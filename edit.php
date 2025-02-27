<?php
session_start();

// Устанавливаем кодировку страницы
header('Content-Type: text/html; charset=UTF-8');

// Проверяем авторизацию пользователя
if (!isset($_SESSION['user_id']) || !isset($_SESSION['application_id'])) {
    header('Location: login.php');
    exit();
}

// Подключение к базе данных
$dsn = 'mysql:host=localhost;dbname=u68648;charset=utf8';
$username = 'u68648';
$password = '7759086';

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Ошибка подключения к БД: ' . $e->getMessage());
}

// Получаем ID заявки пользователя
$application_id = $_SESSION['application_id'];

// Загружаем текущие данные пользователя
$stmt = $pdo->prepare("SELECT * FROM applications WHERE id = :application_id");
$stmt->execute(['application_id' => $application_id]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

// Загружаем выбранные языки программирования
$stmt = $pdo->prepare("SELECT language_id FROM application_languages WHERE application_id = :application_id");
$stmt->execute(['application_id' => $application_id]);
$selectedLanguages = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Список всех возможных языков программирования
$allLanguages = [
    1 => 'Python', 2 => 'JavaScript', 3 => 'Java', 4 => 'C#',
    5 => 'C++', 6 => 'PHP', 7 => 'Swift', 8 => 'Kotlin',
    9 => 'Go', 10 => 'Rust', 11 => 'Ruby', 12 => 'TypeScript'
];

$errors = [];
$success = '';
$values = $userData; // Инициализируем значения по умолчанию из БД

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    // Если нет ошибок, обновляем данные
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Обновляем данные в applications
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
                'application_id' => $application_id
            ]);

            // Удаляем старые языки программирования и вставляем новые
            $stmt = $pdo->prepare("DELETE FROM application_languages WHERE application_id = :application_id");
            $stmt->execute(['application_id' => $application_id]);

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
            $success = 'Данные успешно обновлены!';
            // Обновляем значения после успешного сохранения
            $values = array_merge($values, $_POST);
            $values['contract_accepted'] = $contract_accepted;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors['database'] = 'Ошибка при обновлении данных: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Редактирование данных</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0px 4px 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            width: 100%;
        }

        h1 {
            text-align: center;
            font-size: 24px;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #555;
        }

        input[type="text"], 
        input[type="tel"], 
        input[type="email"], 
        input[type="date"], 
        textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 16px;
        }

        textarea {
            resize: none;
            height: 100px;
        }

        button {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 18px;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background-color: #0056b3;
        }

        .success {
            color: green;
            text-align: center;
            margin-bottom: 15px;
        }

        .error {
            color: red;
            font-size: 14px;
            margin-top: 5px;
        }

        .languages-group {
            margin-top: 5px;
        }

        .languages-group label {
            display: inline-block;
            margin-right: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
    <h1>Редактирование данных</h1>

    <?php if (!empty($success)): ?>
        <p class="success"><?php echo htmlspecialchars($success); ?></p>
    <?php endif; ?>

    <?php if (!empty($errors['database'])): ?>
        <p class="error"><?php echo htmlspecialchars($errors['database']); ?></p>
    <?php endif; ?>

    <form action="edit.php" method="POST">
        <div class="form-group">
            <label for="full_name">ФИО:</label>
            <input type="text" 
                   id="full_name" 
                   name="full_name" 
                   value="<?php echo htmlspecialchars($values['full_name'] ?? $userData['full_name']); ?>" 
                   required>
            <?php if (!empty($errors['full_name'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['full_name']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="phone">Телефон:</label>
            <input type="tel" 
                   id="phone" 
                   name="phone" 
                   value="<?php echo htmlspecialchars($values['phone'] ?? $userData['phone']); ?>" 
                   required>
            <?php if (!empty($errors['phone'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['phone']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="email">E-mail:</label>
            <input type="email" 
                   id="email" 
                   name="email" 
                   value="<?php echo htmlspecialchars($values['email'] ?? $userData['email']); ?>" 
                   required>
            <?php if (!empty($errors['email'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['email']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="birth_date">Дата рождения:</label>
            <input type="date" 
                   id="birth_date" 
                   name="birth_date" 
                   value="<?php echo htmlspecialchars($values['birth_date'] ?? $userData['birth_date']); ?>" 
                   required>
            <?php if (!empty($errors['birth_date'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['birth_date']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Пол:</label>
            <label>
                <input type="radio" 
                       name="gender" 
                       value="male" 
                       <?php echo ($values['gender'] ?? $userData['gender']) === 'male' ? 'checked' : ''; ?> 
                       required>
                Мужской
            </label>
            <label>
                <input type="radio" 
                       name="gender" 
                       value="female" 
                       <?php echo ($values['gender'] ?? $userData['gender']) === 'female' ? 'checked' : ''; ?>>
                Женский
            </label>
            <?php if (!empty($errors['gender'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['gender']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>Языки программирования:</label>
            <div class="languages-group">
                <?php foreach ($allLanguages as $id => $language): ?>
                    <label>
                        <input type="checkbox" 
                               name="languages[]" 
                               value="<?php echo $id; ?>"
                               <?php 
                               $currentLanguages = $values['languages'] ?? $selectedLanguages;
                               echo in_array($id, $currentLanguages) ? 'checked' : ''; 
                               ?>>
                        <?php echo htmlspecialchars($language); ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <?php if (!empty($errors['languages'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['languages']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label for="biography">Биография:</label>
            <textarea id="biography" 
                      name="biography" 
                      required><?php echo htmlspecialchars($values['biography'] ?? $userData['biography']); ?></textarea>
            <?php if (!empty($errors['biography'])): ?>
                <p class="error"><?php echo htmlspecialchars($errors['biography']); ?></p>
            <?php endif; ?>
        </div>

        <div class="form-group">
            <label>
                <input type="checkbox" 
                       name="contract_accepted" 
                       value="1"
                       <?php echo ($values['contract_accepted'] ?? $userData['contract_accepted']) ? 'checked' : ''; ?>>
                Согласен с условиями контракта
            </label>
        </div>

        <button type="submit">Сохранить изменения</button>
    </form>
</div>
</body>
</html>