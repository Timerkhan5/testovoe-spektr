<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

try {
    $sql = file_get_contents(dirname(__DIR__) . '/database/001_init.sql');
    if ($sql === false) {
        throw new RuntimeException('Migration file missing');
    }
    $pdo = database();
    foreach (explode(';', $sql) as $statement) {
        if (trim($statement) !== '') {
            $pdo->exec($statement);
        }
    }
    echo "Schema ready.\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'Migration failed. Check DB settings and schema permissions. ' . get_class($error) . "\n");
    exit(1);
}
