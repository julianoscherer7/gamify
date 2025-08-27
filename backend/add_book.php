<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';
header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['user_id'])) {
    echo json_encode(['ok'=>false,'error'=>'not_authenticated']);
    exit;
}
$input = json_decode(file_get_contents('php://input'), true);
$book_id = (int)($input['book_id'] ?? 0);
if ($book_id <= 0) { echo json_encode(['ok'=>false,'error'=>'invalid_book']); exit; }

$uid = (int)$_SESSION['user_id'];

// check already read
$chk = $mysqli->prepare('SELECT id FROM user_books WHERE user_id=? AND book_id=? LIMIT 1');
$chk->bind_param('ii', $uid, $book_id);
$chk->execute();
if ($chk->get_result()->fetch_assoc()) {
    echo json_encode(['ok'=>false,'error'=>'already_done']);
    exit;
}

// get book points + genre
$bst = $mysqli->prepare('SELECT points, genre FROM books WHERE id=? LIMIT 1');
$bst->bind_param('i', $book_id);
$bst->execute();
$brow = $bst->get_result()->fetch_assoc();
if (!$brow) { echo json_encode(['ok'=>false,'error'=>'book_not_found']); exit; }
$pts = (int)$brow['points'];
$genre = $brow['genre'] ?? 'General';

// insert user_book
$ins = $mysqli->prepare('INSERT INTO user_books (user_id, book_id) VALUES (?,?)');
$ins->bind_param('ii', $uid, $book_id);
$ins->execute();

// update streak logic
// get last_read and streak
$ust = $mysqli->prepare('SELECT points, streak, last_read FROM users WHERE id=? LIMIT 1');
$ust->bind_param('i', $uid); $ust->execute();
$urow = $ust->get_result()->fetch_assoc();
$oldPoints = (int)$urow['points'];
$oldStreak = (int)$urow['streak'];
$lastRead = $urow['last_read']; // may be null

$today = new DateTime('today');
$yesterday = (new DateTime('today'))->modify('-1 day');

$newStreak = 1;
if ($lastRead) {
    $lr = new DateTime($lastRead);
    if ($lr->format('Y-m-d') === $yesterday->format('Y-m-d')) {
        $newStreak = $oldStreak + 1;
    } elseif ($lr->format('Y-m-d') === $today->format('Y-m-d')) {
        $newStreak = $oldStreak; // already read today (unlikely due to user_books unique)
    } else {
        $newStreak = 1;
    }
}

// add points and update user
$upd = $mysqli->prepare('UPDATE users SET points = points + ?, streak = ?, last_read = CURDATE() WHERE id = ?');
$upd->bind_param('iii', $pts, $newStreak, $uid);
$upd->execute();

// award achievements & update badge/rank
// helper functions local:
function unlock_achievement($mysqli, $uid, $code) {
    // find achievement id
    $q = $mysqli->prepare('SELECT id FROM achievements WHERE code=? LIMIT 1');
    $q->bind_param('s', $code); $q->execute();
    $r = $q->get_result()->fetch_assoc();
    if (!$r) return false;
    $aid = (int)$r['id'];
    // insert if not exists
    $ins = $mysqli->prepare('INSERT IGNORE INTO user_achievements (user_id, achievement_id) VALUES (?,?)');
    $ins->bind_param('ii', $uid, $aid); $ins->execute();
    return $ins->affected_rows > 0;
}

function update_rank_badge($mysqli, $uid) {
    // fetch points
    $s = $mysqli->prepare('SELECT points FROM users WHERE id=? LIMIT 1');
    $s->bind_param('i', $uid); $s->execute();
    $p = (int)$s->get_result()->fetch_assoc()['points'];
    $badge = 'Novato';
    if ($p >= 10000) $badge = 'Gênio';
    elseif ($p >= 2000) $badge = 'Sábio';
    elseif ($p >= 500) $badge = 'Estudioso';
    elseif ($p >= 100) $badge = 'Leitor';
    // update
    $u = $mysqli->prepare('UPDATE users SET badge=? WHERE id=?');
    $u->bind_param('si', $badge, $uid);
    $u->execute();
    return $badge;
}

// unlock "first_book"
unlock_achievement($mysqli, $uid, 'first_book');

// check total books read
$cnt = $mysqli->prepare('SELECT COUNT(*) AS c FROM user_books WHERE user_id=?');
$cnt->bind_param('i', $uid); $cnt->execute();
$totalBooks = (int)$cnt->get_result()->fetch_assoc()['c'];
if ($totalBooks >= 10) unlock_achievement($mysqli, $uid, 'ten_books');
if ($newStreak >= 30) unlock_achievement($mysqli, $uid, 'bookworm_30');

// genre master: count books user read in this genre
$genreCount = $mysqli->prepare('SELECT COUNT(*) AS c FROM user_books ub JOIN books b ON ub.book_id=b.id WHERE ub.user_id=? AND b.genre=?');
$genreCount->bind_param('is', $uid, $genre); $genreCount->execute();
$gcount = (int)$genreCount->get_result()->fetch_assoc()['c'];
if ($gcount >= 5) unlock_achievement($mysqli, $uid, 'genre_master');

// points milestone
$pointsTotalStmt = $mysqli->prepare('SELECT points FROM users WHERE id=?');
$pointsTotalStmt->bind_param('i', $uid); $pointsTotalStmt->execute();
$pointsTotal = (int)$pointsTotalStmt->get_result()->fetch_assoc()['points'];
if ($pointsTotal >= 1000) unlock_achievement($mysqli, $uid, 'points_1000');

// update badge/rank
$newBadge = update_rank_badge($mysqli, $uid);

// fetch newly unlocked achievements to return
$achStmt = $mysqli->prepare('SELECT a.code,a.title,a.description,a.icon,ua.unlocked_at FROM user_achievements ua JOIN achievements a ON ua.achievement_id=a.id WHERE ua.user_id=? ORDER BY ua.unlocked_at DESC');
$achStmt->bind_param('i', $uid); $achStmt->execute();
$achRes = $achStmt->get_result();
$achievements = [];
while ($ar = $achRes->fetch_assoc()) $achievements[] = $ar;

// return summary
echo json_encode([
    'ok'=>true,
    'points_added'=>$pts,
    'new_points'=>$pointsTotal,
    'new_streak'=>$newStreak,
    'badge'=>$newBadge,
    'total_books'=>$totalBooks,
    'achievements'=>$achievements
]);
exit;
