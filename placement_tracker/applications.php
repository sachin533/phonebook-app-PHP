<?php
/**
 * applications.php - the applications list.
 *
 * GET /applications.php?q=&status=&company=&date_from=&date_to=&sort=&dir=&page=&per_page=
 *
 * The list is built by the database: the search, the filters, the sorting
 * and the pagination all happen in SQL (see inc\list.php), and only the
 * rows of the current page are sent to the browser.
 */
declare(strict_types=1);

require __DIR__ . '/inc/db.php';
require __DIR__ . '/inc/helpers.php';
require __DIR__ . '/inc/auth.php';
require __DIR__ . '/inc/list.php';
require __DIR__ . '/inc/table.php';

require_login();

$user   = current_user();
$userId = (int) $user['id'];

$params = list_params();
$result = fetch_applications_page($userId, $params);

$companies = company_options($userId);

$title      = 'Applications';
$active     = 'applications';
$listParams = $params;

require __DIR__ . '/inc/header.php';
?>

<div class="page-head">
  <div>
    <h1>Applications</h1>
    <p class="subtitle">Manage all your job and internship applications</p>
  </div>
  <div class="page-head-actions">
    <a class="btn btn-primary" href="/new.php"><?= icon('plus', 15) ?> Add application</a>
  </div>
</div>

<div class="card table-card">
  <form class="filter-bar" method="get" action="/applications.php" id="filterForm" role="search">
    <div class="filter-search">
      <span class="search-icon"><?= icon('search', 15) ?></span>
      <label class="visually-hidden" for="filter-search">Search applications</label>
      <input type="search" id="filter-search" name="q" class="form-control"
             value="<?= e((string) $params['q']) ?>" placeholder="Search by company, role, status, location, notes..." autocomplete="off" data-search-input>
    </div>

    <div class="filter-field">
      <label class="visually-hidden" for="filter-status">Status</label>
      <select id="filter-status" name="status" class="form-control">
        <option value="">All Status</option>
        <?php foreach (STATUSES as $option): ?>
          <option value="<?= e($option) ?>"<?= $params['status'] === $option ? ' selected' : '' ?>><?= e($option) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-field">
      <label class="visually-hidden" for="filter-company">Company</label>
      <select id="filter-company" name="company" class="form-control">
        <option value="">All Companies</option>
        <?php foreach ($companies as $company): ?>
          <option value="<?= e($company) ?>"<?= $params['company'] === $company ? ' selected' : '' ?>><?= e($company) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="filter-field" style="display:flex;align-items:center;gap:6px">
      <label class="visually-hidden" for="filter-from">From date</label>
      <input type="date" id="filter-from" name="date_from" class="form-control" style="width:auto" value="<?= e((string) ($params['date_from'] ?? '')) ?>">
      <span class="cell-muted">&rarr;</span>
      <label class="visually-hidden" for="filter-to">To date</label>
      <input type="date" id="filter-to" name="date_to" class="form-control" style="width:auto" value="<?= e((string) ($params['date_to'] ?? '')) ?>">
    </div>

    <input type="hidden" name="sort" value="<?= e((string) $params['sort']) ?>">
    <input type="hidden" name="dir" value="<?= e((string) $params['dir']) ?>">
    <input type="hidden" name="per_page" value="<?= e((string) $params['per_page']) ?>">

    <?php /* The reset link is always in the bar. The JavaScript hides it
            when nothing is filtered, and shows it again as soon as a filter
            is used - so it is there whether the page was reloaded or the
            filter was changed in place. */ ?>
    <a class="filter-reset<?= list_has_criteria($params) ? '' : ' is-hidden' ?>"
       href="/applications.php" data-filter-reset>
      <?= icon('close', 13) ?> Reset
    </a>
  </form>

  <div class="results-loading" data-results-loading>
    <span class="spinner" aria-hidden="true"></span>
    <span>Searching&hellip;</span>
  </div>

  <div class="table-wrap" data-results>
    <div data-results-table>
      <?= render_applications_table($result['rows'], ((int) $result['page'] - 1) * (int) $params['per_page'], $params) ?>
    </div>
  </div>

  <div data-results-pagination>
    <?= render_pagination($result, $params) ?>
  </div>
</div>

<?php require __DIR__ . '/inc/footer.php'; ?>
