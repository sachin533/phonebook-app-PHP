<?php
/**
 * register.php - create an account.
 *
 * This is also the safe way to create the very first account: there is no
 * default user and no built in password. The first person to register
 * takes over the applications that were already in the database.
 *
 * GET  /register.php  ->  200, the sign up form.
 * POST /register.php  ->  302 to the dashboard, or 422 with the messages.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/validate.php';
require __DIR__ . '/inc/seeder.php';

require_guest();

$errors = [];
$d      = ['name' => '', 'email' => '', 'username' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    [$d, $errors] = validate_registration($_POST);

    // Is the email address or the username already taken? This needs the
    // database, so it happens here rather than in the validation.
    if (!isset($errors['email']) && !isset($errors['username'])) {
        $errors += account_taken($d['email'], $d['username']);
    }

    if (!$errors) {
        $id = register_user(
            $d['name'],
            $d['email'],
            $d['username'],
            (string) ($_POST['password'] ?? '')
        );

        login_user($id);

        // A new account starts with the demonstration records, so the search,
        // the filters and the pagination can be tried straight away.
        if (!empty($_POST['sample_data'])) {
            $result = seed_sample_records($id, 523);
            flash(sprintf(
                'Your account is ready. %d sample %s added so you can try everything out.',
                $result['inserted'],
                $result['inserted'] === 1 ? 'record was' : 'records were'
            ));
        } else {
            flash('Your account is ready. Welcome aboard.');
        }

        redirect('/dashboard.php');
    }

    http_response_code(422);
}

$title  = 'Create an account';
$layout = 'auth';

require __DIR__ . '/inc/header.php';
?>

<?php if ($errors): ?>
  <div class="alert alert-error" role="alert">
    <?= icon('warning', 17) ?>
    <span>
      <strong>Please fix the following:</strong>
      <ul>
        <?php foreach ($errors as $message): ?>
          <li><?= e((string) $message) ?></li>
        <?php endforeach; ?>
      </ul>
    </span>
  </div>
<?php endif; ?>

<h2>Create your account</h2>
<p class="auth-sub">Start tracking every role you apply for.</p>

<div class="auth-note">
  <?= icon('check', 16) ?>
  <span>Your password is stored only as a secure hash. It is never written down anywhere in plain text.</span>
</div>

<form method="post" action="/register.php" novalidate>
  <?= csrf_field() ?>

  <div class="form-group">
    <label for="name">Full name</label>
    <div class="input-group">
      <span class="input-icon"><?= icon('user', 16) ?></span>
      <input type="text" id="name" name="name" class="form-control<?= isset($errors['name']) ? ' invalid' : '' ?>"
             value="<?= e((string) $d['name']) ?>" autocomplete="name" autofocus required>
    </div>
    <?php if (isset($errors['name'])): ?>
      <p class="field-error"><?= e((string) $errors['name']) ?></p>
    <?php endif; ?>
  </div>

  <div class="form-group">
    <label for="email">Email address</label>
    <div class="input-group">
      <span class="input-icon"><?= icon('mail', 16) ?></span>
      <input type="email" id="email" name="email" class="form-control<?= isset($errors['email']) ? ' invalid' : '' ?>"
             value="<?= e((string) $d['email']) ?>" autocomplete="email" required>
    </div>
    <?php if (isset($errors['email'])): ?>
      <p class="field-error"><?= e((string) $errors['email']) ?></p>
    <?php endif; ?>
  </div>

  <div class="form-group">
    <label for="username">Username</label>
    <div class="input-group">
      <span class="input-icon"><?= icon('profile', 16) ?></span>
      <input type="text" id="username" name="username" class="form-control<?= isset($errors['username']) ? ' invalid' : '' ?>"
             value="<?= e((string) $d['username']) ?>" autocomplete="username" required>
    </div>
    <?php if (isset($errors['username'])): ?>
      <p class="field-error"><?= e((string) $errors['username']) ?></p>
    <?php endif; ?>
  </div>

  <div class="form-group">
    <label for="password">Password</label>
    <div class="input-group">
      <span class="input-icon"><?= icon('lock', 16) ?></span>
      <input type="password" id="password" name="password" class="form-control<?= isset($errors['password']) ? ' invalid' : '' ?>"
             autocomplete="new-password" required>
    </div>
    <?php if (isset($errors['password'])): ?>
      <p class="field-error"><?= e((string) $errors['password']) ?></p>
    <?php else: ?>
      <p class="field-hint">At least 8 characters, with one letter and one number.</p>
    <?php endif; ?>
  </div>

  <div class="form-group">
    <label for="password_confirm">Confirm password</label>
    <div class="input-group">
      <span class="input-icon"><?= icon('lock', 16) ?></span>
      <input type="password" id="password_confirm" name="password_confirm" class="form-control<?= isset($errors['password_confirm']) ? ' invalid' : '' ?>"
             autocomplete="new-password" required>
    </div>
    <?php if (isset($errors['password_confirm'])): ?>
      <p class="field-error"><?= e((string) $errors['password_confirm']) ?></p>
    <?php endif; ?>
  </div>

  <div class="form-row-between" style="margin-top:4px;margin-bottom:18px">
    <div class="checkbox-row">
      <input type="checkbox" id="sample_data" name="sample_data" value="1" checked>
      <label for="sample_data">Start with 500+ sample records</label>
    </div>
  </div>
  <p class="field-hint" style="margin:-12px 0 18px">
    The sample records are demonstration data: the company names are
    invented and every one of them is marked as a sample in the list. You can
    remove them at any time.
  </p>

  <button type="submit" class="btn btn-primary btn-block" style="margin-top:8px">Create account</button>
</form>

<p class="auth-foot">
  Already have an account? <a href="/login.php">Sign in</a>
</p>

<?php require __DIR__ . '/inc/footer.php'; ?>
