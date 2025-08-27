<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$username || !$email || !$password) {
    header('Location: ../register.html?error=missing');
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    $stmt = $mysqli->prepare('INSERT INTO users (username,email,password) VALUES (?,?,?)');
    $stmt->bind_param('sss', $username, $email, $hash);
    $stmt->execute();
    $id = $mysqli->insert_id;
    $_SESSION['user_id'] = $id;
    $_SESSION['username'] = $username;
    header('Location: ../dashboard.html');
    exit;
} catch (mysqli_sql_exception $e) {
    header('Location: ../register.html?error=duplicate');
    exit;
}
