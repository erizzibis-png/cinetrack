<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('movies.php');
if (!csrf_valid()) { flash('Your session expired.', 'error'); redirect('movies.php'); }

$ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['movie_ids'] ?? [])))));
$act = (string)($_POST['action'] ?? '');
$back = (string)($_POST['back'] ?? 'movies.php');
if (!preg_match('#^movies\.php#', $back)) $back = 'movies.php';
if (!$ids) { flash('Nothing selected.', 'error'); redirect($back); }

$uid = current_user_id();
$in = implode(',', array_fill(0, count($ids), '?'));
$types = 'i' . str_repeat('i', count($ids));
$params = array_merge([$uid], $ids);

if ($act === 'watched') {
    $s = $conn->prepare("UPDATE movies SET watch_status = 'Watched', watch_count = watch_count + 1 WHERE user_id = ? AND movie_id IN ($in)");
    $s->bind_param($types, ...$params); $s->execute();
    flash($s->affected_rows . ' movie(s) marked as watched.');
} elseif ($act === 'to-watch') {
    $s = $conn->prepare("UPDATE movies SET watch_status = 'To Watch' WHERE user_id = ? AND movie_id IN ($in)");
    $s->bind_param($types, ...$params); $s->execute();
    flash($s->affected_rows . ' movie(s) added to watchlist.');
} elseif ($act === 'favorite') {
    $s = $conn->prepare("UPDATE movies SET favorite = 1 WHERE user_id = ? AND movie_id IN ($in)");
    $s->bind_param($types, ...$params); $s->execute();
    flash($s->affected_rows . ' movie(s) favorited.');
} elseif ($act === 'delete') {
    $s = $conn->prepare("DELETE FROM movies WHERE user_id = ? AND movie_id IN ($in)");
    $s->bind_param($types, ...$params); $s->execute();
    flash($s->affected_rows . ' movie(s) deleted.');
}

redirect($back);