<?php
// Handles the one-click actions (favorite, watched +1, rate). POST only, CSRF-protected, owner only.
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();
$uid = current_user_id();

$back = (string)($_POST['back'] ?? '');
if (!preg_match('/^(movies|index|watchlist|folder)\.php(\?[A-Za-z0-9_=&%.+\-]*)?$/', $back)) $back = 'movies.php';

$id  = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$act = (string)($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) redirect('movies.php');
if (!csrf_valid()) { flash('Your session expired. Please try again.', 'error'); redirect($back); }

if ($act === 'favorite') {
    $s = $conn->prepare('UPDATE movies SET favorite = 1 - favorite WHERE movie_id = ? AND user_id = ?');
    $s->bind_param('ii', $id, $uid); $s->execute();
    flash('Favorite updated.');
} elseif ($act === 'watched') {
    $s = $conn->prepare("UPDATE movies SET watch_status = 'Watched', watch_count = watch_count + 1 WHERE movie_id = ? AND user_id = ?");
    $s->bind_param('ii', $id, $uid); $s->execute();
    flash('Logged as watched.');
} elseif ($act === 'rate') {
    $v = (string)($_POST['value'] ?? '');
    if ($v === 'none') {
        $s = $conn->prepare('UPDATE movies SET user_rating = NULL WHERE movie_id = ? AND user_id = ?');
        $s->bind_param('ii', $id, $uid); $s->execute();
        flash('Rating cleared.');
    } elseif (ctype_digit($v) && (int)$v >= 1 && (int)$v <= 10) {
        $r = (float)$v;
        $s = $conn->prepare('UPDATE movies SET user_rating = ? WHERE movie_id = ? AND user_id = ?');
        $s->bind_param('dii', $r, $id, $uid); $s->execute();
        flash('Rating saved.');
    }
}
redirect($back);
