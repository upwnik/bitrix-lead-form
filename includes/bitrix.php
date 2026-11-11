
<?php

require_once __DIR__ . '/env.php';

function sendLeadToBitrix(array $lead): array
{
    $webhook = getenv('BITRIX_WEBHOOK_URL');

    if (!$webhook) {
        return [
            'success' => false,
            'error' => 'Bitrix24 webhook is not configured'
        ];
    }

    $url = rtrim($webhook, '/') . '/crm.lead.add.json';

    // Формируем данные для CRM
    $fields = [
        'TITLE' => 'Заявка с сайта',
        'NAME' => $lead['name'],
        'PHONE' => [
            [
                'VALUE' => $lead['phone'],
                'VALUE_TYPE' => 'WORK'
            ]
        ],
        'EMAIL' => [
            [
                'VALUE' => $lead['email'],
                'VALUE_TYPE' => 'WORK'
            ]
        ],
        'COMMENTS' => $lead['comment'],
    ];

    try {
        $ch = curl_init($url);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_POSTFIELDS => http_build_query([
                'fields' => $fields
            ]),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        if ($response === false) {
            error_log('Bitrix24 cURL error: ' . $curlError);

            return [
                'success' => false,
                'error' => 'Connection error'
            ];
        }

        $data = json_decode($response, true);

        if (
            $httpCode !== 200 ||
            !isset($data['result']) ||
            !is_numeric($data['result'])
        ) {
            $error = $data['error_description']
                ?? 'Unknown Bitrix24 API error';

            error_log('Bitrix24 API error: ' . $error);

            return [
                'success' => false,
                'error' => $error
            ];
        }

        return [
            'success' => true,
            'lead_id' => (int) $data['result']
        ];

    } catch (Throwable $e) {
        error_log('Bitrix24 error: ' . $e->getMessage());

        return [
            'success' => false,
            'error' => 'Internal connection error'
        ];
    }
}
