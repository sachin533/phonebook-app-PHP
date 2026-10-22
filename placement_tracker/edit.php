<?php
/**
 * edit.php - change an existing application.
 *
 * GET  /edit.php?id=<id>  ->  200 with a pre-filled form, or 404.
 * POST /edit.php?id=<id>  ->  302 to the details page, or 422 with errors.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/validate.php';

require_login();

$user   = current_user();
$userId = (int) $user['id'];

// A missing, non-numeric or unknown id is a 404 on both GET and POST.
// find_application() only ever returns a row that belongs to this person.
$app = find_application((string) ($_GET['id'] ?? ''));

// Pre-fill the form with the values that are already stored.
$d = [
    'company'    => (string) $app['company'],
    'role'       => (string) $app['role'],
    'status'     => (string) $app['status'],
    'applied_on' => $app['applied_on'] === null ? null : (string) $app['applied_on'],
    'location'   => $app['location'] === null ? null : (string) $app['location'],
    'salary'     => $app['salary'] === null ? null : (string) $app['salary'],
    'job_url'    => $app['job_url'] === null ? null : (string) $app['job_url'],
    'notes'      => $app['notes'] === null ? null : (string) $app['notes'],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    [$d, $errors] = validate_application($_POST);

    if (!$errors) {
        $stmt = db()->prepare(
            'UPDATE applications
                SET company    = :company,
                    role       = :role,
                    status     = :status,
                    applied_on = :applied_on,
                    location   = :location,
                    salary     = :salary,
                    job_url    = :job_url,
                    notes      = :notes
              WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute($d + ['id' => (int) $app['id'], 'user_id' => $userId]);

        flash('Application updated.');
        redirect('/view.php?id=' . $app['id']);
    }

    http_response_code(422);
}

$title       = 'Edit application';
$active      = 'applications';
$action      = '/edit.php?id=' . urlencode((string) $app['id']);
$submitLabel = 'Update application';

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
  <div>
    <h1>Edit Application</h1>
    <p class="subtitle"><?= e((string) $app['company']) ?> &middot; <?= e((string) $app['role']) ?></p>
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
