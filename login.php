<?php
session_start(); // Стартуем сессию
$login_cookie = $_COOKIE['user_login'] ?? '';
$password_cookie = $_COOKIE['user_password'] ?? '';

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

// Переменная для ошибок
$error = '';

// Если форма отправлена
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($login) || empty($password)) {
        $error = 'Введите логин и пароль.';
    } else {
        // Проверяем, существует ли пользователь в базе
        $stmt = $pdo->prepare("SELECT id, application_id, password_hash FROM users WHERE login = :login");
        $stmt->execute(['login' => $login]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            // Успешный вход: сохраняем данные в сессию
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['application_id'] = $user['application_id'];
            $_SESSION['login'] = $login;

            // Перенаправляем в `edit.php`
            header('Location: edit.php');
            exit();
        } else {
            $error = 'Неверный логин или пароль.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Вход в систему</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        h1 {
            text-align: center;
            color: #333;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            font-weight: bold;
        }
        input[type="text"], input[type="password"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        button {
            display: block;
            width: 100%;
            padding: 10px;
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        button:hover {
            background-color: #218838;
        }
        .error {
            color: red;
            font-size: 14px;
            margin-top: 10px;
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>Вход в систему</h1>

    <?php if (!empty($error)): ?>
        <p class="error"><?php echo htmlspecialchars($error); ?></p>
    <?php endif; ?>

    <form action="login.php" method="POST">
    <div class="form-group">
        <label for="login">Логин:</label>
        <input type="text" id="login" name="login" value="<?php echo htmlspecialchars($login_cookie); ?>" required>
    </div>

    <div class="form-group">
        <label for="password">Пароль:</label>
        <input type="password" id="password" name="password" value="<?php echo htmlspecialchars($password_cookie); ?>" required>
    </div>

    <button type="submit">Войти</button>
</form>

</body>
</html>
