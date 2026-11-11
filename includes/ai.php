
<?php

require_once __DIR__ . '/env.php';

function analyzeLeadPriority(string $comment): string
{
    $apiKey = getenv('GEMINI_API_KEY');

    if (!$apiKey || trim($comment) === '') {
        return 'normal';
    }

    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent';

    $prompt = <<<PROMPT
Проанализируй текст заявки клиента и определи её приоритет.

Правила:
- high — срочная заявка, высокая заинтересованность или важная задача.
- normal — обычный запрос на услугу или консультацию.
- low — слабая заинтересованность, общий вопрос или отсутствие конкретной задачи.

Верни только JSON следующего формата:
{"priority":"low"}

Вместо low используй подходящее значение: low, normal или high.

Текст заявки:
{$comment}
PROMPT;

    $payload = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'responseMimeType' => 'application/json',
            'responseSchema' => [
                'type' => 'OBJECT',
                'properties' => [
                    'priority' => [
                        'type' => 'STRING',
                        'enum' => ['low', 'normal', 'high']
                    ]
                ],
                'required' => ['priority']
            ]
        ]
    ];

    try {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $apiKey
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
            ),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false || $httpCode !== 200) {
            error_log(
                'Gemini API error. HTTP: ' . $httpCode .
                '. cURL: ' . $curlError
            );

            return 'normal';
        }

        $data = json_decode($response, true);

        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if (!$text) {
            return 'normal';
        }

        $result = json_decode($text, true);
        $priority = $result['priority'] ?? null;

        if (in_array($priority, ['low', 'normal', 'high'], true)) {
            return $priority;
        }

        return 'normal';

    } catch (Throwable $e) {
        error_log('Gemini API error: ' . $e->getMessage());

        return 'normal';
    }
}
