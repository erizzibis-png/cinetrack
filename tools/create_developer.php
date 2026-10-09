<?php
// Create a developer account, or promote an existing account.
// Allowed when (a) no developer exists yet and you are on this computer, or (b) you are signed in as a developer.
declare(strict_types=1);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth.php';

$devCount = (int)$conn->query("SELECT COUNT(*) FROM users WHERE role = 'developer'")->fetch_row()[0];
$local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if ($devCount > 0 || !$local) {
    if (!is_logged_in() || !is_developer()) {
        http_response_code(403);
        exit($devCount > 0 ? 'A developer account already exists. Sign in as the developer to add more.' : 'First developer setup only runs on the local machine.');
    }
}

$errors = []; $m = ['name' => '', 'email' => ''];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m['name'] = trim((string)($_POST['name'] ?? ''));
    $m['email'] = trim((string)($_POST['email'] ?? ''));
    $pass = (string)($_POST['password'] ?? '');
    if (!csrf_valid()) $errors['name'] = 'Your session expired. Please try again.';
    else {
        if ($m['name'] === '' || mb_strlen($m['name']) > 60) $errors['name'] = 'Enter a name (max 60 chars).';
        if (!filter_var($m['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Enter a valid email.';
        if (strlen($pass) < 8) $errors['password'] = 'Password must be at least 8 characters.';
    }
    if (!$errors) {
        $s = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
        $s->bind_param('s', $m['email']); $s->execute();
        $ex = $s->get_result()->fetch_assoc();
        if ($ex) {   // promote and reset the password of that account
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $s = $conn->prepare("UPDATE users SET role = 'developer', is_active = 1, password_hash = ? WHERE user_id = ?");
            $s->bind_param('si', $hash, $ex['user_id']); $s->execute();
        } else {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $s = $conn->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'developer')");
            $s->bind_param('sss', $m['name'], $m['email'], $hash); $s->execute();
        }
        flash('Developer account ready: ' . $m['email']);
        redirect(is_logged_in() ? '../admin.php' : '../login.php');
    }
}

$page_title = 'Developer account';
$active = 'admin';
$base_href = '../';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div><span class="eyebrow">Setup</span><h1>Create a developer account</h1>
<p class="muted">If the email already exists, that account is promoted and its password is reset.</p></div></div>
<form method="post" novalidate style="max-width:560px">
    <?= csrf_field() ?>
    <?php if ($errors): ?><div class="flash flash-error" role="alert"><span><?= e(reset($errors)) ?></span></div><?php endif; ?>
    <section class="card form-section"><div class="form-grid" style="grid-template-columns:1fr">
        <?php
        require_once __DIR__ . '/../includes/movie_form.php';
        f_input('name', 'Name', $m, $errors, 'text', ['maxlength' => 60], true, true);
        f_input('email', 'Email', $m, $errors, 'email', [], true, true);
        f_input('password', 'Password (min 8 characters)', ['password' => ''], $errors, 'password', ['autocomplete' => 'new-password'], true, true);
        ?>
    </div></section>
    <button class="btn btn-primary" type="submit" style="margin-top:1rem"><?= icon('shield', 16) ?> Save developer account</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
