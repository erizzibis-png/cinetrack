<?php
// Returns 5 random movies as an HTML fragment for the shuffle dialog (signed-in user's own movies only).
// scope=watchlist (status To Watch) or scope=folder:ID
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/collections.php';

if (!is_logged_in()) { http_response_code(401); exit('Please sign in.'); }
$uid = current_user_id();
ensure_collections_schema($conn);

const SHUFFLE_COUNT = 5;
$scope = (string)($_GET['scope'] ?? 'watchlist');
if (preg_match('/^folder:(\d+)$/', $scope, $mm)) {
    $fid = (int)$mm[1];
    $s = $conn->prepare('SELECT m.* FROM movies m JOIN collection_movies cm ON cm.movie_id = m.movie_id JOIN collections c ON c.collection_id = cm.collection_id
        WHERE cm.collection_id = ? AND c.user_id = ? AND m.user_id = ? ORDER BY RAND() LIMIT ' . SHUFFLE_COUNT);
    $s->bind_param('iii', $fid, $uid, $uid);
    $c = $conn->prepare('SELECT COUNT(*) FROM collection_movies cm JOIN collections c ON c.collection_id = cm.collection_id WHERE cm.collection_id = ? AND c.user_id = ?');
    $c->bind_param('ii', $fid, $uid);
    $label = 'this folder';
} else {
    $s = $conn->prepare("SELECT * FROM movies WHERE user_id = ? AND watch_status = 'To Watch' ORDER BY RAND() LIMIT " . SHUFFLE_COUNT);
    $s->bind_param('i', $uid);
    $c = $conn->prepare("SELECT COUNT(*) FROM movies WHERE user_id = ? AND watch_status = 'To Watch'");
    $c->bind_param('i', $uid);
    $label = 'your watchlist';
}
$s->execute(); $picks = $s->get_result()->fetch_all(MYSQLI_ASSOC);
$c->execute(); $total = (int)$c->get_result()->fetch_row()[0];

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
if (!$picks) { echo '<p class="muted shuffle-note">There is nothing to shuffle yet. Add some movies first.</p>'; exit; }
foreach ($picks as $m): ?>
<a class="shuffle-card" href="view_movie.php?id=<?= (int)$m['movie_id'] ?>">
    <?= poster($m['poster_url'], $m['title']) ?>
    <strong><?= e($m['title']) ?></strong>
    <span class="muted small"><?= (int)$m['release_year'] ?> &middot; <?= e($m['genre']) ?> &middot; <?= e(format_duration((int)$m['duration_minutes'])) ?></span>
</a>
<?php endforeach;
if ($total < SHUFFLE_COUNT): ?><p class="muted shuffle-note">Only <?= $total ?> <?= $total === 1 ? 'movie is' : 'movies are' ?> in <?= $label ?>, so that is everything you have.</p><?php endif;
