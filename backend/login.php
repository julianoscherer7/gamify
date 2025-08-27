<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    header('Location: ../login.html?error=missing');
    exit;
}

$stmt = $mysqli->prepare('SELECT id, username, password FROM users WHERE email = ? LIMIT 1');
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
if ($user = $res->fetch_assoc()) {
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = (int)$user['id'];
        $_SESSION['username'] = $user['username'];
        header('Location: ../dashboard.html');
        exit;
    }
}
header('Location: ../login.html?error=invalid');
exit;
