<?php
session_start(); // Начинаем сессию

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

// Функция для генерации случайного логина
function generateLogin($email) {
    $emailParts = explode('@', $email);
    return $emailParts[0] . rand(100, 999);
}

// Функция для генерации случайного пароля
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// Проверка авторизации
$isAuthorized = isset($_SESSION['user_id']);
$application_id = $isAuthorized ? $_SESSION['application_id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Массивы для ошибок и значений
    $errors = [];
    $values = [];

    // Валидация полей (оставляем без изменений)
    $full_name = trim($_POST['full_name'] ?? '');
    $values['full_name'] = $full_name;
    if (!preg_match('/^[а-яА-ЯёЁa-zA-Z\s-]+$/u', $full_name) || iconv_strlen($full_name, 'UTF-8') > 150) {
        $errors['full_name'] = 'ФИО должно содержать только буквы, пробелы и дефисы, не более 150 символов.';
    }

    $phone = trim($_POST['phone'] ?? '');
    $values['phone'] = $phone;
    if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
        $errors['phone'] = 'Телефон должен содержать от 10 до 15 цифр, возможно с префиксом "+".';
    }

    $email = trim($_POST['email'] ?? '');
    $values['email'] = $email;
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Некорректный формат e-mail.';
    }

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

    $gender = $_POST['gender'] ?? '';
    $values['gender'] = $gender;
    if (!in_array($gender, ['male', 'female'])) {
        $errors['gender'] = 'Некорректное значение пола.';
    }

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

    $biography = trim($_POST['biography'] ?? '');
    $values['biography'] = $biography;
    if (empty($biography)) {
        $errors['biography'] = 'Поле биографии не может быть пустым.';
    }

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

        if ($isAuthorized) {
            // Обновление существующих данных
            $stmt = $pdo->prepare("
                UPDATE applications 
                SET full_name = :full_name, phone = :phone, email = :email, birth_date = :birth_date,
                    gender = :gender, biography = :biography, contract_accepted = :contract_accepted
                WHERE id = :application_id
            ");
            $stmt->execute([
                'full_name' => $full_name,
                'phone' => $phone,
                'email' => $email,
                'birth_date' => $birth_date,
                'gender' => $gender,
                'biography' => $biography,
                'contract_accepted' => $contract_accepted,
                'application_id' => $application_id,
            ]);

            // Удаляем старые языки программирования
            $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = :application_id");
            $stmt->execute(['application_id' => $application_id]);
        } else {
            // Вставляем новые данные в applications
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

            // Генерация логина и пароля
            $login = generateLogin($email);
            $plain_password = generatePassword();
            $password_hash = password_hash($plain_password, PASSWORD_DEFAULT);

            // Сохранение учетных данных
            $stmt = $pdo->prepare("
                INSERT INTO users (application_id, login, password_hash)
                VALUES (:application_id, :login, :password_hash)
            ");
            $stmt->execute([
                'application_id' => $application_id,
                'login' => $login,
                'password_hash' => $password_hash,
            ]);

            // Сохраняем логин и пароль в куки для отображения
            setcookie('new_login', $login, 0, "/");
            setcookie('new_password', $plain_password, 0, "/");
        }

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

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        die('Ошибка при сохранении данных: ' . $e->getMessage());
    }

    // Сохраняем успешные данные в Cookies
    foreach ($values as $field => $value) {
        setcookie($field . '_value', is_array($value) ? implode(',', $value) : $value, $one_year, "/");
    }

    // Удаляем ошибки и устанавливаем сообщение об успешной записи
    foreach ($errors as $field => $_) {
        setcookie($field . '_error', '', time() - 3600, "/");
    }
    setcookie('save', '1', $one_year, "/");

    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Загрузка данных для авторизованного пользователя
if ($isAuthorized) {
    try {
        $stmt = $pdo->prepare("
            SELECT full_name, phone, email, birth_date, gender, biography, contract_accepted
            FROM applications
            WHERE id = :application_id
        ");
        $stmt->execute(['application_id' => $application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($application) {
            $values = $application;
            $values['contract_accepted'] = (int)$values['contract_accepted'];

            // Загрузка языков программирования
            $stmt = $pdo->prepare("
                SELECT language_id
                FROM application_languages
                WHERE application_id = :application_id
            ");
            $stmt->execute(['application_id' => $application_id]);
            $values['languages'] = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'language_id');
        }
    } catch (PDOException $e) {
        die('Ошибка при загрузке данных: ' . $e->getMessage());
    }
}

// Загружаем форму
include('form.php');
