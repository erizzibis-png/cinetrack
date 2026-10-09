<?php
// Developer only, POST-only: removes a movie from the shared catalog (and from every user's list).
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_developer();

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id) redirect('catalog.php');
if (!csrf_valid()) { flash('Your session expired. Please try again.', 'error'); redirect('view_movie.php?id=' . $id); }

$stmt = $conn->prepare('DELETE FROM movies WHERE movie_id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
flash($stmt->affected_rows ? 'Movie deleted from the catalog.' : 'That movie was already removed.');
redirect('catalog.php');