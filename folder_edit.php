<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/collections.php';
require_once __DIR__ . '/includes/movie_form.php';   // f_input() field helper
require_login();
$uid = current_user_id();
ensure_collections_schema($conn);

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$m = ['name' => '', 'description' => '', 'color' => 'violet', 'icon' => 'folder'];
if ($id) {
    $s = $conn->prepare('SELECT name, description, color, icon FROM collections WHERE collection_id = ? AND user_id = ?');
    $s->bind_param('ii', $id, $uid); $s->execute();
    $row = $s->get_result()->fetch_assoc();
    if (!$row) { flash('That folder no longer exists.', 'error'); redirect('watchlist.php'); }
    $m = array_map(fn($v) => (string)$v, $row);
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['name', 'description', 'color', 'icon'] as $k) $m[$k] = trim((string)($_POST[$k] ?? ''));
    if (!csrf_valid()) $errors['name'] = 'Your session expired. Please submit again.';
    if ($m['name'] === '') $errors['name'] = 'Give your folder a name.';
    elseif (mb_strlen($m['name']) > 60) $errors['name'] = 'Keep the name under 60 characters.';
    elseif (strcasecmp($m['name'], 'To Watch') === 0) $errors['name'] = '"To Watch" is a built-in folder. Pick another name.';
    if (mb_strlen($m['description']) > 255) $errors['description'] = 'Keep the description under 255 characters.';
    if (!isset(FOLDER_COLORS[$m['color']])) $m['color'] = 'violet';
    if (!in_array($m['icon'], FOLDER_ICONS, true)) $m['icon'] = 'folder';
    if (!$errors) {
        $s = $conn->prepare('SELECT collection_id FROM collections WHERE name = ? AND collection_id <> ? AND user_id = ?');
        $s->bind_param('sii', $m['name'], $id, $uid); $s->execute();
        if ($s->get_result()->num_rows) $errors['name'] = 'You already have a folder with that name.';
    }
    if (!$errors) {
        $desc = $m['description'] === '' ? null : $m['description'];
        if ($id) {
            $s = $conn->prepare('UPDATE collections SET name = ?, description = ?, color = ?, icon = ? WHERE collection_id = ? AND user_id = ?');
            $s->bind_param('ssssii', $m['name'], $desc, $m['color'], $m['icon'], $id, $uid); $s->execute();
            flash('Folder updated.');
        } else {
            $s = $conn->prepare('INSERT INTO collections (user_id, name, description, color, icon) VALUES (?, ?, ?, ?, ?)');
            $s->bind_param('issss', $uid, $m['name'], $desc, $m['color'], $m['icon']); $s->execute();
            $id = $conn->insert_id;
            flash('Folder created. Add some movies to it.');
        }
        redirect('folder.php?id=' . $id);
    }
}

$page_title = $id ? 'Edit folder' : 'New folder';
$active = 'watchlist';
$extra_js = ['assets/js/collections.js'];
require __DIR__ . '/includes/header.php';
$editing = isset($row) || ($id && $_SERVER['REQUEST_METHOD'] === 'POST');
?>
<div class="page-head">
    <div>
        <span class="eyebrow"><?= $editing ? 'Editing' : 'New folder' ?></span>
        <h1><?= $editing ? 'Edit folder' : 'Create a folder' ?></h1>
        <p class="muted">Name it, pick a color and an icon. You can change all of it later.</p>
    </div>
    <a class="btn" href="<?= $editing ? 'folder.php?id=' . $id : 'watchlist.php' ?>"><?= icon('chevron-left', 16) ?> Back</a>
</div>
<form method="post" novalidate class="form-page" data-folder-form>
    <?= csrf_field() ?>
    <div class="form-main">
        <?php if ($errors): ?><div class="flash flash-error" role="alert"><span>Please fix the highlighted fields.</span></div><?php endif; ?>
        <section class="card form-section">
            <h2 class="form-h" style="--bar:var(--magenta)">Folder details</h2>
            <div class="form-grid">
                <?php
                f_input('name', 'Folder name', $m, $errors, 'text', ['maxlength' => 60, 'placeholder' => 'e.g. Weekend Marathon'], true, true);
                f_input('description', 'Description', $m, $errors, 'textarea', ['rows' => 2, 'maxlength' => 255, 'placeholder' => 'What is this folder for?'], false, true);
                ?>
            </div>
        </section>
        <section class="card form-section">
            <h2 class="form-h" style="--bar:var(--gold)">Color &amp; icon</h2>
            <p class="muted small">Color</p>
            <div class="swatches">
                <?php foreach (FOLDER_COLORS as $k => $hex): ?>
                    <label class="swatch" title="<?= e(ucfirst($k)) ?>"><input type="radio" name="color" value="<?= $k ?>" <?= $m['color'] === $k ? 'checked' : '' ?>><span style="--sw:<?= $hex ?>"></span></label>
                <?php endforeach; ?>
            </div>
            <p class="muted small" style="margin-top:1.2rem">Icon</p>
            <div class="icon-choices">
                <?php foreach (FOLDER_ICONS as $ic): ?>
                    <label class="icon-choice" title="<?= e(ucfirst($ic)) ?>"><input type="radio" name="icon" value="<?= $ic ?>" <?= $m['icon'] === $ic ? 'checked' : '' ?>><span><?= icon($ic, 20) ?></span></label>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <aside class="form-side">
        <div class="card preview-card">
            <h2 class="form-h" style="--bar:var(--blue)">Preview</h2>
            <div class="folder-card" data-preview style="--fc:<?= folder_color($m['color']) ?>">
                <span class="folder-tile" data-prev-icon><?= icon($m['icon'], 22) ?></span>
                <div class="folder-info"><h3 data-prev-name><?= e($m['name'] !== '' ? $m['name'] : 'Folder name') ?></h3><p class="muted small" data-prev-desc><?= e($m['description'] !== '' ? $m['description'] : 'Description') ?></p></div>
                <span class="folder-count">0 movies</span>
            </div>
        </div>
        <div class="card side-actions">
            <button class="btn btn-primary" type="submit"><?= icon('check', 16) ?> <?= $editing ? 'Save changes' : 'Create folder' ?></button>
            <a class="btn" href="<?= $editing ? 'folder.php?id=' . $id : 'watchlist.php' ?>">Cancel</a>
        </div>
    </aside>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
