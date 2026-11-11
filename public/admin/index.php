
<?php

require_once dirname(__DIR__, 2) . '/includes/admin_auth.php';

requireAdmin();

if (empty($_SESSION['admin_csrf_token'])) {
    $_SESSION['admin_csrf_token'] = bin2hex(random_bytes(32));
}

require_once dirname(__DIR__, 2) . '/includes/db.php';

$allowedStatuses = ['saved', 'processing', 'sent', 'retry', 'error'];

$status = $_GET['status'] ?? '';

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

$sql = '
    SELECT
        id,
        name,
        phone,
        email,
        comment,
        priority,
        status,
        bitrix_lead_id,
        attempts,
        last_error,
        created_at,
        sent_at
    FROM leads
';

$params = [];

if ($status !== '') {
    $sql .= ' WHERE status = :status';
    $params['status'] = $status;
}

$sql .= ' ORDER BY created_at DESC LIMIT 100';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$leads = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$statusLabels = [
    'saved' => 'Сохранена',
    'processing' => 'Обрабатывается',
    'sent' => 'Отправлена',
    'retry' => 'Ожидает повтора',
    'error' => 'Ошибка'
];

$priorityLabels = [
    'low' => 'Низкий',
    'normal' => 'Обычный',
    'high' => 'Высокий'
];

?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель — Заявки</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 32px;
            font-family: Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 28px;
        }

        h1 {
            margin: 0;
            font-size: 28px;
        }

        .count {
            color: #6b7280;
            font-size: 14px;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 20px;
        }

        .filters a {
            padding: 9px 14px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            color: #374151;
            text-decoration: none;
            background: #fff;
            font-size: 14px;
        }

        .filters a.active {
            color: #fff;
            border-color: #2563eb;
            background: #2563eb;
        }

        .table-wrap {
            overflow-x: auto;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
        }

        table {
            width: 100%;
            min-width: 1050px;
            border-collapse: collapse;
        }

        th, td {
            padding: 14px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
            font-size: 14px;
        }

        th {
            color: #6b7280;
            background: #f9fafb;
            font-weight: 600;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .muted {
            color: #6b7280;
            font-size: 12px;
        }

        .comment {
            max-width: 260px;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .error {
            max-width: 240px;
            color: #b91c1c;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }

        .empty {
            padding: 40px;
            color: #6b7280;
            text-align: center;
        }

        @media (max-width: 700px) {
            body {
                padding: 16px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <div class="header">
        <div>
            <h1>Заявки</h1>
            <div class="count">
                Показано: <?= count($leads) ?> из последних 100
            </div>
        </div>

        <a href="/admin/logout.php">Выйти</a>
    </div>

    <nav class="filters">
        <a href="/admin/" class="<?= $status === '' ? 'active' : '' ?>">
            Все
        </a>

        <?php foreach ($statusLabels as $key => $label): ?>
            <a
                href="/admin/?status=<?= e($key) ?>"
                class="<?= $status === $key ? 'active' : '' ?>"
            >
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="table-wrap">
        <?php if (!$leads): ?>

            <div class="empty">
                Заявок пока нет.
            </div>

        <?php else: ?>

            <table>
                <thead>
                <tr>
                    <th>ID / Дата</th>
                    <th>Клиент</th>
                    <th>Комментарий</th>
                    <th>Приоритет</th>
                    <th>Статус</th>
                    <th>Bitrix24</th>
                    <th>Попытки / Ошибка</th>
                    <th>Действия</th>
                </tr>
                </thead>

                <tbody>
                <?php foreach ($leads as $lead): ?>
                    <tr>
                        <td>
                            #<?= (int) $lead['id'] ?><br>
                            <span class="muted">
                                <?= e($lead['created_at']) ?>
                            </span>
                        </td>

                        <td>
                            <strong><?= e($lead['name']) ?></strong><br>
                            <?= e($lead['phone']) ?><br>
                            <?= e($lead['email']) ?>
                        </td>

                        <td>
                            <div class="comment">
                                <?= e($lead['comment']) ?>
                            </div>
                        </td>

                        <td>
                            <?= e($priorityLabels[$lead['priority']] ?? $lead['priority']) ?>
                        </td>

                        <td>
                            <?= e($statusLabels[$lead['status']] ?? $lead['status']) ?>
                        </td>

                        <td>
                            <?php if ($lead['bitrix_lead_id']): ?>
                                ID: <?= (int) $lead['bitrix_lead_id'] ?>
                            <?php else: ?>
                                <span class="muted">Не отправлена</span>
                            <?php endif; ?>

                            <?php if ($lead['sent_at']): ?>
                                <br>
                                <span class="muted">
                                    <?= e($lead['sent_at']) ?>
                                </span>
                            <?php endif; ?>
                        </td>


                        <td>
                            Попыток: <?= (int) $lead['attempts'] ?>

                            <?php if ($lead['last_error']): ?>
                                <div class="error">
                                    <?= e($lead['last_error']) ?>
                                </div>
                            <?php else: ?>
                                <span class="muted">Ошибок нет</span>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?php if ($lead['status'] === 'retry' && !$lead['bitrix_lead_id']): ?>
                                <form method="POST" action="/admin/retry.php">
                                    <input
                                        type="hidden"
                                        name="lead_id"
                                        value="<?= (int) $lead['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['admin_csrf_token']) ?>"
                                    >

                                    <button type="submit">
                                        Повторить
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="muted">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>
    </div>

</div>
</body>
</html>
