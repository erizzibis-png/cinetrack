<?php
// POST only: delete a folder, or remove one movie from a folder. Movies themselves are never deleted here.
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/collections.php';
require_login();
$uid = current_user_id();
ensure_collections_schema($conn);

$id  = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
$act = (string)($_POST['action'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) redirect('watchlist.php');
if (!csrf_valid()) { flash('Your session expired. Please try again.', 'error'); redirect('folder.php?id=' . $id); }

if ($act === 'delete_folder') {
    $s = $conn->prepare('DELETE FROM collections WHERE collection_id = ? AND user_id = ?');
    $s->bind_param('ii', $id, $uid); $s->execute();
    flash($s->affected_rows ? 'Folder deleted.' : 'That folder was already removed.');
    redirect('watchlist.php');
}
if ($act === 'remove') {
    $mid = filter_var($_POST['movie_id'] ?? null, FILTER_VALIDATE_INT);
    if ($mid) {
        $s = $conn->prepare('DELETE cm FROM collection_movies cm JOIN collections c ON c.collection_id = cm.collection_id WHERE cm.collection_id = ? AND cm.movie_id = ? AND c.user_id = ?');
        $s->bind_param('iii', $id, $mid, $uid); $s->execute();
        flash('Removed from the folder.');
    }
}
redirect('folder.php?id=' . $id);
