<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
header('Content-Type: application/json; charset=utf-8');

$all = [];
$res = $mysqli->query('SELECT id,code,title,description,icon FROM achievements ORDER BY id ASC');
while ($r = $res->fetch_assoc()) $all[] = $r;

$userUnlocked = [];
if (!empty($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $stmt = $mysqli->prepare('SELECT a.code, ua.unlocked_at FROM user_achievements ua JOIN achievements a ON ua.achievement_id = a.id WHERE ua.user_id = ?');
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $cres = $stmt->get_result();
    while ($c = $cres->fetch_assoc()) $userUnlocked[$c['code']] = $c['unlocked_at'];
}

echo json_encode(['ok'=>true,'achievements'=>$all,'unlocked'=>$userUnlocked]);
