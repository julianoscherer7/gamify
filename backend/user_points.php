<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) { echo json_encode(['ok'=>false]); exit; }
$uid = (int)$_SESSION['user_id'];

$stmt = $mysqli->prepare('SELECT id,username,points,streak,badge,last_read,created_at FROM users WHERE id=? LIMIT 1');
$stmt->bind_param('i', $uid);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
if (!$user) { echo json_encode(['ok'=>false]); exit; }

// rank thresholds definition
$ranks = [
  ['code'=>'Novato','min'=>0],
  ['code'=>'Leitor','min'=>100],
  ['code'=>'Estudioso','min'=>500],
  ['code'=>'Sábio','min'=>2000],
  ['code'=>'Gênio','min'=>10000]
];
// find next rank
$currentPoints = (int)$user['points'];
$next = null;
foreach ($ranks as $i => $r) {
  if ($currentPoints < $r['min']) { $next = $r; break; }
}
if (!$next) {
  // user already >= last threshold: no next
  $next = ['code'=>'Top','min'=> $currentPoints];
}

// achievements unlocked
$achStmt = $mysqli->prepare('SELECT a.code,a.title,a.description,a.icon,ua.unlocked_at FROM user_achievements ua JOIN achievements a ON ua.achievement_id=a.id WHERE ua.user_id=? ORDER BY ua.unlocked_at DESC');
$achStmt->bind_param('i', $uid); $achStmt->execute();
$ach = $achStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// total books read
$cnt = $mysqli->prepare('SELECT COUNT(*) as c FROM user_books WHERE user_id=?');
$cnt->bind_param('i', $uid); $cnt->execute();
$totalBooks = (int)$cnt->get_result()->fetch_assoc()['c'];

// return
echo json_encode([
  'ok'=>true,
  'user'=>[
    'id'=>(int)$user['id'],
    'username'=>$user['username'],
    'points'=>(int)$user['points'],
    'streak'=>(int)$user['streak'],
    'badge'=>$user['badge'],
    'last_read'=>$user['last_read'] ?? null,
    'created_at'=>$user['created_at'],
    'total_books'=>$totalBooks
  ],
  'next_rank'=>$next,
  'achievements'=>$ach
]);
