<?php
// Watchlist folders: constants, one-time table creation, and the folder card component.
declare(strict_types=1);

const FOLDER_COLORS = ['violet' => '#8b5cf6', 'pink' => '#e0409b', 'gold' => '#fbbf24', 'blue' => '#4f8cff', 'green' => '#34d399', 'red' => '#f87171', 'cyan' => '#22d3ee', 'orange' => '#fb923c'];
const FOLDER_ICONS  = ['folder', 'heart', 'star', 'film', 'clock', 'bookmark', 'dice', 'clapper'];

/** Creates the folder tables if they do not exist yet, so an existing database keeps working without a re-import. */
function ensure_collections_schema(mysqli $c): void {
    static $done = false;
    if ($done) return;
    $done = true;
    if ($c->query("SHOW TABLES LIKE 'collection_movies'")->num_rows > 0) return;
    $c->multi_query((string)file_get_contents(__DIR__ . '/../sql/collections.sql'));
    do { if ($r = $c->store_result()) $r->free(); } while ($c->more_results() && $c->next_result());
}

function folder_color(?string $key): string { return FOLDER_COLORS[$key ?? ''] ?? FOLDER_COLORS['violet']; }

/** Built-in folder: every movie with status To Watch. It fills itself and cannot be edited. */
function builtin_folder(int $count): array {
    return ['builtin' => true, 'name' => 'To Watch', 'description' => 'Every movie with status To Watch. This folder fills itself.',
        'color' => 'blue', 'icon' => 'bookmark', 'n' => $count];
}

function folder_card(array $f): string {
    $builtin = !empty($f['builtin']);
    $n = (int)$f['n'];
    return '<a class="folder-card" style="--fc:' . folder_color($f['color']) . '" href="folder.php?id=' . ($builtin ? 'to-watch' : (int)$f['collection_id']) . '">'
        . '<span class="folder-tile">' . icon($f['icon'], 22) . '</span>'
        . '<div class="folder-info"><h3>' . e($f['name']) . ($builtin ? ' <span class="badge">Built-in</span>' : '') . '</h3>'
        . '<p class="muted small">' . e((string)($f['description'] ?? '')) . '</p></div>'
        . '<span class="folder-count">' . $n . ' ' . ($n === 1 ? 'movie' : 'movies') . '</span></a>';
}
