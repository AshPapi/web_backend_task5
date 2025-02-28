<?php
// Убедимся, что переменная $values определена
if (!isset($values) || !is_array($values)) {
    $values = [];
}

// Загружаем сохранённые данные из куки
$fields = ['full_name', 'phone', 'email', 'birth_date', 'gender', 'languages', 'biography', 'contract_accepted'];
foreach ($fields as $field) {
    $values[$field] = $_COOKIE[$field . '_value'] ?? ($values[$field] ?? '');
}

// Если данные языков программирования сохранены, преобразуем их в массив
if (!empty($values['languages']) && is_string($values['languages'])) {
    $values['languages'] = explode(',', $values['languages']);
} elseif (!is_array($values['languages'])) {
    $values['languages'] = [];
}

// Загружаем ошибки из куки (если есть)
$errors = [];
foreach ($fields as $field) {
    if (isset($_COOKIE[$field . '_error'])) {
        $errors[$field] = $_COOKIE[$field . '_error'];
        setcookie($field . '_error', '', time() - 3600, "/"); // Удаляем ошибку после загрузки
    }
}

// Проверяем сообщение об успешном сохранении
$success_message = '';
if (isset($_COOKIE['save'])) {
    $success_message = 'Данные успешно сохранены!';
    setcookie('save', '', time() - 3600, "/");
}

// Проверяем наличие логина и пароля для отображения
$new_login = $_COOKIE['new_login'] ?? '';
$new_password = $_COOKIE['new_password'] ?? '';
if ($new_login && $new_password) {
    $success_message .= "<br>Ваш логин: $new_login<br>Ваш пароль: $new_password<br>Сохраните эти данные, они больше не будут отображаться!";
    setcookie('new_login', '', time() - 3600, "/");
    setcookie('new_password', '', time() - 3600, "/");
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Форма заявки</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 20px auto;
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
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="text"],
        input[type="tel"],
        input[type="email"],
        input[type="date"],
        textarea,
        select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        textarea {
            resize: vertical;
            height: 100px;
        }

        .radio-group, .checkbox-group {
            margin: 10px 0;
        }

        select[multiple] {
            height: 150px;
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
            margin-top: 5px;
        }

        .success {
            color: green;
            font-size: 14px;
            margin-top: 5px;
            text-align: center;
        }

        .auth-link {
            text-align: center;
            margin-top: 20px;
        }

        .auth-link a {
            color: #007bff;
            text-decoration: none;
        }

        .auth-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <h1>Форма заявки</h1>

    <?php if (!empty($success_message)): ?>
        <p class="success"><?php echo htmlspecialchars($success_message); ?></p>
    <?php endif; ?>

    <form action="index.php" method="POST">
        <div class="form-group">
            <label for="full_name">ФИО:</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($values['full_name']); ?>" required>
            <?php if (isset($errors['full_name'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['full_name']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="phone">Телефон:</label>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($values['phone']); ?>" required>
            <?php if (isset($errors['phone'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['phone']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($values['email']); ?>" required>
            <?php if (isset($errors['email'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['email']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="birth_date">Дата рождения:</label>
            <input type="date" id="birth_date" name="birth_date" value="<?php echo htmlspecialchars($values['birth_date']); ?>" required>
            <?php if (isset($errors['birth_date'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['birth_date']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Пол:</label>
            <div class="radio-group">
                <label><input type="radio" name="gender" value="male" <?php echo ($values['gender'] === 'male') ? 'checked' : ''; ?> required> Мужской</label>
                <label><input type="radio" name="gender" value="female" <?php echo ($values['gender'] === 'female') ? 'checked' : ''; ?>> Женский</label>
            </div>
            <?php if (isset($errors['gender'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['gender']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="languages">Любимый язык программирования:</label>
            <select id="languages" name="languages[]" multiple required>
                <?php
                $programming_languages = [
                    1 => 'Pascal', 2 => 'C', 3 => 'C++', 4 => 'JavaScript', 5 => 'PHP', 6 => 'Python',
                    7 => 'Java', 8 => 'Haskell', 9 => 'Clojure', 10 => 'Prolog', 11 => 'Scala', 12 => 'Go'
                ];
                foreach ($programming_languages as $id => $lang) {
                    $selected = in_array((string)$id, $values['languages']) ? 'selected' : '';
                    echo "<option value=\"$id\" $selected>$lang</option>";
                }
                ?>
            </select>
            <?php if (isset($errors['languages'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['languages']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="biography">Биография:</label>
            <textarea id="biography" name="biography" required><?php echo htmlspecialchars($values['biography']); ?></textarea>
            <?php if (isset($errors['biography'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['biography']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <div class="checkbox-group">
                <label>
                    <input type="checkbox" name="contract_accepted" <?php echo ($values['contract_accepted'] == 1) ? 'checked' : ''; ?> required>
                    С контрактом ознакомлен(а)
                </label>
            </div>
            <?php if (isset($errors['contract_accepted'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['contract_accepted']); ?></p>
            <?php endif; ?>
        </div>

        <button type="submit"><?php echo $isAuthorized ? 'Сохранить изменения' : 'Сохранить'; ?></button>
    </form>

    <?php if (!$isAuthorized): ?>
        <div class="auth-link">
            <a href="login.php">Войти для редактирования данных</a>
        </div>
    <?php else: ?>
        <div class="auth-link">
            <a href="logout.php">Выйти</a>
        </div>
    <?php endif; ?>
</body>
</html>
