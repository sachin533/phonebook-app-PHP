<?php
/**
 * statistics.php - the numbers behind the tracker.
 *
 * GET /statistics.php
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/stats.php';
require __DIR__ . '/inc/charts.php';

require_login();

$user   = current_user();
$userId = (int) $user['id'];

$counts   = status_counts($userId);
$total    = array_sum($counts);
$trend    = monthly_trend($userId, 6);
$roles    = role_summary($userId, 8);
$companies = company_summary($userId, '', 5);
$colors   = status_colors();

// How many applications turned into an interview or better.
$reached = ($counts['Interview'] ?? 0) + ($counts['Offer'] ?? 0);
$rate    = $total > 0 ? round($reached / $total * 100, 1) : 0;

$title  = 'Statistics';
$active = 'statistics';

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
  <div>
    <h1>Statistics</h1>
    <p class="subtitle">How your applications are going</p>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <span class="stat-icon stat-icon-blue"><?= icon('applications', 19) ?></span>
    <div>
      <div class="stat-value"><?= e((string) $total) ?></div>
      <div class="stat-label">Total applications</div>
    </div>
  </div>
  <div class="stat-card">
    <span class="stat-icon stat-icon-green"><?= icon('check', 19) ?></span>
    <div>
      <div class="stat-value"><?= e((string) $reached) ?></div>
      <div class="stat-label">Reached interview or offer</div>
      <div class="stat-trend"><?= e((string) $rate) ?>% of everything you sent</div>
    </div>
  </div>
  <div class="stat-card">
    <span class="stat-icon stat-icon-amber"><?= icon('calendar', 19) ?></span>
    <div>
      <div class="stat-value"><?= e((string) ($counts['Interview'] ?? 0)) ?></div>
      <div class="stat-label">Interviews</div>
    </div>
  </div>
  <div class="stat-card">
    <span class="stat-icon stat-icon-slate"><?= icon('briefcase', 19) ?></span>
    <div>
      <div class="stat-value"><?= e((string) ($counts['Offer'] ?? 0)) ?></div>
      <div class="stat-label">Offers</div>
    </div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div>
        <h2>Status breakdown</h2>
        <p class="subtitle">Every application by where it stands</p>
      </div>
    </div>
    <div class="card-body">
      <?= render_donut($counts) ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Conversion</h2>
        <p class="subtitle">How far your applications got</p>
      </div>
    </div>
    <div class="card-body">
      <ul class="chart-legend" style="width:100%">
        <?php foreach ($colors as $status => $color): ?>
          <?php $n = (int) ($counts[$status] ?? 0); ?>
          <li style="display:block;padding:9px 0">
            <div style="display:flex;align-items:center;gap:9px">
              <span class="swatch" style="background:<?= e($color) ?>"></span>
              <span class="legend-name"><?= e($status) ?></span>
              <span class="legend-count"><?= e((string) $n) ?></span>
              <span class="legend-pct"><?= $total > 0 ? e((string) round($n / $total * 100)) . '%' : '0%' ?></span>
            </div>
            <?= render_share_bar($n, $total, $color) ?>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</div>

<div class="grid-2-even" style="margin-top:18px">
  <div class="card">
    <div class="card-head">
      <div>
        <h2>Applications per month</h2>
        <p class="subtitle">The last six months</p>
      </div>
    </div>
    <div class="card-body">
      <?= render_bar_chart($trend) ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Most applied roles</h2>
        <p class="subtitle">What you have been targeting</p>
      </div>
    </div>
    <div class="card-body" style="padding-top:6px;padding-bottom:6px">
      <?php if ($roles === []): ?>
        <div class="empty-state" style="padding:32px 20px">
          <h3>No roles yet</h3>
          <p>Add a few applications and the pattern shows up here.</p>
        </div>
      <?php else: ?>
        <ul class="mini-list">
          <?php foreach ($roles as $role): ?>
            <li>
              <span class="mini-main">
                <span class="mini-title"><?= e((string) $role['role']) ?></span>
                <span class="mini-sub"><?= e((string) $role['n']) ?> application(s)</span>
              </span>
              <?= render_share_bar((int) $role['n'], (int) $roles[0]['n'], '#2563eb') ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="card-head">
    <div>
      <h2>Top companies</h2>
      <p class="subtitle">Where you applied the most</p>
    </div>
  </div>
  <div class="card-body" style="padding-top:6px;padding-bottom:6px">
    <?php if ($companies === []): ?>
      <div class="empty-state" style="padding:32px 20px">
        <h3>No companies yet</h3>
        <p>Add a few applications and the list appears here.</p>
      </div>
    <?php else: ?>
      <ul class="mini-list">
        <?php foreach ($companies as $company): ?>
          <li>
            <span class="company-logo" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $company['company'], 0, 1))) ?></span>
            <span class="mini-main">
              <a class="mini-title" href="/index.php?company=<?= urlencode((string) $company['company']) ?>"><?= e((string) $company['company']) ?></a>
              <span class="mini-sub"><?= e((string) $company['n']) ?> application(s)</span>
            </span>
            <?= render_share_bar((int) $company['n'], (int) $companies[0]['n'], '#2563eb') ?>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
