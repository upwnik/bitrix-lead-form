
<?php

header('Content-Type: application/json; charset=utf-8');

function respond(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Принимаем только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, [
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}

// Читаем JSON
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    respond(400, [
        'success' => false,
        'message' => 'Invalid JSON'
    ]);
}

// Получаем данные
$uuid = trim($data['request_id'] ?? '');
$name = trim($data['name'] ?? '');
$phone = trim($data['phone'] ?? '');
$email = trim($data['email'] ?? '');
$comment = trim($data['comment'] ?? '');

// Проверяем UUID
if (!preg_match(
    '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
    $uuid
)) {
    respond(422, [
        'success' => false,
        'message' => 'Invalid request ID'
    ]);
}

// Серверная валидация
if ($name === '' || mb_strlen($name) > 255) {
    respond(422, [
        'success' => false,
        'message' => 'Введите корректное имя'
    ]);
}

if ($phone === '' || mb_strlen($phone) > 30) {
    respond(422, [
        'success' => false,
        'message' => 'Введите корректный телефон'
    ]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 255) {
    respond(422, [
        'success' => false,
        'message' => 'Введите корректный email'
    ]);
}

if (mb_strlen($comment) > 5000) {
    respond(422, [
        'success' => false,
        'message' => 'Комментарий слишком длинный'
    ]);
}

try {
    require_once __DIR__ . '/../../includes/db.php';
    require_once __DIR__ . '/../../includes/ai.php';
    require_once __DIR__ . '/../../includes/bitrix.php';

    // Проверяем дубликат
    $check = $pdo->prepare(
        'SELECT id, status FROM leads WHERE uuid = :uuid'
    );

    $check->execute(['uuid' => $uuid]);
    $existing = $check->fetch();

    if ($existing) {
        respond(200, [
            'success' => true,
            'duplicate' => true,
            'message' => 'Заявка уже принята',
            'lead_id' => $existing['id'],
            'status' => $existing['status']
        ]);
    }

    // 1. Сначала сохраняем заявку в MySQL
    $stmt = $pdo->prepare(
        'INSERT INTO leads
        (uuid, name, phone, email, comment, priority, status)
        VALUES
        (:uuid, :name, :phone, :email, :comment, :priority, :status)'
    );

    $stmt->execute([
        'uuid' => $uuid,
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'comment' => $comment,
        'priority' => 'normal',
        'status' => 'saved'
    ]);

    $leadId = (int) $pdo->lastInsertId();

    // 2. Анализируем заявку через Gemini
    $priority = analyzeLeadPriority($comment);

    $updatePriority = $pdo->prepare(
        'UPDATE leads SET priority = :priority WHERE id = :id'
    );

    $updatePriority->execute([
        'priority' => $priority,
        'id' => $leadId
    ]);

    // 3. Отправляем заявку в Bitrix24
    $bitrixResult = sendLeadToBitrix([
        'name' => $name,
        'phone' => $phone,
        'email' => $email,
        'comment' => $comment
    ]);

    if ($bitrixResult['success']) {

        // Bitrix24 принял заявку
        $update = $pdo->prepare(
            "UPDATE leads
            SET status = 'sent',
                bitrix_lead_id = :bitrix_id,
                sent_at = NOW(),
                last_error = NULL,
                next_retry_at = NULL
            WHERE id = :id"
        );

        $update->execute([
            'bitrix_id' => $bitrixResult['lead_id'],
            'id' => $leadId
        ]);

        respond(201, [
            'success' => true,
            'message' => 'Заявка успешно отправлена в Bitrix24',
            'lead_id' => $leadId,
            'priority' => $priority,
            'status' => 'sent',
            'bitrix_lead_id' => $bitrixResult['lead_id']
        ]);

    } else {

        // Заявка сохранена, но CRM не приняла её
        $update = $pdo->prepare(
            "UPDATE leads
            SET status = 'retry',
                attempts = attempts + 1,
                last_error = :error,
                next_retry_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE)
            WHERE id = :id"
        );

        $update->execute([
            'error' => $bitrixResult['error'],
            'id' => $leadId
        ]);

        respond(201, [
            'success' => true,
            'message' => 'Заявка сохранена, отправка в Bitrix24 не удалась',
            'lead_id' => $leadId,
            'priority' => $priority,
            'status' => 'retry'
        ]);
    }

} catch (PDOException $e) {

    if ($e->getCode() === '23000') {
        respond(200, [
            'success' => true,
            'duplicate' => true,
            'message' => 'Заявка уже принята'
        ]);
    }

    error_log($e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'Ошибка сохранения заявки'
    ]);

} catch (Throwable $e) {

    error_log($e->getMessage());

    respond(500, [
        'success' => false,
        'message' => 'Внутренняя ошибка сервера'
    ]);
}
