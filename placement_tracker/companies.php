<?php
/**
 * companies.php - the companies that were applied to.
 *
 * GET /companies.php?q=
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

$q        = trim((string) ($_GET['q'] ?? ''));
$q        = mb_substr($q, 0, 100);
$companies = company_summary($userId, $q);

$counts = status_counts($userId);
$total  = array_sum($counts);

$title  = 'Companies';
$active = 'companies';

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
  <div>
    <h1>Companies</h1>
    <p class="subtitle">Every employer you have applied to</p>
  </div>
  <div class="page-head-actions">
    <a class="btn btn-primary" href="/new.php"><?= icon('plus', 15) ?> Add application</a>
  </div>
</div>

<div class="card table-card">
  <form class="filter-bar" method="get" action="/companies.php" role="search">
    <div class="filter-search">
      <span class="search-icon"><?= icon('search', 15) ?></span>
      <label class="visually-hidden" for="company-search">Search companies</label>
      <input type="search" id="company-search" name="q" class="form-control<?= $q !== '' ? ' has-value' : '' ?>"
             value="<?= e($q) ?>" placeholder="Search company name..." autocomplete="off">
    </div>
    <?php if ($q !== ''): ?>
      <a class="filter-reset" href="/companies.php"><?= icon('close', 13) ?> Reset</a>
    <?php endif; ?>
  </form>

  <div class="table-wrap">
    <?php if ($companies === []): ?>
      <div class="empty-state">
        <span class="empty-icon"><?= icon('companies', 26) ?></span>
        <h3><?= $q === '' ? 'No companies yet' : 'No matching companies' ?></h3>
        <p>
          <?= $q === ''
                ? 'Add an application and the company will be listed here.'
                : 'No company name matched "' . e($q) . '".' ?>
        </p>
        <div class="empty-actions">
          <?php if ($q === ''): ?>
            <a class="btn btn-primary" href="/new.php"><?= icon('plus', 15) ?> Add application</a>
          <?php else: ?>
            <a class="btn btn-primary" href="/companies.php">Clear the search</a>
          <?php endif; ?>
        </div>
      </div>
    <?php else: ?>
      <table class="data-table">
        <caption class="visually-hidden">Companies and how many applications each has</caption>
        <thead>
          <tr>
            <th scope="col">Company</th>
            <th scope="col">Applications</th>
            <th scope="col">Status mix</th>
            <th scope="col" class="cell-actions">Open</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($companies as $company): ?>
          <tr>
            <td>
              <div class="company-cell">
                <span class="company-logo" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $company['company'], 0, 1))) ?></span>
                <span class="company-name"><?= e((string) $company['company']) ?></span>
              </div>
            </td>
            <td>
              <span class="cell-strong"><?= e((string) $company['n']) ?></span>
              <span class="cell-muted">&nbsp;application(s)</span>
            </td>
            <td>
              <div style="display:flex;gap:4px;align-items:center;min-width:150px">
                <?php
                $mix = [
                    'Applied'   => (int) $company['applied'],
                    'Interview' => (int) $company['interview'],
                    'Offer'     => (int) $company['offer'],
                    'Rejected'  => (int) $company['rejected'],
                    'Wishlist'  => (int) $company['wishlist'],
                ];
                $colors = status_colors();
                $companyTotal = max(1, (int) $company['n']);
                foreach ($mix as $status => $n):
                    if ($n === 0) { continue; }
                    ?>
                    <span title="<?= e($status . ': ' . (string) $n) ?>"
                          style="height:8px;border-radius:999px;background:<?= e($colors[$status]) ?>;width:<?= e((string) max(8, round($n / $companyTotal * 90))) ?>px"></span>
                <?php endforeach; ?>
              </div>
            </td>
            <td class="cell-actions">
              <a class="btn btn-sm btn-secondary" href="/index.php?company=<?= urlencode((string) $company['company']) ?>">
                View applications
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
