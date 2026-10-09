<?php
// One-time tool: finds each movie's poster on Wikipedia (no API key) and saves the link in poster_url.
// Developer accounts only. Open http://localhost/<your-folder>/tools/fetch_posters.php and press the button. Local machine only.
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';
defined('WIKI_API') || define('WIKI_API', 'https://en.wikipedia.org/w/api.php');

require_developer();   // developer accounts only (it edits every user's movies)

/** GET a JSON URL. If the local PHP has no CA bundle (common on XAMPP), retry once without certificate checks.
 *  That is safe here because find_poster() only accepts image links on upload.wikimedia.org. */
function http_json(string $url, bool &$sslFallback): ?array {
    $ua = 'CineTrack/1.0 (local personal project)';
    if (function_exists('curl_init')) {
        foreach ([true, false] as $verify) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_USERAGENT => $ua, CURLOPT_SSL_VERIFYPEER => $verify, CURLOPT_SSL_VERIFYHOST => $verify ? 2 : 0]);
            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            curl_close($ch);
            if ($body !== false) return json_decode((string)$body, true) ?: null;
            if (!in_array($errno, [35, 51, 58, 60, 77, 83], true)) return null;   // retry only on SSL/certificate errors
            $sslFallback = true;
        }
        return null;
    }
    $ctx = stream_context_create(['http' => ['header' => "User-Agent: $ua\r\n", 'timeout' => 20]]);
    $body = @file_get_contents($url, false, $ctx);
    return $body === false ? null : (json_decode($body, true) ?: null);
}

/** @return array{0:?string,1:string,2:string} [poster url, status, matched wikipedia title] */
function find_poster(string $title, int $year, bool &$ssl): array {
    $data = http_json(WIKI_API . '?' . http_build_query([
        'action' => 'query', 'format' => 'json', 'generator' => 'search', 'gsrsearch' => "$title $year film",
        'gsrlimit' => 5, 'prop' => 'pageimages', 'piprop' => 'thumbnail', 'pithumbsize' => 500, 'redirects' => 1,
    ]), $ssl);
    $pages = $data['query']['pages'] ?? [];
    if (!$pages) return [null, 'not found', ''];
    uasort($pages, fn($a, $b) => ($a['index'] ?? 99) <=> ($b['index'] ?? 99));

    $norm = fn(string $s) => preg_replace('/[^a-z0-9]+/', '', strtolower(preg_replace('/\s*\(.*?\)\s*$/', '', $s)));
    $want = $norm($title);
    $cands = [];
    foreach ($pages as $p) {
        $src = $p['thumbnail']['source'] ?? '';
        if ($src !== '' && parse_url($src, PHP_URL_HOST) === 'upload.wikimedia.org') $cands[] = [$src, (string)$p['title'], $norm((string)$p['title'])];
    }
    foreach ($cands as [$src, $t, $got]) if ($got === $want) return [$src, 'matched', $t];                                  // exact title
    foreach ($cands as [$src, $t, $got]) if ($got !== '' && str_starts_with($want, $got)) return [$src, 'matched', $t];     // e.g. "Star Wars" article for a longer title
    return $cands ? [$cands[0][0], 'check', $cands[0][1]] : [null, 'no image', ''];                                         // best guess: verify by eye
}

$results = []; $ran = false; $exportMsg = ''; $sslUsed = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    set_time_limit(300);
    $overwrite = !empty($_POST['overwrite']);
    $rows = $conn->query('SELECT movie_id, title, release_year FROM movies'
        . ($overwrite ? '' : " WHERE poster_url IS NULL OR poster_url = ''") . ' ORDER BY title')->fetch_all(MYSQLI_ASSOC);
    $upd = $conn->prepare('UPDATE movies SET poster_url = ? WHERE movie_id = ?');
    foreach ($rows as $row) {
        [$url, $status, $matched] = find_poster($row['title'], (int)$row['release_year'], $sslUsed);
        if ($url) { $id = (int)$row['movie_id']; $upd->bind_param('si', $url, $id); $upd->execute(); }
        $results[] = $row + ['url' => $url, 'status' => $status, 'matched' => $matched];
        usleep(150000);   // be polite to Wikipedia
    }
    $lines = ['-- Poster links exported by tools/fetch_posters.php. Import after seed.sql.', 'USE movie_watchlist;'];
    foreach ($conn->query("SELECT title, release_year, poster_url FROM movies WHERE poster_url IS NOT NULL AND poster_url <> '' ORDER BY title") as $r) {
        $lines[] = sprintf("UPDATE movies SET poster_url = '%s' WHERE title = '%s' AND release_year = %d;",
            $conn->real_escape_string($r['poster_url']), $conn->real_escape_string($r['title']), (int)$r['release_year']);
    }
    $exportMsg = @file_put_contents(__DIR__ . '/../sql/posters.sql', implode("\n", $lines) . "\n") !== false
        ? 'Saved the links to sql/posters.sql so other people can import the same posters.' : 'Could not write sql/posters.sql (folder not writable).';
    $ran = true;
}

$missing = (int)$conn->query("SELECT COUNT(*) FROM movies WHERE poster_url IS NULL OR poster_url = ''")->fetch_row()[0];
$page_title = 'Fetch posters';
$active = 'movies';
$base_href = '../';   // this page lives in /tools, so links must resolve from the project root
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <span class="eyebrow">One-time tool</span>
        <h1>Fetch movie posters</h1>
        <p class="muted"><?= $missing ?> of your movies have no poster yet. Posters come from each movie's Wikipedia article.</p>
    </div>
    <a class="btn" href="movies.php"><?= icon('film', 16) ?> Back to movies</a>
</div>

<section class="card form-section">
    <form method="post" class="tool-form">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit"><?= icon('search', 16) ?> Fetch posters now</button>
        <label class="check-inline"><input type="checkbox" name="overwrite" value="1"> Replace posters that are already set</label>
        <span class="muted small">Takes about a minute. Needs an internet connection.</span>
    </form>
</section>

<?php if ($ran): ?>
<?php $got = count(array_filter($results, fn($r) => $r['url'])); $check = count(array_filter($results, fn($r) => $r['status'] === 'check')); ?>
<section class="card form-section" style="margin-top:1.2rem">
    <h2 class="form-h" style="--bar:var(--gold)">Results: <?= $got ?> of <?= count($results) ?> found</h2>
    <p class="muted"><?= e($exportMsg) ?><?= $check ? " $check marked \"check\" are best guesses, so look at them and fix any wrong one with Edit movie." : '' ?><?= $sslUsed ? ' Your PHP could not verify Wikipedia\'s certificate, so certificate checks were skipped for this run (only Wikimedia image links are saved).' : '' ?></p>
    <div class="table-wrap"><table class="result-table">
        <tr><th></th><th>Movie</th><th>Result</th><th>Wikipedia article</th></tr>
        <?php foreach ($results as $r): ?>
        <tr>
            <td><?php if ($r['url']): ?><img src="<?= e($r['url']) ?>" alt="" width="36"><?php endif; ?></td>
            <td><?= e($r['title']) ?> <span class="muted">(<?= (int)$r['release_year'] ?>)</span></td>
            <td><span class="badge <?= $r['status'] === 'matched' ? 'badge-watched' : ($r['status'] === 'check' ? 'badge-watching' : 'badge-high') ?>"><?= e($r['status']) ?></span></td>
            <td class="muted"><?= e($r['matched']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table></div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
