<?php
/**
 * dashboard.php - the overview.
 *
 * GET /dashboard.php  ->  200, the numbers and the charts.
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

$counts = status_counts($userId);
$total  = array_sum($counts);

// How far back the "recent" list reaches.
$period = (int) ($_GET['period'] ?? 6);
if (!in_array($period, [3, 6, 12, 0], true)) {
    $period = 6;
}

$recent = recent_applications($userId, 40);
if ($period > 0) {
    $cutoff = date('Y-m-d', strtotime("-{$period} months"));
    // A Wishlist row has no application date, so the day it was added is
    // used instead. Without this those rows would be sorted to the top and
    // then thrown away by the filter, and the list would come out empty.
    $recent = array_values(array_filter(
        $recent,
        static function (array $row) use ($cutoff): bool {
            $when = (string) ($row['applied_on'] ?? '');
            if ($when === '') {
                $when = substr((string) $row['created_at'], 0, 10);
            }
            return $when >= $cutoff;
        }
    ));
}
$recent = array_slice($recent, 0, 6);

$trend = monthly_trend($userId, 6);

$title  = 'Dashboard';
$active = 'dashboard';

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
  <div>
    <h1>Dashboard</h1>
    <p class="subtitle">Overview of your placement applications</p>
  </div>
  <div class="page-head-actions">
    <form method="get" action="/dashboard.php" style="display:inline-flex;align-items:center;gap:8px">
      <label for="period" class="visually-hidden">Time range</label>
      <select id="period" name="period" class="form-control" style="width:auto" onchange="this.form.submit()">
        <option value="3"<?= $period === 3 ? ' selected' : '' ?>>Last 3 months</option>
        <option value="6"<?= $period === 6 ? ' selected' : '' ?>>Last 6 months</option>
        <option value="12"<?= $period === 12 ? ' selected' : '' ?>>Last 12 months</option>
        <option value="0"<?= $period === 0 ? ' selected' : '' ?>>All time</option>
      </select>
    </form>
    <a class="btn btn-primary" href="/new.php"><?= icon('plus', 15) ?> Add application</a>
  </div>
</div>

<div class="stat-grid">
  <?php
  $cards = [
      ['label' => 'Total Applications', 'value' => $total,                                   'icon' => 'applications', 'tone' => 'blue'],
      ['label' => 'Applied',           'value' => $counts['Applied'] ?? 0,                     'icon' => 'check',        'tone' => 'slate'],
      ['label' => 'In Interview',      'value' => $counts['Interview'] ?? 0,                   'icon' => 'calendar',     'tone' => 'amber'],
      ['label' => 'Offers',            'value' => $counts['Offer'] ?? 0,                       'icon' => 'check',        'tone' => 'green'],
      ['label' => 'Rejected',          'value' => $counts['Rejected'] ?? 0,                    'icon' => 'close',        'tone' => 'red'],
      ['label' => 'Wishlist',          'value' => $counts['Wishlist'] ?? 0,                    'icon' => 'briefcase',    'tone' => 'violet'],
  ];
  foreach ($cards as $card):
  ?>
    <div class="stat-card">
      <span class="stat-icon stat-icon-<?= e($card['tone']) ?>"><?= icon($card['icon'], 19) ?></span>
      <div>
        <div class="stat-value"><?= e((string) $card['value']) ?></div>
        <div class="stat-label"><?= e($card['label']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<div class="grid-2">
  <div class="card">
    <div class="card-head">
      <div>
        <h2>Application Status</h2>
        <p class="subtitle">Where every application stands today</p>
      </div>
    </div>
    <div class="card-body">
      <?= render_donut($counts) ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <div>
        <h2>Recent Applications</h2>
        <p class="subtitle"><?= $period === 0 ? 'All time' : 'Last ' . e((string) $period) . ' months' ?></p>
      </div>
      <a href="/index.php" style="font-size:13px;font-weight:600">View All &rarr;</a>
    </div>
    <div class="card-body" style="padding-top:4px;padding-bottom:4px">
      <?php if ($recent === []): ?>
        <div class="empty-state" style="padding:36px 20px">
          <span class="empty-icon"><?= icon('applications', 24) ?></span>
          <h3>Nothing here yet</h3>
          <p>Applications you add will show up here, or you can fill the
             tracker with demonstration records to try everything out.</p>
          <div class="empty-actions">
            <a class="btn btn-primary" href="/new.php"><?= icon('plus', 15) ?> Add application</a>
            <form method="post" action="/demo-data.php" class="inline-form">
              <?= csrf_field() ?>
              <input type="hidden" name="total" value="523">
              <input type="hidden" name="return_to" value="/dashboard.php">
              <button type="submit" class="btn btn-secondary">
                <?= icon('applications', 15) ?> Load 500+ sample records
              </button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <ul class="mini-list">
          <?php foreach ($recent as $row): ?>
            <li>
              <span class="company-logo" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $row['company'], 0, 1))) ?></span>
              <span class="mini-main">
                <a class="mini-title" href="/view.php?id=<?= e((string) $row['id']) ?>"><?= e((string) $row['company']) ?></a>
                <span class="mini-sub"><?= e((string) $row['role']) ?></span>
              </span>
              <span class="<?= e(status_badge_class((string) $row['status'])) ?>"><?= e((string) $row['status']) ?></span>
              <span class="mini-date"><?= e(fmt_date($row['applied_on'] === null ? null : (string) $row['applied_on'])) ?></span>
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
      <h2>Applications over time</h2>
      <p class="subtitle">How many applications you sent each month</p>
    </div>
  </div>
  <div class="card-body">
    <?= render_bar_chart($trend) ?>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
