<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) redirect('index.php');

$errors = [];
$m = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $m['name']  = trim((string)($_POST['name'] ?? ''));
    $m['email'] = trim((string)($_POST['email'] ?? ''));
    $pass       = (string)($_POST['password'] ?? '');
    $pass2      = (string)($_POST['password2'] ?? '');

    if (!csrf_valid()) {
        $errors['name'] = 'Your session expired. Please try again.';
    } else {
        if ($m['name'] === '' || mb_strlen($m['name']) > 60)  $errors['name']     = 'Enter your name (max 60 chars).';
        if (!filter_var($m['email'], FILTER_VALIDATE_EMAIL))  $errors['email']    = 'Enter a valid email.';
        if (empty($_POST['terms']))                           $errors['terms']    = 'Please accept the Privacy Policy to continue.';
        if (strlen($pass) < 8)                                $errors['password'] = 'Password must be at least 8 characters.';
        elseif ($pass !== $pass2)                             $errors['password2'] = 'Passwords do not match.';

        if (!$errors) {
            $s = $conn->prepare('SELECT user_id FROM users WHERE email = ?');
            $s->bind_param('s', $m['email']); $s->execute();
            if ($s->get_result()->num_rows) $errors['email'] = 'That email is already registered.';
        }
        if (!$errors) {
            $hash = password_hash($pass, PASSWORD_DEFAULT);
            $s = $conn->prepare('INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)');
            $s->bind_param('sss', $m['name'], $m['email'], $hash);
            $s->execute();
            login_user(['user_id' => $conn->insert_id, 'name' => $m['name'], 'email' => $m['email']]);
            flash('Welcome to CineTrack, ' . $m['name'] . '!');
            redirect('index.php');
        }
    }
}

$page_title = 'Create account';
$active = '';
$auth_page  = true;
require __DIR__ . '/includes/header.php';
?>
<div class="auth-split">
    <!-- LEFT: Branding -->
    <aside class="auth-aside">
        <a class="auth-brand" href="index.php">
            <span class="auth-brand-mark"><?= icon('film', 26) ?></span>
            <span class="auth-brand-text">
                <strong><?= e(APP_NAME) ?></strong>
                <small><?= e(APP_TAGLINE) ?></small>
            </span>
        </a>

        <div class="auth-aside-body">
            <span class="eyebrow">New account</span>
            <h1>Begin your <span class="grad-text">movie journey</span>.</h1>
            <p class="muted">Track every film you watch, rate and review the ones you love, and build the collection you deserve.</p>

            <ul class="auth-points">
                <li><span class="auth-point-icon"><?= icon('check', 14) ?></span> Private, per-user collection</li>
                <li><span class="auth-point-icon"><?= icon('check', 14) ?></span> Export or delete your data anytime</li>
                <li><span class="auth-point-icon"><?= icon('check', 14) ?></span> No ads. No tracking. No noise.</li>
            </ul>
        </div>

        <p class="auth-foot muted small">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Built with PHP &amp; MySQL.</p>
    </aside>

    <!-- RIGHT: Form -->
    <section class="auth-main">
        <div class="auth-card">
            <header class="auth-head">
                <h2>Create your account</h2>
                <p class="muted">Already have one? <a href="login.php">Sign in</a></p>
            </header>

            <?php if ($errors): ?>
                <div class="flash flash-error" role="alert"><span><?= isset($errors['terms']) && count($errors) === 1 ? e($errors['terms']) : 'Please fix the highlighted fields below.' ?></span></div>
            <?php endif; ?>

            <form method="post" novalidate class="auth-form">
                <?= csrf_field() ?>

                <div class="field<?= isset($errors['name']) ? ' has-error' : '' ?>">
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" value="<?= e($m['name']) ?>"
                           maxlength="60" autocomplete="name" autofocus required>
                    <?php if (isset($errors['name'])): ?><div class="field-error"><?= e($errors['name']) ?></div><?php endif; ?>
                </div>

                <div class="field<?= isset($errors['email']) ? ' has-error' : '' ?>">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="<?= e($m['email']) ?>"
                           autocomplete="email" required>
                    <?php if (isset($errors['email'])): ?><div class="field-error"><?= e($errors['email']) ?></div><?php endif; ?>
                </div>

                <div class="field<?= isset($errors['password']) ? ' has-error' : '' ?>">
                    <label for="password">Password</label>
                    <div class="pw-wrap">
                        <input id="password" name="password" type="password"
                               autocomplete="new-password" minlength="8" required>
                        <button type="button" class="pw-toggle" data-pw-toggle aria-label="Show password"><?= icon('eye', 18) ?></button>
                    </div>
                    <p class="field-hint">At least 8 characters.</p>
                    <?php if (isset($errors['password'])): ?><div class="field-error"><?= e($errors['password']) ?></div><?php endif; ?>
                </div>

                <div class="field<?= isset($errors['password2']) ? ' has-error' : '' ?>">
                    <label for="password2">Confirm password</label>
                    <div class="pw-wrap">
                        <input id="password2" name="password2" type="password"
                               autocomplete="new-password" minlength="8" required>
                        <button type="button" class="pw-toggle" data-pw-toggle aria-label="Show password"><?= icon('eye', 18) ?></button>
                    </div>
                    <?php if (isset($errors['password2'])): ?><div class="field-error"><?= e($errors['password2']) ?></div><?php endif; ?>
                </div>

                <label class="auth-terms">
                    <input type="checkbox" name="terms" value="1" required>
                    <span>I agree to the <a href="privacy.php" target="_blank">Privacy Policy</a> and <a href="privacy.php#terms" target="_blank">Terms</a>.</span>
                </label>

                <button class="btn btn-primary btn-lg auth-submit" type="submit">
                    <?= icon('check', 16) ?> Create account
                </button>
            </form>

            <p class="auth-alt muted small">
                Or <a href="login.php">sign in with an existing account</a>.
            </p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>