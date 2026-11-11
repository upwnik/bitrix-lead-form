
<?php

$envPath = dirname(__DIR__) . '/.env';

if (!is_file($envPath)) {
    throw new RuntimeException('Файл .env не найден');
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
    $line = trim($line);

    if ($line === '' || str_starts_with($line, '#')) {
        continue;
    }

    [$name, $value] = explode('=', $line, 2);

    $name = trim($name);
    $value = trim($value);
    $value = trim($value, "\"'");

    if ($name !== '') {
        putenv("$name=$value");
        $_ENV[$name] = $value;
    }
}
