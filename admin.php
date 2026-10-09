<?php
// Developer panel: platform-wide data (all accounts, totals) and per-user inspection. Developer accounts only.
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

require_developer();
$me = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT);
    $act = (string)($_POST['action'] ?? '');
    if (!csrf_valid()) {
        flash('Your session expired. Please try again.', 'error');
    } elseif (!$tid || $tid === $me) {
        flash('You cannot change your own account here.', 'error');
    } elseif ($act === 'toggle_active') {
        $s = $conn->prepare('UPDATE users SET is_active = 1 - is_active WHERE user_id = ?');
        $s->bind_param('i', $tid); $s->execute();
        flash('Account status updated.');
    } elseif ($act === 'make_developer' || $act === 'make_user') {
        $role = $act === 'make_developer' ? 'developer' : 'user';
        $s = $conn->prepare('UPDATE users SET role = ? WHERE user_id = ?');
        $s->bind_param('si', $role, $tid); $s->execute();
        flash('Role changed to ' . $role . '.');
    } elseif ($act === 'delete_user') {
        $s = $conn->prepare('DELETE FROM users WHERE user_id = ?');   // movies and folders cascade
        $s->bind_param('i', $tid); $s->execute();
        flash($s->affected_rows ? 'Account and all of its data deleted.' : 'That account was already removed.');
    }
    redirect('admin.php' . (isset($_POST['keep']) ? '?user=' . (int)$_POST['keep'] : ''));
}

$one = fn(string $sql): int => (int)$conn->query($sql)->fetch_row()[0];
$tot = [
    'users'   => $one("SELECT COUNT(*) FROM users"),
    'devs'    => $one("SELECT COUNT(*) FROM users WHERE role = 'developer'"),
    'new7'    => $one("SELECT COUNT(*) FROM users WHERE created_at >= NOW() - INTERVAL 7 DAY"),
    'movies'  => $one("SELECT COUNT(*) FROM movies"),
    'folders' => $one("SELECT COUNT(*) FROM collections"),
];

$users = $conn->query("SELECT u.user_id, u.name, u.email, u.role, u.is_active, u.created_at, u.last_login_at,
        (SELECT COUNT(*) FROM movies m WHERE m.user_id = u.user_id) movies,
        (SELECT COUNT(*) FROM collections c WHERE c.user_id = u.user_id) folders
    FROM users u ORDER BY u.role = 'developer' DESC, u.created_at DESC")->fetch_all(MYSQLI_ASSOC);

$viewId = filter_input(INPUT_GET, 'user', FILTER_VALIDATE_INT) ?: 0;
$viewUser = null; $viewMovies = [];
if ($viewId) {
    foreach ($users as $u) if ((int)$u['user_id'] === $viewId) $viewUser = $u;
    if ($viewUser) {
        $s = $conn->prepare('SELECT title, release_year, genre, watch_status, user_rating, watch_count FROM movies WHERE user_id = ? ORDER BY date_added DESC, movie_id DESC LIMIT 200');
        $s->bind_param('i', $viewId); $s->execute();
        $viewMovies = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

$act_btn = function (array $u, string $action, string $label, string $cls = '', string $confirm = '') {
    $c = $confirm !== '' ? ' onclick="return confirm(\'' . e($confirm) . '\')"' : '';
    return '<form method="post">' . csrf_field()
        . '<input type="hidden" name="user_id" value="' . (int)$u['user_id'] . '"><input type="hidden" name="action" value="' . e($action) . '">'
        . '<button class="btn btn-sm ' . e($cls) . '" type="submit"' . $c . '>' . e($label) . '</button></form> ';
};

$page_title = 'Developer panel';
$active = 'admin';
require __DIR__ . '/includes/header.php';
?>
<style>
.admin-table { width: 100%; border-collapse: collapse; text-align: left; }
.admin-table th, .admin-table td { padding: .85rem 1rem; text-align: left; vertical-align: middle; border-bottom: 1px solid var(--line); }
.admin-table th { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--muted); white-space: nowrap; }
.admin-table tr:last-child td { border-bottom: 0; }
.admin-table .num { text-align: center; width: 1%; font-variant-numeric: tabular-nums; }
.admin-table .nowrap { white-space: nowrap; }
.admin-table .row-actions { display: flex; flex-wrap: wrap; gap: .4rem; }
.admin-table .row-actions form { margin: 0; }
</style>
<div class="page-head">
    <div>
        <span class="eyebrow">Developer</span>
        <h1>Developer panel</h1>
        <p class="muted">Platform data and every account. User accounts only ever see their own movies and folders.</p>
    </div>
    <div class="head-actions">
        <a class="btn" href="tools/fetch_posters.php"><?= icon('search', 16) ?> Fetch posters</a>
        <a class="btn" href="tools/create_developer.php"><?= icon('shield', 16) ?> Developer accounts</a>
    </div>
</div>

<section class="section">
    <div class="sec-title" style="--bar:var(--violet)"><div><h2>Developer data</h2><p>Platform totals</p></div></div>
    <div class="grid grid-stats">
        <div class="card stat"><span class="stat-icon"><?= icon('users', 22) ?></span><div><div class="stat-value"><?= $tot['users'] ?></div><div class="stat-label">Accounts</div></div></div>
        <div class="card stat"><span class="stat-icon t-gold"><?= icon('shield', 22) ?></span><div><div class="stat-value"><?= $tot['devs'] ?></div><div class="stat-label">Developers</div></div></div>
        <div class="card stat"><span class="stat-icon t-green"><?= icon('plus', 22) ?></span><div><div class="stat-value"><?= $tot['new7'] ?></div><div class="stat-label">New (7 days)</div></div></div>
        <div class="card stat"><span class="stat-icon t-pink"><?= icon('film', 22) ?></span><div><div class="stat-value"><?= $tot['movies'] ?></div><div class="stat-label">Movies stored</div></div></div>
        <div class="card stat"><span class="stat-icon t-blue"><?= icon('folder', 22) ?></span><div><div class="stat-value"><?= $tot['folders'] ?></div><div class="stat-label">Folders</div></div></div>
    </div>
</section>

<section class="section">
    <div class="sec-title" style="--bar:var(--magenta)"><div><h2>User data</h2><p>All accounts, their activity, and controls</p></div></div>
    <div class="card"><div class="table-wrap"><table class="result-table admin-table">
        <tr><th>Account</th><th>Role</th><th>Status</th><th class="num">Movies</th><th class="num">Folders</th><th>Joined</th><th>Last login</th><th>Actions</th></tr>
        <?php foreach ($users as $u): $self = (int)$u['user_id'] === $me; ?>
        <tr>
            <td><strong><?= e($u['name']) ?></strong><?= $self ? ' <span class="muted small">(you)</span>' : '' ?><br><span class="muted small"><?= e($u['email']) ?></span></td>
            <td><span class="badge <?= $u['role'] === 'developer' ? 'badge-watching' : '' ?>"><?= e($u['role']) ?></span></td>
            <td><span class="badge <?= (int)$u['is_active'] ? 'badge-watched' : 'badge-high' ?>"><?= (int)$u['is_active'] ? 'Active' : 'Suspended' ?></span></td>
            <td class="num"><?= (int)$u['movies'] ?></td>
            <td class="num"><?= (int)$u['folders'] ?></td>
            <td class="muted nowrap"><?= e(format_date($u['created_at'])) ?></td>
            <td class="muted nowrap"><?= $u['last_login_at'] ? e(format_date($u['last_login_at'])) : 'Never' ?></td>
            <td><div class="row-actions">
                <a class="btn btn-sm" href="admin.php?user=<?= (int)$u['user_id'] ?>#detail">View data</a>
                <?php if (!$self):
                    echo $act_btn($u, 'toggle_active', (int)$u['is_active'] ? 'Suspend' : 'Reactivate');
                    echo $u['role'] === 'user' ? $act_btn($u, 'make_developer', 'Make developer', '', 'Give this account full developer access?')
                                               : $act_btn($u, 'make_user', 'Make user');
                    echo $act_btn($u, 'delete_user', 'Delete', 'btn-danger', 'Delete this account and ALL of its movies and folders? This cannot be undone.');
                endif; ?>
            </div></td>
        </tr>
        <?php endforeach; ?>
    </table></div></div>
</section>

<?php if ($viewUser): ?>
<section class="section" id="detail">
    <div class="sec-title" style="--bar:var(--gold)"><div><h2><?= e($viewUser['name']) ?>'s movies</h2><p>Read-only view &middot; <?= (int)$viewUser['movies'] ?> total<?= (int)$viewUser['movies'] > 200 ? ' (showing the latest 200)' : '' ?></p></div></div>
    <?php if ($viewMovies): ?>
    <div class="card"><div class="table-wrap"><table class="result-table">
        <tr><th>Title</th><th>Year</th><th>Genre</th><th>Status</th><th>Rating</th><th>Watched</th></tr>
        <?php foreach ($viewMovies as $m): ?>
        <tr><td><?= e($m['title']) ?></td><td><?= (int)$m['release_year'] ?></td><td><?= e($m['genre']) ?></td>
            <td><span class="badge <?= e(status_class($m['watch_status'])) ?>"><?= e($m['watch_status']) ?></span></td>
            <td><?= $m['user_rating'] !== null ? number_format((float)$m['user_rating'], 1) : '-' ?></td><td>&times;<?= (int)$m['watch_count'] ?></td></tr>
        <?php endforeach; ?>
    </table></div></div>
    <?php else: ?><div class="card empty"><p>This account has no movies yet.</p></div><?php endif; ?>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
