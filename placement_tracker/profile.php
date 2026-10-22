<?php
/**
 * profile.php - the account page.
 *
 * GET  /profile.php  ->  200, the account details.
 * POST /profile.php  ->  change the password, or sign out everywhere.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/stats.php';
require __DIR__ . '/inc/list.php';

require_login();

$user   = current_user();
$userId = (int) $user['id'];

$errors = [];
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'password') {
        $current = (string) ($_POST['current_password'] ?? '');
        $new     = (string) ($_POST['new_password'] ?? '');
        $confirm = (string) ($_POST['new_password_confirm'] ?? '');

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $hash = (string) $stmt->fetchColumn();

        if (!password_verify($current, $hash)) {
            $errors['current_password'] = 'That is not your current password.';
        }

        $problem = password_problem($new);
        if ($problem !== '') {
            $errors['new_password'] = $problem;
        } elseif (!hash_equals($new, $confirm)) {
            $errors['new_password_confirm'] = 'The two passwords do not match.';
        }

        if (!$errors) {
            $stmt = db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);

            // Every saved sign in is dropped, so a stolen cookie stops working.
            $stmt = db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
            $stmt->execute([$userId]);

            $notice = 'Your password was changed. Other devices have been signed out.';
        }
    } elseif ($action === 'signout_everywhere') {
        $stmt = db()->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
        $notice = 'Every saved sign in was removed.';
    }
}

$counts    = status_counts($userId);
$total     = array_sum($counts);
$companies = count(company_options($userId));

$title  = 'Profile';
$active = 'profile';

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
  <div>
    <h1>Profile</h1>
    <p class="subtitle">Your account and the data behind it</p>
  </div>
</div>

<?php if ($notice !== ''): ?>
  <div class="alert alert-success" role="status">
    <?= icon('check', 17) ?>
    <span><?= e($notice) ?></span>
  </div>
<?php endif; ?>

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

<div class="grid-2">
  <div>
    <div class="card">
      <div class="card-body">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px">
          <span class="avatar avatar-lg"><?= e(initials((string) $user['name'])) ?></span>
          <div style="min-width:0">
            <h2 style="font-size:18px"><?= e((string) $user['name']) ?></h2>
            <p class="cell-muted" style="margin:2px 0 0"><?= e((string) $user['email']) ?></p>
          </div>
        </div>

        <dl class="kv-list">
          <dt>Full name</dt><dd><?= e((string) $user['name']) ?></dd>
          <dt>Email</dt><dd><?= e((string) $user['email']) ?></dd>
          <dt>Username</dt><dd><?= e((string) $user['username']) ?></dd>
          <dt>Member since</dt><dd><?= e(fmt_stamp((string) $user['created_at'])) ?></dd>
          <dt>Last sign in</dt><dd><?= e($user['last_login_at'] === null ? 'This is your first session' : fmt_stamp((string) $user['last_login_at'])) ?></dd>
        </dl>
      </div>
    </div>

    <div class="card" style="margin-top:18px">
      <div class="card-head"><h2>Your data</h2></div>
      <div class="card-body">
        <div class="stat-grid" style="margin-bottom:0">
          <div class="stat-card">
            <span class="stat-icon stat-icon-blue"><?= icon('applications', 18) ?></span>
            <div>
              <div class="stat-value" style="font-size:22px"><?= e((string) $total) ?></div>
              <div class="stat-label">Applications</div>
            </div>
          </div>
          <div class="stat-card">
            <span class="stat-icon stat-icon-slate"><?= icon('companies', 18) ?></span>
            <div>
              <div class="stat-value" style="font-size:22px"><?= e((string) $companies) ?></div>
              <div class="stat-label">Companies</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="card" style="margin-top:18px">
      <div class="card-head">
        <div>
          <h2>Signed in devices</h2>
          <p class="subtitle">Remove every saved sign in</p>
        </div>
      </div>
      <div class="card-body">
        <form method="post" action="/profile.php" data-confirm="Sign out on every saved device? You will stay signed in here.">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="signout_everywhere">
          <button type="submit" class="btn btn-secondary">Sign out everywhere</button>
        </form>
      </div>
    </div>
  </div>

  <div class="card" id="password">
    <div class="card-head">
      <div>
        <h2>Change password</h2>
        <p class="subtitle">Use at least 8 characters with a letter and a number</p>
      </div>
    </div>
    <div class="card-body">
      <form method="post" action="/profile.php#password" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="password">

        <div class="form-group" style="margin-bottom:16px">
          <label for="current_password">Current password</label>
          <div class="input-group">
            <span class="input-icon"><?= icon('lock', 16) ?></span>
            <input type="password" id="current_password" name="current_password"
                   class="form-control<?= isset($errors['current_password']) ? ' invalid' : '' ?>" autocomplete="current-password" required>
          </div>
          <?php if (isset($errors['current_password'])): ?>
            <p class="field-error"><?= e((string) $errors['current_password']) ?></p>
          <?php endif; ?>
        </div>

        <div class="form-group" style="margin-bottom:16px">
          <label for="new_password">New password</label>
          <div class="input-group">
            <span class="input-icon"><?= icon('lock', 16) ?></span>
            <input type="password" id="new_password" name="new_password"
                   class="form-control<?= isset($errors['new_password']) ? ' invalid' : '' ?>" autocomplete="new-password" required>
          </div>
          <?php if (isset($errors['new_password'])): ?>
            <p class="field-error"><?= e((string) $errors['new_password']) ?></p>
          <?php endif; ?>
        </div>

        <div class="form-group" style="margin-bottom:16px">
          <label for="new_password_confirm">Confirm new password</label>
          <div class="input-group">
            <span class="input-icon"><?= icon('lock', 16) ?></span>
            <input type="password" id="new_password_confirm" name="new_password_confirm"
                   class="form-control<?= isset($errors['new_password_confirm']) ? ' invalid' : '' ?>" autocomplete="new-password" required>
          </div>
          <?php if (isset($errors['new_password_confirm'])): ?>
            <p class="field-error"><?= e((string) $errors['new_password_confirm']) ?></p>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary">Update password</button>
      </form>
    </div>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
