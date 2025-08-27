<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$mode = $_GET['mode'] ?? 'overall'; // overall | weekly | monthly | genre
$limit = intval($_GET['limit'] ?? 10);
if ($limit <= 0) $limit = 10;

if ($mode === 'overall') {
    $stmt = $mysqli->prepare('SELECT username, points FROM users ORDER BY points DESC, created_at ASC LIMIT ?');
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($r = $res->fetch_assoc()) $list[] = $r;
    echo json_encode(['ok'=>true,'type'=>'overall','leaderboard'=>$list]);
    exit;
}

if ($mode === 'weekly' || $mode === 'monthly') {
    if ($mode === 'weekly') {
        $since = date('Y-m-d H:i:s', strtotime('-7 days'));
    } else {
        $since = date('Y-m-d H:i:s', strtotime('-30 days'));
    }
    // sum points from books read in interval
    $sql = "SELECT u.username, SUM(b.points) as pts FROM user_books ub JOIN users u ON ub.user_id=u.id JOIN books b ON ub.book_id=b.id WHERE ub.completed_at >= ? GROUP BY ub.user_id ORDER BY pts DESC LIMIT ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('si', $since, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($r = $res->fetch_assoc()) $list[] = ['username'=>$r['username'],'points'=> (int)$r['pts']];
    echo json_encode(['ok'=>true,'type'=>$mode,'leaderboard'=>$list]);
    exit;
}

if ($mode === 'genre') {
    $genre = $_GET['genre'] ?? '';
    if (!$genre) { echo json_encode(['ok'=>false,'error'=>'missing_genre']); exit; }
    // top users by points from this genre overall
    $sql = "SELECT u.username, SUM(b.points) as pts FROM user_books ub JOIN users u ON ub.user_id=u.id JOIN books b ON ub.book_id=b.id WHERE b.genre = ? GROUP BY ub.user_id ORDER BY pts DESC LIMIT ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('si', $genre, $limit);
    $stmt->execute();
    $res = $stmt->get_result();
    $list = [];
    while ($r = $res->fetch_assoc()) $list[] = ['username'=>$r['username'],'points'=> (int)$r['pts']];
    echo json_encode(['ok'=>true,'type'=>'genre','genre'=>$genre,'leaderboard'=>$list]);
    exit;
}

echo json_encode(['ok'=>false,'error'=>'invalid_mode']);
