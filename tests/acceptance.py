"""Exercise the HTTP application with MySQL and local Bitrix/OpenRouter fixtures."""

import concurrent.futures
import contextlib
import http.cookiejar
import json
import os
import re
import secrets
import shlex
import socket
import subprocess
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

PRIORITY_FIELD = "UF_CRM_TEST_PRIORITY"
UUID_FIELD = "UF_CRM_TEST_UUID"
PRIORITIES = {"low": "101", "normal": "102", "high": "103"}


class Fixture(BaseHTTPRequestHandler):
    def log_message(self, *_args):
        pass

    def do_POST(self):
        payload = json.loads(self.rfile.read(int(self.headers["Content-Length"])))
        state = self.server.state
        if self.path.endswith("/chat/completions"):
            comment = payload["messages"][1]["content"]
            state["ai_comments"].append(comment)
            if comment == "ai-offline":
                return self.reply({"error": "offline"}, 503)
            content = "invalid" if comment == "ai-invalid" else json.dumps({
                "priority": {"urgent": "high", "exploring": "low"}.get(comment, "normal")
            })
            return self.reply({"choices": [{"message": {"content": content}}]})
        if state["offline"]:
            return self.reply({"error": "offline"}, 503)

        with self.server.lock:
            leads = state["leads"]
            method = self.path.rsplit("/", 1)[-1]
            if method == "crm.lead.list.json":
                key = payload["filter"]["=" + UUID_FIELD]
                return self.reply({"result": [
                    {"ID": lead["ID"], UUID_FIELD: lead[UUID_FIELD]}
                    for lead in leads.values() if lead[UUID_FIELD] == key
                ]})
            if method == "crm.lead.add.json":
                fields = payload["fields"]
                assert {"TITLE", "NAME", "PHONE", "EMAIL", "COMMENTS", UUID_FIELD, PRIORITY_FIELD} <= fields.keys()
                assert fields["PHONE"][0]["VALUE"] == "+79000000001"
                key = str(len(leads) + 1)
                leads[key] = dict(fields, ID=key)
                if fields["COMMENTS"] == "lost":
                    self.close_connection = True
                    return
                if fields["COMMENTS"] == "repair":
                    del leads[key][PRIORITY_FIELD]
                return self.reply({"result": key})
            if method == "crm.lead.get.json":
                return self.reply({"result": leads[payload["id"]]})
            if method == "crm.lead.update.json":
                state["updates"] += 1
                if state["reject_update"]:
                    return self.reply({"error": "temporary"}, 400)
                leads[payload["id"]].update(payload["fields"])
                return self.reply({"result": True})
        self.reply({"error": "unexpected_method"}, 404)

    def reply(self, body, status=200):
        data = json.dumps(body).encode()
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(data)))
        self.end_headers()
        self.wfile.write(data)


class Client:
    def __init__(self, base):
        self.base = base
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def request(self, path, data=None, as_json=False):
        headers = {}
        if data is not None:
            data = (json.dumps(data) if as_json else urllib.parse.urlencode(data)).encode()
            headers["Content-Type"] = "application/json" if as_json else "application/x-www-form-urlencoded"
        request = urllib.request.Request(self.base + path, data, headers)
        try:
            response = self.opener.open(request, timeout=32)
        except urllib.error.HTTPError as error:
            response = error
        with response:
            return response.status, response.read().decode(), response.url

    def submit(self, data, expected=201):
        status, body, _ = self.request("/api/requests", data, as_json=True)
        assert status == expected, (status, body)
        assert json.loads(body) == {"message": "Заявка принята.", "request_uuid": data["request_uuid"]}


def csrf(html):
    return re.search(r'name="csrf" value="([0-9a-f]+)"', html).group(1)


def submission(comment):
    return {
        "request_uuid": str(uuid.uuid4()),
        "name": "<QA> Фамилия",
        "email": "test@example.invalid",
        "phone": "+7 (900) 000-00-01",
        "comment": comment,
    }


def local_address():
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return f"127.0.0.1:{sock.getsockname()[1]}"


@contextlib.contextmanager
def app_server(php, env):
    address = local_address()
    with subprocess.Popen(
        [*php, "-S", address, "-t", "public", "public/index.php"],
        env=env,
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    ) as process:
        base = "http://" + address
        try:
            for _ in range(50):
                try:
                    assert Client(base).request("/health")[0] == 200
                    break
                except urllib.error.URLError:
                    time.sleep(0.1)
            else:
                raise RuntimeError("PHP did not start")
            yield Client(base)
        finally:
            process.terminate()


def exercise(client, peer, fixture, sql, password):
    def row(data):
        return sql("SELECT * FROM leads_requests WHERE request_uuid = ?", data["request_uuid"])[0]

    def matching_leads(data):
        return [lead for lead in fixture["leads"].values() if lead[UUID_FIELD] == data["request_uuid"]]

    def assert_delivered(data, priority, source):
        saved = row(data)
        leads = matching_leads(data)
        assert saved["delivery_status"] == "sent"
        assert (saved["priority"], saved["priority_source"]) == (priority, source)
        assert len(leads) == 1 and leads[0][PRIORITY_FIELD] == PRIORITIES[priority]
        assert str(saved["crm_lead_id"]) == leads[0]["ID"]

    def retry(data):
        path = f"/admin/requests/{row(data)['id']}"
        _, html, _ = client.request(path)
        assert client.request(path + "/retry", {"csrf": "wrong"})[0] == 403
        status, html, _ = client.request(path + "/retry", {"csrf": csrf(html)})
        assert status == 200
        return html

    # T11 and administrative authentication/CSRF.
    assert client.request("/admin/requests")[2].endswith("/admin/login")
    assert client.request("/admin/requests/1/retry", {})[0] == 403
    status, html, _ = client.request("/admin/login")
    assert status == 200
    assert client.request("/admin/login", {
        "username": "qa", "password": password, "csrf": csrf(html),
    })[2].endswith("/admin/requests")

    for comment, priority, source in [
        ("urgent", "high", "ai"),
        ("exploring", "low", "ai"),
        ("routine", "normal", "ai"),
        ("ai-invalid", "normal", "fallback"),
        ("ai-offline", "normal", "fallback"),
        ("", "normal", "fallback"),
    ]:
        data = submission(comment)
        client.submit(data)
        assert_delivered(data, priority, source)
    assert fixture["ai_comments"] == ["urgent", "exploring", "routine", "ai-invalid", "ai-offline"]
    print("T01–T04 passed")

    fixture["offline"] = True
    data = submission("crm failure")
    client.submit(data)
    assert row(data)["delivery_status"] == "retry" and not matching_leads(data)
    fixture["offline"] = False
    assert "Лид подтверждён" in retry(data)
    assert_delivered(data, "normal", "ai")
    assert row(data)["attempt_count"] == 2
    print("T05–T06 passed")

    data = submission("same UUID, two PHP workers")
    with concurrent.futures.ThreadPoolExecutor() as pool:
        responses = list(pool.map(lambda app: app.request("/api/requests", data, as_json=True), [client, peer]))
    assert sorted(response[0] for response in responses) == [200, 201], responses
    client.submit(data, 200)
    assert_delivered(data, "normal", "ai")
    assert row(data)["attempt_count"] == 1
    print("T07 passed")

    data = submission("lost")
    client.submit(data)
    assert row(data)["last_error_code"] == "crm_create_uncertain"
    assert row(data)["crm_lead_id"] is None and len(matching_leads(data)) == 1
    retry(data)
    assert row(data)["attempt_count"] == 1
    sql("UPDATE leads_requests SET next_retry_at = UTC_TIMESTAMP() WHERE request_uuid = ?", data["request_uuid"])
    retry(data)
    assert_delivered(data, "normal", "ai")
    print("T08 passed")

    data = submission("repair")
    fixture["reject_update"] = True
    client.submit(data)
    assert row(data)["last_error_code"] == "crm_priority_update"
    assert row(data)["crm_lead_id"] is not None and len(matching_leads(data)) == 1
    fixture["reject_update"] = False
    html = retry(data)
    assert_delivered(data, "normal", "ai")
    assert fixture["updates"] == 2 and "История попыток" in html
    assert "Повторить отправку" not in html
    print("T09 passed")

    data = submission("invalid")
    data.update(email="invalid", phone="nope")
    status, body, _ = client.request("/api/requests", data, as_json=True)
    assert status == 422 and {"email", "phone"} <= json.loads(body)["errors"].keys()
    assert sql("SELECT id FROM leads_requests WHERE request_uuid = ?", data["request_uuid"]) == []
    _, html, _ = client.request("/admin/requests?delivery_status=sent&priority=high")
    assert "&lt;QA&gt; Фамилия" in html and "<QA>" not in html
    client.request("/admin/logout", {"csrf": csrf(html)})
    assert client.request("/admin/requests")[2].endswith("/admin/login")
    print("T10–T11 passed")


def main():
    db_name = os.environ.get("SPEKTR_TEST_DB_NAME", "spektr_test")
    if not re.fullmatch(r"[a-zA-Z0-9_]+_test", db_name):
        raise SystemExit("Use a dedicated test database whose name ends in _test.")
    php = ["php", *shlex.split(os.environ.get("SPEKTR_TEST_PHP_FLAGS", ""))]
    password = secrets.token_hex(16)
    env = os.environ | {
        "APP_ENV": "local",
        "DB_NAME": db_name,
        "DB_HOST": os.environ.get("SPEKTR_TEST_DB_HOST", "127.0.0.1"),
        "DB_PORT": os.environ.get("SPEKTR_TEST_DB_PORT", "3306"),
        "DB_USER": os.environ["SPEKTR_TEST_DB_USER"],
        "DB_PASSWORD": os.environ.get("SPEKTR_TEST_DB_PASSWORD", ""),
        "ADMIN_USER": "qa",
        "BITRIX_PRIORITY_FIELD": PRIORITY_FIELD,
        "BITRIX_UUID_FIELD": UUID_FIELD,
        **{"BITRIX_PRIORITY_" + key.upper(): value for key, value in PRIORITIES.items()},
        "BITRIX_SOURCE_ID": "",
        "AI_API_KEY": "local-fixture",
        "AI_MODEL": "local-fixture",
    }
    env["ADMIN_PASSWORD_HASH"] = subprocess.check_output([
        *php, "-r", "echo password_hash($argv[1], PASSWORD_DEFAULT);", password,
    ], text=True)
    subprocess.run([*php, "scripts/migrate.php"], env=env, check=True)

    def sql(query, arg):
        return json.loads(subprocess.check_output([
            *php, "-r",
            "require 'src/bootstrap.php'; $q = database()->prepare($argv[1]); "
            "$q->execute([$argv[2]]); echo json_encode($q->fetchAll());", query, arg,
        ], env=env))

    with ThreadingHTTPServer(("127.0.0.1", 0), Fixture) as server:
        server.state = {"leads": {}, "offline": False, "reject_update": False, "updates": 0, "ai_comments": []}
        server.lock = threading.Lock()
        fixture_url = f"http://127.0.0.1:{server.server_port}"
        env.update(BITRIX_WEBHOOK_URL=fixture_url + "/crm", AI_BASE_URL=fixture_url + "/ai")
        threading.Thread(target=server.serve_forever, daemon=True).start()
        try:
            with app_server(php, env) as client, app_server(php, env) as peer:
                exercise(client, peer, server.state, sql, password)
        finally:
            server.shutdown()


if __name__ == "__main__":
    main()
