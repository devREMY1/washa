<?php
session_start();

// Замените на токен вашего бота
$botToken = '7680844282:AAHYmSve7_5AkWRTiPH8Ud_UuGCGFa1-42Y';

function sendTelegramMessage($chatId, $message) {
    global $botToken;
    $url = "https://api.telegram.org/bot$botToken/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML'
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    return $result !== FALSE;
}

function generateAuthCode() {
    return strval(random_int(100000, 999999));
}

function handleRequest() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'request_code') {
            $telegramId = $_POST['telegramId'] ?? '';
            
            if (empty($telegramId)) {
                return ['success' => false, 'message' => 'Telegram ID не указан'];
            }

            $authCode = generateAuthCode();
            $_SESSION['auth_code_' . $telegramId] = $authCode;
            $_SESSION['auth_code_time_' . $telegramId] = time();

            $message = "Ваш код авторизации: <b>$authCode</b>\n\nОн действителен в течение 5 минут.";
            if (sendTelegramMessage($telegramId, $message)) {
                return ['success' => true, 'message' => 'Код отправлен в Telegram'];
            } else {
                return ['success' => false, 'message' => 'Ошибка отправки кода'];
            }
        } elseif ($action === 'verify_code') {
            $telegramId = $_POST['telegramId'] ?? '';
            $authCode = $_POST['authCode'] ?? '';

            if (empty($telegramId) || empty($authCode)) {
                return ['success' => false, 'message' => 'Telegram ID или код не указаны'];
            }

            $storedCode = $_SESSION['auth_code_' . $telegramId] ?? '';
            $storedTime = $_SESSION['auth_code_time_' . $telegramId] ?? 0;

            if ($storedCode && $storedCode === $authCode && (time() - $storedTime) <= 300) {
                unset($_SESSION['auth_code_' . $telegramId]);
                unset($_SESSION['auth_code_time_' . $telegramId]);
                $_SESSION['authenticated_user'] = $telegramId;
                return ['success' => true, 'message' => 'Авторизация успешна'];
            } else {
                return ['success' => false, 'message' => 'Неверный код или время истекло'];
            }
        }
    }
    return null;
}

$result = handleRequest();
if ($result !== null) {
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

if (isset($_SESSION['authenticated_user'])) {
    $userId = $_SESSION['authenticated_user'];
    $content = <<<HTML
    <h1>Добро пожаловать!</h1>
    <p>Вы успешно авторизовались через Telegram.</p>
    <p>Ваш Telegram ID: {$userId}</p>
    <a href="?logout=1" class="button">Выйти</a>
    HTML;
} else {
    $content = <<<HTML
    <h1>PAW - Авторизация</h1>
    <form id="authForm">
        <input type="text" id="telegramId" placeholder="Ваш Telegram ID" required>
        <button type="submit">Запросить код</button>
    </form>
    <div id="codeForm" style="display: none;">
        <input type="text" id="authCode" placeholder="Введите код из Telegram" required>
        <button type="button" id="submitCode">Войти</button>
    </div>
    <div id="message"></div>
    HTML;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PAW - Авторизация через Telegram</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background-color: #f0f2f5;
        }
        .container {
            background-color: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            max-width: 400px;
            width: 100%;
        }
        h1 {
            text-align: center;
            color: #1a73e8;
        }
        form, #codeForm {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        input {
            padding: 0.5rem;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button, .button {
            padding: 0.5rem;
            background-color: #1a73e8;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
        }
        button:hover, .button:hover {
            background-color: #1557b0;
        }
        #message {
            margin-top: 1rem;
            text-align: center;
            color: #1a73e8;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php echo $content; ?>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const authForm = document.getElementById('authForm');
            const codeForm = document.getElementById('codeForm');
            const submitCodeButton = document.getElementById('submitCode');
            const messageDiv = document.getElementById('message');

            if (authForm) {
                authForm.addEventListener('submit', async (e) => {
                    e.preventDefault();
                    const telegramId = document.getElementById('telegramId').value;
                    
                    try {
                        const response = await fetch('index.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=request_code&telegramId=${encodeURIComponent(telegramId)}`,
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            authForm.style.display = 'none';
                            codeForm.style.display = 'flex';
                            messageDiv.textContent = data.message;
                        } else {
                            messageDiv.textContent = 'Ошибка: ' + data.message;
                        }
                    } catch (error) {
                        messageDiv.textContent = 'Ошибка при отправке запроса';
                    }
                });
            }

            if (submitCodeButton) {
                submitCodeButton.addEventListener('click', async () => {
                    const telegramId = document.getElementById('telegramId').value;
                    const authCode = document.getElementById('authCode').value;
                    
                    try {
                        const response = await fetch('index.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: `action=verify_code&telegramId=${encodeURIComponent(telegramId)}&authCode=${encodeURIComponent(authCode)}`,
                        });
                        
                        const data = await response.json();
                        
                        if (data.success) {
                            window.location.reload();
                        } else {
                            messageDiv.textContent = 'Ошибка: ' + data.message;
                        }
                    } catch (error) {
                        messageDiv.textContent = 'Ошибка при отправке запроса';
                    }
                });
            }
        });
    </script>
</body>
</html>