<?php
// "Add to Watchlist": pick movies from the shared catalog and set them to To Watch on MY list (many at once).
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/picker.php';

require_login();
$uid = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['movie_ids'] ?? [])))));
    if (!csrf_valid()) {
        flash('Your session expired. Please try again.', 'error');
    } elseif (!$ids) {
        flash('Tick at least one movie to add.', 'error');
    } else {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $s = $conn->prepare("INSERT INTO user_movies (user_id, movie_id, watch_status) SELECT ?, movie_id, 'To Watch' FROM movies WHERE movie_id IN ($in)
            ON DUPLICATE KEY UPDATE watch_status = 'To Watch'");
        $s->bind_param('i' . str_repeat('i', count($ids)), $uid, ...$ids); $s->execute();
        $n = count($ids);
        flash("$n " . ($n === 1 ? 'movie' : 'movies') . ' added to your watchlist.');
        redirect('watchlist.php');
    }
    redirect('watchlist_add.php');
}

$ms = $conn->prepare("SELECT m.*, COALESCE(um.watch_status, 'Not added') AS watch_status, um.user_rating
    FROM movies m LEFT JOIN user_movies um ON um.movie_id = m.movie_id AND um.user_id = ?
    WHERE um.watch_status IS NULL OR um.watch_status <> 'To Watch' ORDER BY m.title");
$ms->bind_param('i', $uid); $ms->execute();
$movies = $ms->get_result()->fetch_all(MYSQLI_ASSOC);

$page_title = 'Add to Watchlist';
$active = 'watchlist';
$extra_js = ['assets/js/collections.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Watchlist</span>
        <h1>Add to Watchlist</h1>
        <p class="muted">Tick movies from the shared catalog. They are added to your list as To Watch.</p>
    </div>
    <div class="head-actions">
        <?php if (is_developer()): ?><a class="btn" href="add_movie.php"><?= icon('plus', 16) ?> New catalog movie</a><?php endif; ?>
        <a class="btn" href="watchlist.php"><?= icon('chevron-left', 16) ?> Back</a>
    </div>
</div>
<?php if ($movies): render_movie_picker($movies, [], 'your watchlist'); else: ?>
<div class="card empty"><h2>Everything in the catalog is already on your watchlist</h2><p>Check back when new movies are added.</p><a class="btn btn-primary" href="catalog.php">Browse catalog</a></div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>