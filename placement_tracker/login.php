<?php
/**
 * login.php - sign in.
 *
 * GET  /login.php  ->  200, the sign in form.
 * POST /login.php  ->  302 to the page the person was heading for, or 422
 *                      with the form and the messages.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/validate.php';

// A signed in person never sees this page.
require_guest();

$errors     = [];
$identifier = '';
$remember   = false;

// Too many wrong passwords in a row: say so before the form is used.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && login_throttled()) {
    $errors['password'] = 'Too many failed attempts. Please wait a few minutes and try again.';
}

// Where to go after signing in. Only a local path is allowed, so the
// parameter cannot be used to send somebody to another website.
$next = (string) ($_GET['next'] ?? $_POST['next'] ?? '');
if ($next === '') {
    $next = (string) ($_SESSION['intended'] ?? '/dashboard.php');
}
if (!str_starts_with($next, '/') || str_starts_with($next, '//') || str_contains($next, "\n")) {
    $next = '/dashboard.php';
}
unset($_SESSION['intended']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    [$data, $errors] = validate_login($_POST);
    $identifier = $data['identifier'];
    $remember   = !empty($_POST['remember']);

    if (!$errors) {
        if (attempt_login($identifier, (string) ($_POST['password'] ?? ''), $remember)) {
            flash('Welcome back.');
            redirect($next);
        }
        if (login_throttled()) {
            // Too many wrong passwords in a row: say so plainly.
            $errors['password'] = 'Too many failed attempts. Please wait a few minutes and try again.';
        } else {
            // The same message for an unknown account and a wrong password,
            // so the form cannot be used to find out which accounts exist.
            $errors['password'] = 'That email address, username or password is not right.';
        }
    }

    http_response_code(422);
}

$title  = 'Sign in';
$layout = 'auth';

require __DIR__ . '/inc/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert">
    <?= icon('warning', 17) ?>
    <span>
      <strong>We could not sign you in.</strong>
      <ul>
        <?php foreach ($errors as $message): ?>
          <li><?= e((string) $message) ?></li>
        <?php endforeach; ?>
      </ul>
    </span>
  </div>
<?php endif; ?>

<h2>Welcome back</h2>
<p class="auth-sub">Sign in to your account to continue.</p>

<form method="post" action="/login.php" novalidate>
  <?= csrf_field() ?>
  <input type="hidden" name="next" value="<?= e($next) ?>">

  <div class="form-group">
    <label for="identifier">Email address or username</label>
    <div class="input-group">
      <span class="input-icon"><?= icon('user', 16) ?></span>
      <input type="text" id="identifier" name="identifier" class="form-control<?= isset($errors['identifier']) ? ' invalid' : '' ?>"
             value="<?= e($identifier) ?>" autocomplete="username" autofocus required>
    </div>
    <?php if (isset($errors['identifier'])): ?>
      <p class="field-error"><?= e((string) $errors['identifier']) ?></p>
    <?php endif; ?>
  </div>

  <div class="form-group">
    <label for="password">Password</label>
    <div class="input-group">
      <span class="input-icon"><?= icon('lock', 16) ?></span>
      <input type="password" id="password" name="password" class="form-control<?= isset($errors['password']) ? ' invalid' : '' ?>"
             autocomplete="current-password" required>
    </div>
    <?php if (isset($errors['password'])): ?>
      <p class="field-error"><?= e((string) $errors['password']) ?></p>
    <?php endif; ?>
  </div>

  <div class="form-row-between">
    <div class="checkbox-row">
      <input type="checkbox" id="remember" name="remember" value="1"<?= $remember ? ' checked' : '' ?>>
      <label for="remember">Remember me</label>
    </div>
  </div>

  <button type="submit" class="btn btn-primary btn-block" style="margin-top:20px">Sign in</button>
</form>

<p class="auth-foot">
  Don't have an account? <a href="/register.php">Sign up</a>
</p>

<?php require __DIR__ . '/inc/footer.php'; ?>
