
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оставить заявку</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 40px 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f3f5f8;
            font-family: Arial, sans-serif;
            color: #20242c;
        }

        .form-card {
            width: 100%;
            max-width: 480px;
            padding: 36px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 8px 35px rgba(0, 0, 0, 0.06);
        }

        h1 {
            margin: 0 0 10px;
            font-size: 28px;
        }

        .description {
            margin: 0 0 28px;
            color: #737b89;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 600;
        }

        input,
        textarea {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid #dce1e8;
            border-radius: 8px;
            font: inherit;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s;
        }

        input:focus,
        textarea:focus {
            border-color: #4778e8;
        }

        textarea {
            min-height: 110px;
            resize: vertical;
        }

        button {
            width: 100%;
            padding: 15px;
            border: none;
            border-radius: 8px;
            background: #356ce0;
            color: #fff;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #2458c5;
        }

        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .message {
            display: none;
            margin-top: 18px;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            line-height: 1.5;
        }

        .message.success {
            display: block;
            background: #e8f7ed;
            color: #21763b;
        }

        .message.error {
            display: block;
            background: #fff0f0;
            color: #b42318;
        }

        @media (max-width: 480px) {
            body {
                padding: 20px 14px;
            }

            .form-card {
                padding: 26px 20px;
            }

            h1 {
                font-size: 24px;
            }
        }
    </style>
</head>

<body>

<main class="form-card">
    <h1>Оставить заявку</h1>

    <p class="description">
        Заполните форму, и мы свяжемся с вами.
    </p>

    <form id="leadForm">

        <div class="form-group">
            <label for="name">Ваше имя *</label>
            <input
                type="text"
                id="name"
                name="name"
                placeholder="Иван Иванов"
                maxlength="255"
                autocomplete="name"
                required
            >
        </div>

        <div class="form-group">
            <label for="phone">Телефон *</label>
            <input
                type="tel"
                id="phone"
                name="phone"
                placeholder="+7 (999) 123-45-67"
                maxlength="30"
                autocomplete="tel"
                required
            >
        </div>

        <div class="form-group">
            <label for="email">Email *</label>
            <input
                type="email"
                id="email"
                name="email"
                placeholder="example@mail.ru"
                maxlength="255"
                autocomplete="email"
                required
            >
        </div>

        <div class="form-group">
            <label for="comment">Комментарий</label>
            <textarea
                id="comment"
                name="comment"
                placeholder="Расскажите подробнее о вашем запросе..."
                maxlength="5000"
            ></textarea>
        </div>

        <button type="submit" id="submitButton">
            Отправить заявку
        </button>

        <div
            id="formMessage"
            class="message"
            role="status"
            aria-live="polite"
        ></div>

    </form>
</main>

<script>
    const form = document.getElementById('leadForm');
    const button = document.getElementById('submitButton');
    const message = document.getElementById('formMessage');

    // Один UUID используется до подтверждения сохранения заявки.
    let requestId = null;

    form.addEventListener('submit', async function(event) {
        event.preventDefault();

        if (!form.reportValidity()) {
            return;
        }

        if (!requestId) {
            requestId = crypto.randomUUID();
        }

        const formData = new FormData(form);

        const data = {
            request_id: requestId,
            name: formData.get('name').trim(),
            phone: formData.get('phone').trim(),
            email: formData.get('email').trim(),
            comment: formData.get('comment').trim()
        };

        button.disabled = true;
        button.textContent = 'Отправка...';

        message.className = 'message';
        message.textContent = '';

        try {
            const response = await fetch('/api/submit.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(
                    result.message || 'Не удалось отправить заявку'
                );
            }

            message.textContent =
                result.message + '. Номер заявки: ' + result.lead_id;

            message.className = 'message success';

            form.reset();

            // Следующая заявка получит новый UUID.
            requestId = null;

        } catch (error) {
            message.textContent =
                error.message || 'Ошибка соединения с сервером';

            message.className = 'message error';

        } finally {
            button.disabled = false;
            button.textContent = 'Отправить заявку';
        }
    });
</script>

</body>
</html>
