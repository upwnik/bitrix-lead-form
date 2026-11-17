
<?php

require_once __DIR__ . '/env.php';

/**
 * Определяет приоритет заявки через YandexGPT.
 */
function analyzeLeadPriority(string $comment): string
{
    if (trim($comment) === '') {
        return 'normal';
    }

    $apiKey = getenv('YANDEX_API_KEY');
    $folderId = getenv('YANDEX_FOLDER_ID');
    $model = getenv('YANDEX_MODEL');

    if (!$apiKey || !$folderId || !$model) {
        error_log('Yandex AI: Missing configuration.');
        return 'normal';
    }

    $apiUrl = 'https://ai.api.cloud.yandex.net/v1/chat/completions';

    $systemPrompt = <<<'PROMPT'
Ты — классификатор обращений технической поддержки.

Определи приоритет обращения по следующим правилам:

- low — общий вопрос, консультация или незначительная проблема,
  которая не мешает основной работе пользователя.
- normal — проблема с отдельной функцией или сервисом,
  которая мешает работе одного пользователя.
- high — массовый сбой, недоступность критически важного сервиса,
  потеря данных или угроза безопасности.

Если масштаб проблемы неизвестен, не считай её массовой.

Верни строго JSON без Markdown и дополнительных пояснений:
{"priority":"normal"}

Допустимые значения priority: low, normal, high.
PROMPT;

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => "Текст обращения клиента:\n" . $comment
            ]
        ],
        'temperature' => 0,
        'max_tokens' => 50,
        'stream' => false
    ];

    try {
        $ch = curl_init($apiUrl);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Api-Key ' . $apiKey,
                'OpenAI-Project: ' . $folderId
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
            ),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false || $httpCode !== 200) {
            error_log(
                'Yandex AI API error. HTTP: ' . $httpCode .
                '. cURL: ' . $curlError
            );

            return 'normal';
        }

        $data = json_decode($response, true);

        $text = $data['choices'][0]['message']['content'] ?? null;

        if (!is_string($text) || trim($text) === '') {
            error_log('Yandex AI: Empty model response.');
            return 'normal';
        }

        $result = json_decode(trim($text), true);

        // Дополнительная обработка JSON, обёрнутого в Markdown.
        if (!is_array($result)) {
            if (preg_match('/\{.*\}/s', $text, $matches)) {
                $result = json_decode($matches[0], true);
            }
        }

        $priority = $result['priority'] ?? null;

        if (in_array($priority, ['low', 'normal', 'high'], true)) {
            return $priority;
        }

        error_log('Yandex AI: Invalid priority response.');

        return 'normal';

    } catch (Throwable $e) {
        error_log('Yandex AI exception: ' . $e->getMessage());

        return 'normal';
    }
}
