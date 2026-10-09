<?php
// Multi-select movie picker: live search, checkbox on every poster, sticky action bar.
// Used by folder_add.php and watchlist_add.php. The page that includes it handles the POST (movie_ids[]).
declare(strict_types=1);

function render_movie_picker(array $movies, array $disabledIds, string $targetName): void { ?>
<form method="post" class="picker" data-picker data-target="<?= e($targetName) ?>">
    <?= csrf_field() ?>
    <div class="card picker-bar">
        <div class="picker-search"><?= icon('search', 16) ?><input type="search" data-q placeholder="Search by title, genre, director, or cast" aria-label="Search movies" autocomplete="off"></div>
        <button type="button" class="btn btn-sm" data-select-shown>Select all shown</button>
        <button type="button" class="btn btn-sm" data-clear>Clear</button>
        <span class="muted small"><span data-shown><?= count($movies) ?> shown</span> &middot; <strong data-count>0 selected</strong></span>
        <button class="btn btn-primary" type="submit" data-submit disabled><?= icon('plus', 16) ?> <span data-submit-label>Select movies to add</span></button>
    </div>
    <div class="grid grid-movies picker-grid">
    <?php foreach ($movies as $m):
        $id  = (int)$m['movie_id'];
        $dis = in_array($id, $disabledIds, true);
        $hay = mb_strtolower($m['title'] . ' ' . $m['genre'] . ' ' . $m['director'] . ' ' . $m['cast'] . ' ' . $m['release_year']); ?>
        <label class="pick-card<?= $dis ? ' is-disabled' : '' ?>" data-search="<?= e($hay) ?>">
            <input type="checkbox" name="movie_ids[]" value="<?= $id ?>"<?= $dis ? ' disabled' : '' ?>>
            <article class="mcard">
                <div class="mcard-media">
                    <?= poster($m['poster_url'], $m['title']) ?>
                    <span class="pill"><?= e($m['genre']) ?></span>
                    <span class="pick-check"><?= icon('check', 16) ?></span>
                </div>
                <div class="mcard-body">
                    <div class="mcard-meta"><span><?= (int)$m['release_year'] ?> &bull; <?= e(format_duration((int)$m['duration_minutes'])) ?></span>
                        <?php if ($m['user_rating'] !== null): ?><span class="rate"><?= icon('star', 13) ?> <?= number_format((float)$m['user_rating'], 1) ?></span><?php endif; ?></div>
                    <h3><?= e($m['title']) ?></h3>
                    <div class="mcard-foot"><span class="badge <?= e(status_class($m['watch_status'])) ?>"><?= e($m['watch_status']) ?></span>
                        <?php if ($dis): ?><span class="muted small">Already added</span><?php endif; ?></div>
                </div>
            </article>
        </label>
    <?php endforeach; ?>
    </div>
    <p class="card empty" data-empty hidden>No movies match your search.</p>
</form>
<?php }
