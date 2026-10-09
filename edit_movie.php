<?php
// Developer only: edits a movie in the shared catalog (changes are seen by every user).
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/movie_form.php';

require_developer();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) redirect('catalog.php');

$stmt = $conn->prepare('SELECT * FROM movies WHERE movie_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) { flash('That movie no longer exists.', 'error'); redirect('catalog.php'); }

$m = array_intersect_key($row, array_flip(MOVIE_COLS));
foreach ($m as $k => $v) if ($v === null) $m[$k] = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = movie_from_post();
    $errors = csrf_valid() ? movie_validate($m) : ['title' => 'Your session expired. Please submit again.'];
    if (!$errors) {
        $sql = 'UPDATE movies SET ' . implode(', ', array_map(fn($c) => "`$c` = ?", MOVIE_COLS)) . ' WHERE movie_id = ?';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(MOVIE_TYPES . 'i', ...[...movie_params($m), $id]);
        $stmt->execute();
        flash('Catalog movie updated.');
        redirect('view_movie.php?id=' . $id);
    }
}

$page_title = 'Edit ' . $row['title'];
$active = 'catalog';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Developer &middot; catalog</span>
        <h1>Edit movie</h1>
        <p class="muted"><?= e($row['title']) ?></p>
    </div>
    <a class="btn" href="view_movie.php?id=<?= $id ?>"><?= icon('chevron-left', 16) ?> Back to details</a>
</div>
<?php render_movie_form($m, $errors, 'Save changes', 'view_movie.php?id=' . $id); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>