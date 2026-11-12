<?php

declare(strict_types=1);

function submitRequest(): never
{
    $type = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($type !== 'application/json') {
        jsonResponse(415, ['message' => 'Ожидается JSON.']);
    }
    $maxBytes = 65536;
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $maxBytes) {
        jsonResponse(413, ['message' => 'Заявка слишком большая.']);
    }
    $body = file_get_contents('php://input', false, null, 0, $maxBytes + 1);
    if ($body === false || strlen($body) > $maxBytes) {
        jsonResponse(413, ['message' => 'Заявка слишком большая.']);
    }
    $input = json_decode($body);
    if (!$input instanceof stdClass) {
        jsonResponse(400, ['message' => 'Ожидается объект JSON.']);
    }
    $store = new Store(database());
    if (!$store->allow('requests', 15, 60)) {
        header('Retry-After: ' . (60 - time() % 60));
        jsonResponse(429, ['message' => 'Слишком много попыток. Подождите немного и попробуйте снова.']);
    }
    [$fields, $errors] = validateRequest((array) $input);
    if ($errors) {
        jsonResponse(422, ['message' => 'Проверьте поля формы.', 'errors' => $errors]);
    }
    [$id, $created] = $store->save($fields);
    if ($created) {
        ignore_user_abort(true);
        try {
            (new Delivery($store))->attempt($id);
        } catch (Throwable $error) {
            error_log('SPEKTR delivery failure: ' . get_class($error));
        }
    }
    jsonResponse($created ? 201 : 200, ['message' => 'Заявка принята.', 'request_uuid' => $fields['request_uuid']]);
}
