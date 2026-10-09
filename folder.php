<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/collections.php';
require_login();
$uid = current_user_id();
ensure_collections_schema($conn);

$idParam = (string)($_GET['id'] ?? '');
$builtin = $idParam === 'to-watch';
if ($builtin) {
    $ms = $conn->prepare("SELECT * FROM movies WHERE user_id = ? AND watch_status = 'To Watch' ORDER BY FIELD(priority,'High','Medium','Low'), title");
    $ms->bind_param('i', $uid); $ms->execute();
    $movies = $ms->get_result()->fetch_all(MYSQLI_ASSOC);
    $f = builtin_folder(count($movies));
    $fid = 0;
} else {
    $fid = (int)filter_var($idParam, FILTER_VALIDATE_INT);
    $s = $conn->prepare('SELECT * FROM collections WHERE collection_id = ? AND user_id = ?');
    $s->bind_param('ii', $fid, $uid);
    $s->execute();
    $f = $s->get_result()->fetch_assoc();
    if (!$f) { flash('That folder no longer exists.', 'error'); redirect('watchlist.php'); }
    $s = $conn->prepare('SELECT m.* FROM movies m JOIN collection_movies cm ON cm.movie_id = m.movie_id WHERE cm.collection_id = ? ORDER BY cm.added_at DESC, m.title');
    $s->bind_param('i', $fid);
    $s->execute();
    $movies = $s->get_result()->fetch_all(MYSQLI_ASSOC);
}
$n = count($movies);
$back = 'folder.php?id=' . ($builtin ? 'to-watch' : $fid);
$scope = $builtin ? 'watchlist' : 'folder:' . $fid;

$page_title = $f['name'];
$active = 'watchlist';
$extra_js = ['assets/js/collections.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head folder-head" style="--fc:<?= folder_color($f['color']) ?>">
    <div class="folder-head-main">
        <span class="folder-tile big"><?= icon($f['icon'], 30) ?></span>
        <div>
            <span class="eyebrow"><?= $builtin ? 'Built-in folder' : 'Folder' ?></span>
            <h1><?= e($f['name']) ?></h1>
            <p class="muted"><?= $f['description'] ? e($f['description']) . ' &middot; ' : '' ?><?= $n ?> <?= $n === 1 ? 'movie' : 'movies' ?></p>
        </div>
    </div>
    <div class="head-actions">
        <a class="btn" href="watchlist.php"><?= icon('chevron-left', 16) ?> Watchlist</a>
        <?php if ($n > 0): ?><button class="btn" type="button" data-shuffle="<?= e($scope) ?>"><?= icon('shuffle', 16) ?> Shuffle</button><?php endif; ?>
        <?php if ($builtin): ?>
            <a class="btn btn-primary" href="watchlist_add.php"><?= icon('plus', 16) ?> Add to Watchlist</a>
        <?php else: ?>
            <a class="btn" href="folder_edit.php?id=<?= $fid ?>"><?= icon('edit', 16) ?> Edit folder</a>
            <button class="btn btn-danger" type="button" data-modal-open="#delete-folder"><?= icon('trash', 16) ?> Delete</button>
            <a class="btn btn-primary" href="folder_add.php?id=<?= $fid ?>"><?= icon('plus', 16) ?> Add Movies to <?= e($f['name']) ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if ($movies): ?>
<div class="grid grid-movies">
    <?php foreach ($movies as $m): ?>
        <?php if ($builtin): echo movie_card($m, $back, true); else: ?>
        <div class="folder-item">
            <?= movie_card($m, $back) ?>
            <form method="post" action="folder_action.php">
                <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?= $fid ?>"><input type="hidden" name="movie_id" value="<?= (int)$m['movie_id'] ?>">
                <button class="btn btn-sm btn-ghost-danger" type="submit"><?= icon('x', 14) ?> Remove from folder</button>
            </form>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="card empty">
    <h2>This folder is empty</h2>
    <p><?= $builtin ? 'Movies with status To Watch appear here automatically.' : 'Add movies from your collection. You can tick many at once.' ?></p>
    <a class="btn btn-primary" href="<?= $builtin ? 'watchlist_add.php' : 'folder_add.php?id=' . $fid ?>"><?= $builtin ? 'Add to Watchlist' : 'Add Movies to ' . e($f['name']) ?></a>
</div>
<?php endif; ?>

<?php if (!$builtin): ?>
<dialog id="delete-folder">
    <h2>Delete this folder?</h2>
    <p class="muted">&ldquo;<?= e($f['name']) ?>&rdquo; will be removed. Your movies stay in your collection.</p>
    <form method="post" action="folder_action.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete_folder"><input type="hidden" name="id" value="<?= $fid ?>">
        <div class="dialog-actions"><button type="button" class="btn" data-modal-close>Cancel</button><button type="submit" class="btn btn-danger"><?= icon('trash', 16) ?> Delete folder</button></div>
    </form>
</dialog>
<?php endif; ?>
<dialog id="shuffle-dialog" class="dlg-wide">
    <div class="dlg-head"><h2>Shuffle <?= e($f['name']) ?></h2><button type="button" class="icon-btn" data-modal-close aria-label="Close"><?= icon('x', 16) ?></button></div>
    <p class="muted">Five random picks from this folder.</p>
    <div id="shuffle-results" class="shuffle-grid" aria-live="polite"></div>
    <div class="dialog-actions"><button type="button" class="btn" data-modal-close>Close</button><button type="button" class="btn btn-primary" id="shuffle-again"><?= icon('shuffle', 16) ?> Shuffle again</button></div>
</dialog>
<?php require __DIR__ . '/includes/footer.php'; ?>
