<?php
// POST only: add a catalog movie to MY list, remove it, or save my own status/rating/review. Never touches the catalog itself.
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/movie_form.php';

require_login();
$uid = current_user_id();

$back = (string)($_POST['back'] ?? '');
if (!preg_match('/^(catalog|movies|index|watchlist|folder|view_movie)\.php(\?[A-Za-z0-9_=&%.+\-]*)?$/', $back)) $back = 'catalog.php';

$id  = filter_var($_POST['movie_id'] ?? null, FILTER_VALIDATE_INT);
$act = (string)($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) redirect('catalog.php');
if (!csrf_valid()) { flash('Your session expired. Please try again.', 'error'); redirect($back); }

$c = $conn->prepare('SELECT 1 FROM movies WHERE movie_id = ?');
$c->bind_param('i', $id); $c->execute();
if (!$c->get_result()->num_rows) { flash('That movie is no longer in the catalog.', 'error'); redirect('catalog.php'); }

if ($act === 'add') {
    $s = $conn->prepare('INSERT IGNORE INTO user_movies (user_id, movie_id) VALUES (?, ?)');
    $s->bind_param('ii', $uid, $id); $s->execute();
    flash($s->affected_rows ? 'Added to your list.' : 'That movie is already on your list.');
} elseif ($act === 'remove') {
    $s = $conn->prepare('DELETE FROM user_movies WHERE user_id = ? AND movie_id = ?');
    $s->bind_param('ii', $uid, $id); $s->execute();
    $s = $conn->prepare('DELETE cm FROM collection_movies cm JOIN collections c ON c.collection_id = cm.collection_id WHERE c.user_id = ? AND cm.movie_id = ?');
    $s->bind_param('ii', $uid, $id); $s->execute();
    flash('Removed from your list.');
} elseif ($act === 'track') {
    $t = track_from_post();
    $errs = track_validate($t);
    if ($errs) { flash(reset($errs), 'error'); redirect($back); }
    $rating = $t['user_rating'] === '' ? null : (float)$t['user_rating'];
    $review = $t['review'] === '' ? null : $t['review'];
    $cnt = (int)$t['watch_count']; $fav = (int)$t['favorite'];
    $s = $conn->prepare('UPDATE user_movies SET watch_status = ?, priority = ?, watch_count = ?, user_rating = ?, review = ?, favorite = ? WHERE user_id = ? AND movie_id = ?');
    $s->bind_param('ssidsiii', $t['watch_status'], $t['priority'], $cnt, $rating, $review, $fav, $uid, $id);
    $s->execute();
    flash('Your details were saved.');
}
redirect($back);