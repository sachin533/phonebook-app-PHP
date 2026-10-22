<?php
/**
 * new.php - create an application.
 *
 * GET  /new.php  ->  200, an empty form.
 * POST /new.php  ->  302 to the new record, or 422 with the form and errors.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/validate.php';

require_login();

$user   = current_user();
$userId = (int) $user['id'];

$d      = ['status' => 'Applied'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A POST without a valid token is rejected before anything is written.
    require_csrf();

    [$d, $errors] = validate_application($_POST);

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO applications (user_id, company, role, status, applied_on, location, salary, job_url, notes)
             VALUES (:user_id, :company, :role, :status, :applied_on, :location, :salary, :job_url, :notes)'
        );
        $stmt->execute($d + ['user_id' => $userId]);

        // POST/Redirect/GET: the browser follows the redirect with a fresh
        // GET, so a refresh cannot insert the row a second time.
        flash('Application added.');
        redirect('/view.php?id=' . db()->lastInsertId());
    }

    // Something was wrong: show the form again with the messages.
    http_response_code(422);
}

$title       = 'Add application';
$active      = 'applications';
$action      = '/new.php';
$submitLabel = 'Save application';

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
  <div>
    <h1>Add New Application</h1>
    <p class="subtitle">Fill in the details of your job or internship application</p>
  </div>
</div>

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

<?php require __DIR__ . '/inc/form.php'; ?>

<?php require __DIR__ . '/inc/footer.php'; ?>
