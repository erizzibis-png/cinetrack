<?php
// Developer only: adds a movie to the shared catalog (every user can then add it to their own list).
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/movie_form.php';

require_developer();

$m = movie_defaults();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m = movie_from_post();
    $errors = csrf_valid() ? movie_validate($m) : ['title' => 'Your session expired. Please submit again.'];
    if (!$errors) {
        $sql = 'INSERT INTO movies (' . implode(',', array_map(fn($c) => "`$c`", MOVIE_COLS)) . ') VALUES (' . implode(',', array_fill(0, count(MOVIE_COLS), '?')) . ')';
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(MOVIE_TYPES, ...movie_params($m));
        $stmt->execute();
        flash('Movie added to the shared catalog.');
        redirect('view_movie.php?id=' . $conn->insert_id);
    }
}

$page_title = 'Add movie';
$active = 'catalog';
require __DIR__ . '/includes/header.php';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">Developer &middot; catalog</span>
        <h1>Add a movie to the catalog</h1>
        <p class="muted">Every user will be able to find it and add it to their own list. Fields marked * are required.</p>
    </div>
    <a class="btn" href="catalog.php"><?= icon('film', 16) ?> Back to catalog</a>
</div>
<?php render_movie_form($m, $errors, 'Add to catalog', 'catalog.php'); ?>
<?php require __DIR__ . '/includes/footer.php'; ?>