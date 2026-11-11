
<?php

require_once dirname(__DIR__, 2) . '/includes/env.php';
require_once dirname(__DIR__, 2) . '/includes/admin_auth.php';

startAdminSession();

if (isAdminAuthenticated()) {
    header('Location: /admin/');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $adminUsername = getenv('ADMIN_USERNAME');
    $passwordHash = getenv('ADMIN_PASSWORD_HASH');

    if (
        $adminUsername &&
        $passwordHash &&
        hash_equals($adminUsername, $username) &&
        password_verify($password, $passwordHash)
    ) {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;

        header('Location: /admin/');
        exit;
    }

    $error = 'Неверный логин или пароль';
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в админ-панель</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 20px;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
        }

        .login {
            width: 100%;
            max-width: 380px;
            padding: 32px;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .06);
        }

        h1 {
            margin: 0 0 24px;
            font-size: 24px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input {
            width: 100%;
            padding: 12px;
            margin-bottom: 18px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 16px;
        }

        button {
            width: 100%;
            padding: 13px;
            border: 0;
            border-radius: 7px;
            background: #2563eb;
            color: white;
            font-size: 16px;
            cursor: pointer;
        }

        .error {
            margin-bottom: 16px;
            color: #dc2626;
            font-size: 14px;
        }
    </style>
</head>
<body>

<div class="login">
    <h1>Администратор</h1>

    <?php if ($error): ?>
        <div class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <label for="username">Логин</label>
        <input
            type="text"
            id="username"
            name="username"
            required
            autocomplete="username"
        >

        <label for="password">Пароль</label>
        <input
            type="password"
            id="password"
            name="password"
            required
            autocomplete="current-password"
        >

        <button type="submit">Войти</button>
    </form>
</div>

</body>
</html>
