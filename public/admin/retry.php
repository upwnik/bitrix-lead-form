
<?php

require_once dirname(__DIR__, 2) . '/includes/admin_auth.php';

requireAdmin();

require_once dirname(__DIR__, 2) . '/includes/db.php';
require_once dirname(__DIR__, 2) . '/includes/bitrix.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

startAdminSession();

$csrfToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['admin_csrf_token'] ?? '';

if (
    !$sessionToken ||
    !is_string($csrfToken) ||
    !hash_equals($sessionToken, $csrfToken)
) {
    http_response_code(403);
    exit('Invalid CSRF token');
}

$leadId = filter_input(INPUT_POST, 'lead_id', FILTER_VALIDATE_INT);

if (!$leadId || $leadId < 1) {
    http_response_code(400);
    exit('Invalid lead ID');
}

// Забираем заявку только если она действительно ожидает повтора.
$stmt = $pdo->prepare("
    UPDATE leads
    SET status = 'processing'
    WHERE id = :id
      AND status = 'retry'
      AND bitrix_lead_id IS NULL
");

$stmt->execute(['id' => $leadId]);

if ($stmt->rowCount() !== 1) {
    header('Location: /admin/?message=not_available');
    exit;
}

// Получаем данные заявки.
$stmt = $pdo->prepare("
    SELECT id, name, phone, email, comment
    FROM leads
    WHERE id = :id
");

$stmt->execute(['id' => $leadId]);

$lead = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$lead) {
    header('Location: /admin/');
    exit;
}

// Повторяем отправку в Bitrix24.
$result = sendLeadToBitrix($lead);

if ($result['success']) {
    $stmt = $pdo->prepare("
        UPDATE leads
        SET
            status = 'sent',
            bitrix_lead_id = :bitrix_lead_id,
            sent_at = NOW(),
            last_error = NULL,
            next_retry_at = NULL
        WHERE id = :id
    ");

    $stmt->execute([
        'bitrix_lead_id' => $result['lead_id'],
        'id' => $leadId
    ]);
} else {
    $stmt = $pdo->prepare("
        UPDATE leads
        SET
            status = 'retry',
            attempts = attempts + 1,
            last_error = :last_error,
            next_retry_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
        WHERE id = :id
    ");

    $stmt->execute([
        'last_error' => $result['error'] ?? 'Unknown Bitrix24 error',
        'id' => $leadId
    ]);
}

header('Location: /admin/');
exit;
