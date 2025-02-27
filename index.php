<?php
session_start(); // Запускаем сессию

// Устанавливаем кодировку страницы
header('Content-Type: text/html; charset=UTF-8');

// Подключение к базе данных
$dsn = 'mysql:host=localhost;dbname=u68648;charset=utf8';
$username = 'u68648';
$password = '7759086';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Ошибка подключения к базе данных: ' . $e->getMessage());
}

// Время жизни куки для успешных данных – 1 год
$one_year = time() + (365 * 24 * 60 * 60);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Массивы для ошибок и значений
    $errors = [];
    $values = [];

    // 1. ФИО
    $full_name = trim($_POST['full_name'] ?? '');
    $values['full_name'] = $full_name;
    if (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]+$/u', $full_name) || iconv_strlen($full_name, 'UTF-8') > 150) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефисы, не более 150 символов.';
    }

    // 2. Телефон
    $phone = trim($_POST['phone'] ?? '');
    $values['phone'] = $phone;
    if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
        $errors['phone'] = 'Телефон должен содержать от 10 до 15 цифр, возможно с префиксом "+".';
    }

    // 3. E-mail
    $email = trim($_POST['email'] ?? '');
    $values['email'] = $email;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат e-mail.';
    }

    // 4. Дата рождения
    $birth_date = $_POST['birth_date'] ?? '';
    $values['birth_date'] = $birth_date;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth_date)) {
        $errors['birth_date'] = 'Дата рождения должна быть в формате ГГГГ-ММ-ДД.';
    } else {
        $date = DateTime::createFromFormat('Y-m-d', $birth_date);
        if (!$date || $date->format('Y-m-d') !== $birth_date || $date > new DateTime()) {
            $errors['birth_date'] = 'Некорректная дата рождения или дата в будущем.';
        }
    }

    // 5. Пол
    $gender = $_POST['gender'] ?? '';
    $values['gender'] = $gender;
    if (!in_array($gender, ['male', 'female'])) {
        $errors['gender'] = 'Некорректное значение пола.';
    }

    // 6. Языки программирования
    $languages = $_POST['languages'] ?? [];
    $values['languages'] = $languages;
    if (empty($languages)) {
        $errors['languages'] = 'Выберите хотя бы один язык программирования.';
    } else {
        $valid_languages = range(1, 12);
        foreach ($languages as $lang) {
            if (!is_numeric($lang) || !in_array((int)$lang, $valid_languages)) {
                $errors['languages'] = 'Некорректный выбор языка программирования.';
                break;
            }
        }
    }

    // 7. Биография
    $biography = trim($_POST['biography'] ?? '');
    $values['biography'] = $biography;
    if (empty($biography)) {
        $errors['biography'] = 'Поле биографии не может быть пустым.';
    }

    // 8. Согласие с контрактом
    $contract_accepted = isset($_POST['contract_accepted']) ? 1 : 0;
    $values['contract_accepted'] = $contract_accepted;
    if (!$contract_accepted) {
        $errors['contract_accepted'] = 'Необходимо согласиться с контрактом.';
    }

    // Если есть ошибки – сохраняем их в Cookies и перенаправляем обратно
    if (!empty($errors)) {
        foreach ($errors as $field => $error_message) {
            setcookie($field . '_error', $error_message, 0, "/");
        }
        foreach ($values as $field => $value) {
            setcookie($field . '_value', is_array($value) ? implode(',', $value) : $value, 0, "/");
        }
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }

    // Сохранение данных в базу
    try {
        $pdo->beginTransaction();

        // Вставляем данные в applications
        $stmt = $pdo->prepare("
            INSERT INTO applications (full_name, phone, email, birth_date, gender, biography, contract_accepted)
            VALUES (:full_name, :phone, :email, :birth_date, :gender, :biography, :contract_accepted)
        ");
        $stmt->execute([
            'full_name' => $full_name,
            'phone' => $phone,
            'email' => $email,
            'birth_date' => $birth_date,
            'gender' => $gender,
            'biography' => $biography,
            'contract_accepted' => $contract_accepted,
        ]);

        $application_id = $pdo->lastInsertId();

        // Вставляем выбранные языки программирования
        $stmt = $pdo->prepare("
            INSERT INTO application_languages (application_id, language_id)
            VALUES (:application_id, :language_id)
        ");
        foreach ($languages as $lang) {
            $stmt->execute([
                'application_id' => $application_id,
                'language_id' => (int)$lang,
            ]);
        }

        // Генерация логина и пароля
        $login = 'user' . $application_id;
        $password = bin2hex(random_bytes(4)); // 8-значный случайный пароль
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Сохранение пользователя в таблицу users
        $stmt = $pdo->prepare("INSERT INTO users (application_id, login, password_hash) VALUES (:app_id, :login, :password_hash)");
        $stmt->execute([
            'app_id' => $application_id,
            'login' => $login,
            'password_hash' => $password_hash
        ]);

        $pdo->commit();

        // Отображение пользователю
        setcookie('user_login', $login, $one_year, "/");
        setcookie('user_password', $password, 0, "/"); // Пароль показывается 1 раз

        // Выводим страницу с логином и паролем
        echo "
        <!DOCTYPE html>
        <html lang='ru'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Регистрация успешна</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    background-color: #f4f4f4;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .container {
                    background: white;
                    padding: 30px;
                    border-radius: 10px;
                    box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
                    text-align: center;
                    max-width: 400px;
                    width: 100%;
                }
                h2 {
                    color: #28a745;
                    margin-bottom: 20px;
                }
                p {
                    font-size: 16px;
                    color: #333;
                    margin: 10px 0;
                }
                .highlight {
                    font-weight: bold;
                    color: #007bff;
                    font-size: 18px;
                }
                .warning {
                    color: red;
                    font-size: 14px;
                    margin-top: 10px;
                }
                .button {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 15px;
                    background-color: #007bff;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    font-size: 16px;
                    transition: background 0.3s;
                }
                .button:hover {
                    background-color: #0056b3;
                }
            </style>
        </head>
        <body>

            <div class='container'>
                <h2>Регистрация прошла успешно!</h2>
                <p><strong>Ваш логин:</strong> <span class='highlight'>$login</span></p>
                <p><strong>Ваш пароль:</strong> <span class='highlight'>$password</span></p>
                <p class='warning'>⚠️ Сохраните эти данные! Пароль показывается только один раз.</p>
                <a href='login.php' class='button'>Перейти к авторизации</a>
            </div>

        </body>
        </html>
        ";
        exit(); // Останавливаем выполнение, чтобы пользователь увидел данные



    } catch (PDOException $e) {
        $pdo->rollBack();
        die('Ошибка при сохранении данных: ' . $e->getMessage());
    }
}

// Загружаем форму
include('form.php');
?>
