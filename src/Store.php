<?php

declare(strict_types=1);

final class Store
{
    public function __construct(private PDO $pdo)
    {
    }

    private function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement;
    }

    private function transaction(callable $work): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $work();
            $this->pdo->commit();
            return $result;
        } catch (Throwable $error) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $error;
        }
    }

    public function allow(string $scope, int $limit, int $seconds): bool
    {
        $window = intdiv(time(), $seconds) * $seconds;
        $client = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? 'unknown', true);
        $params = [$scope, $client, $window];
        $hits = $this->transaction(function () use ($params, $limit): int {
            $this->query(
                'INSERT INTO rate_limits (scope, client_key, window_start, hits)
                 VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE hits = LEAST(hits + 1, ?)',
                [...$params, $limit + 1]
            );
            return (int) $this->query(
                'SELECT hits FROM rate_limits WHERE scope = ? AND client_key = ? AND window_start = ?',
                $params
            )->fetchColumn();
        });
        if (random_int(1, 100) === 1) {
            $this->query('DELETE FROM rate_limits WHERE window_start < ? LIMIT 500', [time() - 3600]);
        }
        return $hits <= $limit;
    }

    public function save(array $fields): array
    {
        return $this->transaction(function () use ($fields): array {
            try {
                $this->query(
                    'INSERT INTO leads_requests (request_uuid, name, phone, email, comment)
                     VALUES (:request_uuid, :name, :phone, :email, :comment)',
                    $fields
                );
                return [$this->pdo->lastInsertId(), true];
            } catch (PDOException $error) {
                if (($error->errorInfo[1] ?? null) !== 1062) {
                    throw $error;
                }
                $id = $this->query(
                    'SELECT id FROM leads_requests WHERE request_uuid = ?',
                    [$fields['request_uuid']]
                )->fetchColumn();
                if ($id === false) {
                    throw $error;
                }
                return [(string) $id, false];
            }
        });
    }

    public function find(string $id): ?array
    {
        $row = $this->query('SELECT * FROM leads_requests WHERE id = ?', [$id])->fetch();
        return $row === false ? null : $row;
    }

    public function claim(string $id): ?array
    {
        return $this->transaction(function () use ($id): ?array {
            $row = $this->query(
                'SELECT *, lease_until > UTC_TIMESTAMP() AS lease_active,
                    next_retry_at > UTC_TIMESTAMP() AS waiting
                 FROM leads_requests WHERE id = ? FOR UPDATE',
                [$id]
            )->fetch();
            if (!$row || $row['delivery_status'] === 'sent' || $row['lease_active']
                || ($row['waiting'] && $row['last_error_code'] === 'crm_create_uncertain')) {
                return null;
            }
            if ($row['delivery_status'] === 'processing') {
                $this->query(
                    "UPDATE delivery_attempts SET outcome = 'retry', error_code = 'lease_expired',
                     finished_at = UTC_TIMESTAMP()
                     WHERE request_id = ? AND attempt_no = ? AND outcome = 'processing'",
                    [$id, $row['attempt_count']]
                );
            }
            $row['lease_token'] = bin2hex(random_bytes(16));
            $row['attempt_count']++;
            $this->query(
                "UPDATE leads_requests SET delivery_status = 'processing', attempt_count = ?,
                 lease_token = ?, lease_until = UTC_TIMESTAMP() + INTERVAL 120 SECOND WHERE id = ?",
                [$row['attempt_count'], $row['lease_token'], $id]
            );
            $this->query(
                'INSERT INTO delivery_attempts (request_id, attempt_no) VALUES (?, ?)',
                [$id, $row['attempt_count']]
            );
            return $row;
        });
    }

    public function classify(array $claim, string $priority, string $source): void
    {
        $this->updateClaim(
            $claim,
            'priority = ?, priority_source = ?, classified_at = UTC_TIMESTAMP()',
            [$priority, $source]
        );
    }

    public function rememberLead(array $claim, string $leadId): void
    {
        $this->updateClaim($claim, 'crm_lead_id = ?', [$leadId]);
    }

    private function updateClaim(array $claim, string $assignments, array $values): bool
    {
        return $this->query(
            "UPDATE leads_requests SET $assignments
             WHERE id = ? AND lease_token = ? AND lease_until > UTC_TIMESTAMP()",
            [...$values, $claim['id'], $claim['lease_token']]
        )->rowCount() === 1;
    }

    public function ownsClaim(array $claim): bool
    {
        return (bool) $this->query(
            'SELECT 1 FROM leads_requests
             WHERE id = ? AND lease_token = ? AND lease_until > UTC_TIMESTAMP()',
            [$claim['id'], $claim['lease_token']]
        )->fetchColumn();
    }

    public function finish(array $claim, ?IntegrationFailure $failure): void
    {
        $this->transaction(function () use ($claim, $failure): void {
            $status = $failure ? 'retry' : 'sent';
            $delay = $failure?->reason === 'crm_create_uncertain' ? 60 : 30;
            $next = $failure ? "UTC_TIMESTAMP() + INTERVAL $delay SECOND" : 'NULL';
            $sent = $failure ? 'NULL' : 'UTC_TIMESTAMP()';
            $changed = $this->updateClaim(
                $claim,
                "delivery_status = ?, last_error_code = ?, last_error_message = ?,
                 next_retry_at = $next, sent_at = $sent, lease_token = NULL, lease_until = NULL",
                [$status, $failure?->reason, $failure?->getMessage()]
            );
            if ($changed) {
                $this->query(
                    'UPDATE delivery_attempts SET outcome = ?, http_status = ?, error_code = ?,
                     finished_at = UTC_TIMESTAMP() WHERE request_id = ? AND attempt_no = ?',
                    [$status, $failure?->httpStatus, $failure?->reason, $claim['id'], $claim['attempt_count']]
                );
            }
        });
    }

    public function requests(array $filters, int $page): array
    {
        $conditions = [];
        $values = [];
        foreach ($filters as $column => $value) {
            $conditions[] = "$column = ?";
            $values[] = $value;
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $offset = ($page - 1) * 20;
        $rows = $this->query(
            "SELECT * FROM leads_requests $where ORDER BY id DESC LIMIT 21 OFFSET $offset",
            $values
        )->fetchAll();
        return [array_slice($rows, 0, 20), count($rows) > 20];
    }

    public function attempts(string $id): array
    {
        return $this->query(
            'SELECT * FROM delivery_attempts WHERE request_id = ? ORDER BY attempt_no DESC',
            [$id]
        )->fetchAll();
    }

    public function counts(): array
    {
        $counts = array_fill_keys(['saved', 'processing', 'retry', 'sent'], 0);
        foreach ($this->query('SELECT delivery_status, COUNT(*) AS total FROM leads_requests GROUP BY delivery_status') as $row) {
            $counts[$row['delivery_status']] = (int) $row['total'];
        }
        return $counts;
    }
}
