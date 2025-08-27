<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
header('Content-Type: application/json; charset=utf-8');

$books = [];
$stmt = $mysqli->prepare('SELECT id, title, description, genre, points FROM books ORDER BY title ASC');
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $row['completed'] = false;
    $row['completed_at'] = null;
    $books[$row['id']] = $row;
}

if (!empty($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $mysqli->prepare('SELECT ub.book_id, ub.completed_at FROM user_books ub WHERE ub.user_id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $cres = $stmt->get_result();
    while ($c = $cres->fetch_assoc()) {
        $bid = (int)$c['book_id'];
        if (isset($books[$bid])) {
            $books[$bid]['completed'] = true;
            $books[$bid]['completed_at'] = $c['completed_at'];
        }
    }
}

echo json_encode(array_values($books));
