<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/collections.php';
require_once __DIR__ . '/includes/picker.php';
require_login();
$uid = current_user_id();
ensure_collections_schema($conn);

$fid = (int)filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$s = $conn->prepare('SELECT * FROM collections WHERE collection_id = ? AND user_id = ?');
$s->bind_param('ii', $fid, $uid); $s->execute();
$f = $s->get_result()->fetch_assoc();
if (!$f) { flash('That folder no longer exists.', 'error'); redirect('watchlist.php'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['movie_ids'] ?? [])))));
    if (!csrf_valid()) {
        flash('Your session expired. Please try again.', 'error');
    } elseif (!$ids) {
        flash('Tick at least one movie to add.', 'error');
    } else {
        $ins = $conn->prepare('INSERT IGNORE INTO collection_movies (collection_id, movie_id) SELECT ?, movie_id FROM movies WHERE movie_id = ? AND user_id = ?');
        $added = 0;
        foreach ($ids as $mid) { $ins->bind_param('iii', $fid, $mid, $uid); $ins->execute(); $added += max(0, $ins->affected_rows); }
        flash($added ? "Added $added " . ($added === 1 ? 'movie' : 'movies') . ' to ' . $f['name'] . '.' : 'Those movies were already in the folder.');
        redirect('folder.php?id=' . $fid);
    }
    redirect('folder_add.php?id=' . $fid);
}

$ms = $conn->prepare('SELECT * FROM movies WHERE user_id = ? ORDER BY title');
$ms->bind_param('i', $uid); $ms->execute();
$movies = $ms->get_result()->fetch_all(MYSQLI_ASSOC);
$s = $conn->prepare('SELECT movie_id FROM collection_movies WHERE collection_id = ?');
$s->bind_param('i', $fid); $s->execute();
$inFolder = array_map('intval', array_column($s->get_result()->fetch_all(MYSQLI_NUM), 0));

$page_title = 'Add movies to ' . $f['name'];
$active = 'watchlist';
$extra_js = ['assets/js/collections.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head folder-head" style="--fc:<?= folder_color($f['color']) ?>">
    <div class="folder-head-main">
        <span class="folder-tile big"><?= icon($f['icon'], 30) ?></span>
        <div>
            <span class="eyebrow">Add movies</span>
            <h1>Add Movies to <?= e($f['name']) ?></h1>
            <p class="muted">Search, tick the movies you want, then add them all at once.</p>
        </div>
    </div>
    <a class="btn" href="folder.php?id=<?= $fid ?>"><?= icon('chevron-left', 16) ?> Back to folder</a>
</div>
<?php render_movie_picker($movies, $inFolder, $f['name']); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
