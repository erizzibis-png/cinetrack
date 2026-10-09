<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/movie_form.php';

require_login();
$uid = current_user_id();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) redirect('catalog.php');

$stmt = $conn->prepare('SELECT m.*, um.watch_status, um.watch_count, um.user_rating, um.review, um.priority, um.favorite, um.date_added AS added_on,
        (um.user_id IS NOT NULL) AS on_list
    FROM movies m LEFT JOIN user_movies um ON um.movie_id = m.movie_id AND um.user_id = ? WHERE m.movie_id = ?');
$stmt->bind_param('ii', $uid, $id);
$stmt->execute();
$m = $stmt->get_result()->fetch_assoc();
if (!$m) { flash('That movie is not in the catalog.', 'error'); redirect('catalog.php'); }

$onList = (int)$m['on_list'] === 1;
$mid  = (int)$m['movie_id'];
$self = 'view_movie.php?id=' . $mid;
$t = $onList ? [
    'watch_status' => (string)$m['watch_status'], 'priority' => (string)$m['priority'], 'watch_count' => (string)(int)$m['watch_count'],
    'user_rating' => $m['user_rating'] === null ? '' : (string)(float)$m['user_rating'], 'review' => (string)($m['review'] ?? ''), 'favorite' => (int)$m['favorite'],
] : null;

$na = fn($v) => ($v === null || $v === '') ? 'Not specified' : e((string)$v);
$page_title = $m['title'];
$active = 'catalog';
require __DIR__ . '/includes/header.php';
?>
<section class="hero">
    <?php if ($m['poster_url']): ?><div class="hero-bg" style="background-image:url('<?= e($m['poster_url']) ?>')"></div><?php endif; ?>
    <div class="hero-body">
        <?= poster($m['poster_url'], $m['title'], 'hero-poster') ?>
        <div>
            <span class="badge"><?= e($m['genre']) ?></span>
            <h1><?= e($m['title']) ?></h1>
            <p class="muted"><?= (int)$m['release_year'] ?> &middot; <?= e(format_duration((int)$m['duration_minutes'])) ?> &middot; <?= e($m['country']) ?> &middot; <?= $na($m['age_rating']) ?></p>
            <p><?= e($m['short_description']) ?></p>
            <div class="hero-actions">
                <?php if (!empty($m['watch_url'])): ?>
                    <a class="btn btn-primary" href="<?= e($m['watch_url']) ?>" target="_blank" rel="noopener noreferrer"><?= icon('play', 16) ?> Watch now</a>
                <?php endif; ?>
                <?php if (!$onList): ?>
                    <form method="post" action="list_action.php" style="margin:0">
                        <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="movie_id" value="<?= $mid ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
                        <button class="btn <?= empty($m['watch_url']) ? 'btn-primary' : '' ?>" type="submit"><?= icon('plus', 16) ?> Add to my list</button>
                    </form>
                <?php endif; ?>
                <?php if (is_developer()): ?>
                    <a class="btn" href="edit_movie.php?id=<?= $mid ?>"><?= icon('edit', 16) ?> Edit movie</a>
                    <button class="btn btn-danger" type="button" data-modal-open="#delete-dialog"><?= icon('trash', 16) ?> Delete from catalog</button>
                <?php endif; ?>
                <a class="btn" href="catalog.php"><?= icon('film', 16) ?> Back to catalog</a>
            </div>
        </div>
    </div>
</section>

<div class="detail-grid">
    <section class="card">
        <h2>Movie information</h2>
        <dl class="info">
            <div><dt>Director</dt><dd><?= $na($m['director']) ?></dd></div>
            <div><dt>Main cast</dt><dd><?= $na($m['cast']) ?></dd></div>
            <div><dt>Release year</dt><dd><?= (int)$m['release_year'] ?></dd></div>
            <div><dt>Runtime</dt><dd><?= e(format_duration((int)$m['duration_minutes'])) ?></dd></div>
            <div><dt>Language</dt><dd><?= $na($m['language']) ?></dd></div>
            <div><dt>Country</dt><dd><?= $na($m['country']) ?></dd></div>
            <div><dt>Age rating</dt><dd><?= $na($m['age_rating']) ?></dd></div>
            <div><dt>Streaming on</dt><dd><?= $na($m['streaming_platform']) ?></dd></div>
        </dl>
    </section>
    <section class="card">
        <h2>My list</h2>
        <?php if ($onList): ?>
            <p class="muted small">Only you can see and change these details. Added on <?= e(format_date($m['added_on'])) ?>.</p>
            <?php render_track_form($t, $mid, $self); ?>
            <form method="post" action="list_action.php" style="margin-top:.8rem" onsubmit="return confirm('Remove this movie from your list? Your rating and review for it will be lost.')">
                <?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="movie_id" value="<?= $mid ?>"><input type="hidden" name="back" value="movies.php">
                <button class="btn btn-sm btn-ghost-danger" type="submit"><?= icon('x', 14) ?> Remove from my list</button>
            </form>
        <?php else: ?>
            <p class="muted">This movie is not on your list yet. Add it to set a status, rate it, and write a review.</p>
            <form method="post" action="list_action.php">
                <?= csrf_field() ?><input type="hidden" name="action" value="add"><input type="hidden" name="movie_id" value="<?= $mid ?>"><input type="hidden" name="back" value="<?= e($self) ?>">
                <button class="btn btn-primary" type="submit"><?= icon('plus', 16) ?> Add to my list</button>
            </form>
        <?php endif; ?>
    </section>
</div>

<?php if (is_developer()): ?>
<dialog id="delete-dialog">
    <h2>Delete from the catalog?</h2>
    <p class="muted"><?= e($m['title']) ?> will be removed for <strong>everyone</strong>, including every user's list, ratings and reviews. This cannot be undone.</p>
    <form method="post" action="delete_movie.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $mid ?>">
        <div class="dialog-actions">
            <button type="button" class="btn" data-modal-close>Cancel</button>
            <button type="submit" class="btn btn-danger"><?= icon('trash', 16) ?> Delete for everyone</button>
        </div>
    </form>
</dialog>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>