<?php
/**
 * view.php - the details page for one application.
 *
 * GET /view.php?id=<id>  ->  200 with the record, or 404 for an unknown or
 *                           non-numeric id.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';

require_login();

$app   = find_application((string) ($_GET['id'] ?? ''));
$title = (string) $app['company'];
$active = 'applications';

require __DIR__ . '/inc/header.php';
?>

<div class="breadcrumb">
  <a href="/applications.php">Applications</a>
  <span>/</span>
  <span><?= e((string) $app['company']) ?></span>
</div>

<div class="page-head">
  <div>
    <h1>Application Details</h1>
    <p class="subtitle">Everything recorded about this application</p>
  </div>
  <div class="page-head-actions">
    <a class="btn btn-secondary" href="/edit.php?id=<?= e((string) $app['id']) ?>">
      <?= icon('edit', 15) ?> Edit
    </a>
    <a class="btn btn-primary" href="/new.php"><?= icon('plus', 15) ?> Add application</a>
  </div>
</div>

<div class="detail-grid">
  <div>
    <div class="card hero-card">
      <span class="company-logo" style="width:44px;height:44px;font-size:17px;border-radius:12px" aria-hidden="true">
        <?= e(mb_strtoupper(mb_substr((string) $app['company'], 0, 1))) ?>
      </span>
      <div style="min-width:0">
        <h2><?= e((string) $app['company']) ?></h2>
        <p class="hero-role" style="margin:0"><?= e((string) $app['role']) ?></p>
      </div>
      <span class="<?= e(status_badge_class((string) $app['status'])) ?>" style="margin-left:auto"><?= e((string) $app['status']) ?></span>
    </div>

    <div class="card" style="margin-top:18px">
      <div class="card-head"><h2>Notes</h2></div>
      <div class="card-body">
        <?php if (($app['notes'] ?? '') !== ''): ?>
          <p style="margin:0;white-space:pre-line"><?= e((string) $app['notes']) ?></p>
        <?php else: ?>
          <p class="cell-muted" style="margin:0">No notes were added to this application.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h2>Details</h2></div>
    <div class="detail-list">
      <div class="detail-row">
        <div class="detail-label">Company</div>
        <div class="detail-value"><?= e((string) $app['company']) ?></div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Role</div>
        <div class="detail-value"><?= e((string) $app['role']) ?></div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Status</div>
        <div class="detail-value">
          <span class="<?= e(status_badge_class((string) $app['status'])) ?>"><?= e((string) $app['status']) ?></span>
        </div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Applied on</div>
        <div class="detail-value"><?= e(fmt_date($app['applied_on'] === null ? null : (string) $app['applied_on'], true)) ?></div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Location</div>
        <div class="detail-value"><?= e((string) ($app['location'] ?? '')) ?: '<span class="cell-muted">-</span>' ?></div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Salary</div>
        <div class="detail-value"><?= e((string) ($app['salary'] ?? '')) ?: '<span class="cell-muted">-</span>' ?></div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Job URL</div>
        <div class="detail-value">
          <?php if (!empty($app['job_url'])): ?>
            <a href="<?= e((string) $app['job_url']) ?>" rel="noopener noreferrer nofollow" target="_blank">
              <?= e((string) $app['job_url']) ?> <?= icon('external', 13) ?>
            </a>
          <?php else: ?>
            <span class="cell-muted">-</span>
          <?php endif; ?>
        </div>
      </div>
      <div class="detail-row">
        <div class="detail-label">Added on</div>
        <div class="detail-value"><?= e(fmt_stamp((string) $app['created_at'])) ?></div>
      </div>
      <?php if ((int) ($app['is_sample'] ?? 0) === 1): ?>
        <div class="detail-row">
          <div class="detail-label">Record type</div>
          <div class="detail-value"><span class="tag tag-sample">Sample data</span></div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="danger-zone">
  <div>
    <h3>Delete this application</h3>
    <p>This removes the record permanently. It cannot be undone.</p>
  </div>
  <form method="post" action="/delete.php"
        data-confirm="Delete this application? This cannot be undone.">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e((string) $app['id']) ?>">
    <button type="submit" class="btn btn-danger"><?= icon('trash', 15) ?> Delete</button>
  </form>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
