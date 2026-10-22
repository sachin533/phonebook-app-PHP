<?php
/**
 * inc/table.php - the applications table and the pagination bar.
 *
 * They are rendered here as functions so that the applications page and the
 * JSON search endpoint show exactly the same thing. There is only one copy
 * of the markup.
 *
 * The search, the filters and the pagination all come from inc\list.php.
 */
declare(strict_types=1);

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/list.php';

/**
 * The applications table.
 *
 * @param array<int,array<string,mixed>> $rows   The rows of this page.
 * @param int                            $offset The number of rows before this page.
 * @param array<string,mixed>            $params The current list parameters.
 */
function render_applications_table(array $rows, int $offset = 0, array $params = []): string
{
    ob_start();
    ?>
    <table class="data-table">
      <caption class="visually-hidden">Job and internship applications</caption>
      <thead>
        <tr>
          <th scope="col" class="cell-num">#</th>
          <?= render_sort_header('company', 'Company', $params) ?>
          <?= render_sort_header('role', 'Role', $params) ?>
          <?= render_sort_header('status', 'Status', $params) ?>
          <?= render_sort_header('applied_on', 'Applied On', $params) ?>
          <?= render_sort_header('location', 'Location', $params) ?>
          <?= render_sort_header('salary', 'Salary', $params) ?>
          <th scope="col" class="cell-actions">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if ($rows === []): ?>
        <tr>
          <td colspan="8" style="padding:0">
            <?= render_empty_state($params) ?>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach ($rows as $index => $row): ?>
          <?php $id = (int) $row['id']; ?>
          <tr>
            <td class="cell-num"><?= e((string) ($offset + $index + 1)) ?></td>
            <td>
              <div class="company-cell">
                <span class="company-logo" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $row['company'], 0, 1))) ?></span>
                <span style="min-width:0">
                  <a class="company-name" href="/view.php?id=<?= e((string) $id) ?>"><?= e((string) $row['company']) ?></a>
                  <?php if ((int) ($row['is_sample'] ?? 0) === 1): ?>
                    <span class="tag tag-sample">Sample</span>
                  <?php endif; ?>
                </span>
              </div>
            </td>
            <td><?= e((string) $row['role']) ?></td>
            <td><span class="<?= e(status_badge_class((string) $row['status'])) ?>"><?= e((string) $row['status']) ?></span></td>
            <td class="cell-muted"><?= e(fmt_date($row['applied_on'] === null ? null : (string) $row['applied_on'])) ?></td>
            <td class="cell-muted"><?= e((string) ($row['location'] ?? '')) ?: '&mdash;' ?></td>
            <td class="cell-muted"><?= e((string) ($row['salary'] ?? '')) ?: '&mdash;' ?></td>
            <td class="cell-actions">
              <span class="action-group">
                <a class="action-btn" href="/view.php?id=<?= e((string) $id) ?>" title="View details" aria-label="View details"><?= icon('eye', 16) ?></a>
                <a class="action-btn" href="/edit.php?id=<?= e((string) $id) ?>" title="Edit" aria-label="Edit"><?= icon('edit', 16) ?></a>
                <form method="post" action="/delete.php" class="inline-form"
                      data-confirm="Delete this application? This cannot be undone.">
                  <?= csrf_field() ?>
                  <input type="hidden" name="id" value="<?= e((string) $id) ?>">
                  <button type="submit" class="action-btn danger" title="Delete" aria-label="Delete"><?= icon('trash', 16) ?></button>
                </form>
              </span>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
    <?php
    return (string) ob_get_clean();
}

/**
 * A clickable column header that sorts the list.
 */
function render_sort_header(string $column, string $label, array $params): string
{
    $sort    = (string) ($params['sort'] ?? 'created_at');
    $dir     = (string) ($params['dir'] ?? 'DESC');
    $isSorted = $sort === $column;
    $nextDir  = ($isSorted && $dir === 'ASC') ? 'DESC' : 'ASC';

    $url = list_url(['sort' => $column, 'dir' => $nextDir, 'page' => 1]);

    $iconName = ($isSorted && $dir === 'ASC') ? 'arrow-up' : 'arrow-down';

    return '<th scope="col" class="' . ($isSorted ? 'sorted' : '') . '">'
         . '<a href="' . e($url) . '">' . e($label)
         . '<span class="sort-icon">' . icon($iconName, 12) . '</span></a></th>';
}

/**
 * The message shown when nothing matches.
 */
function render_empty_state(array $params = []): string
{
    $filtered = list_has_criteria($params);

    ob_start();
    ?>
    <div class="empty-state">
      <span class="empty-icon"><?= icon($filtered ? 'search' : 'applications', 26) ?></span>
      <?php if ($filtered): ?>
        <h3>No matching applications</h3>
        <p>
          Nothing matched your search and filters. Try a different word, or
          clear the filters to see everything again.
        </p>
        <div class="empty-actions">
          <a class="btn btn-primary" href="/applications.php">Clear all filters</a>
        </div>
      <?php else: ?>
        <h3>No applications yet</h3>
        <p>Add the first role you are applying for, or fill the list with
           500+ demonstration records to try the search and the pagination.</p>
        <div class="empty-actions">
          <a class="btn btn-primary" href="/new.php">
            <?= icon('plus', 15) ?> Add application
          </a>
          <form method="post" action="/demo-data.php" class="inline-form">
            <?= csrf_field() ?>
            <input type="hidden" name="total" value="523">
            <input type="hidden" name="return_to" value="/applications.php">
            <button type="submit" class="btn btn-secondary">
              <?= icon('applications', 15) ?> Load 500+ sample records
            </button>
          </form>
        </div>
      <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * "Showing 1 to 10 of 523 results".
 *
 * @param array<string,mixed> $result The result of fetch_applications_page().
 */
function render_result_summary(array $result, array $params = []): string
{
    $total = (int) $result['total'];
    $from  = (int) $result['from'];
    $to    = (int) $result['to'];

    if ($total === 0) {
        $term = trim((string) ($params['q'] ?? ''));
        if ($term !== '') {
            return 'No results for <strong>' . e($term) . '</strong>';
        }
        return 'No applications yet';
    }

    $summary = 'Showing <strong>' . e((string) $from) . '</strong> to <strong>' . e((string) $to)
             . '</strong> of <strong>' . e((string) $total) . '</strong> ' . ($total === 1 ? 'result' : 'results');

    $term = trim((string) ($params['q'] ?? ''));
    if ($term !== '') {
        $summary .= ' for <strong>' . e($term) . '</strong>';
    }

    return $summary;
}

/**
 * The pagination bar: the summary, the page size selector and the numbered
 * page buttons.
 *
 * @param array<string,mixed> $result The result of fetch_applications_page().
 */
function render_pagination(array $result, array $params = []): string
{
    $total = (int) $result['total'];
    $page  = (int) $result['page'];
    $pages = (int) $result['pages'];

    ob_start();
    ?>
    <div class="pagination-bar">
      <div class="pagination-info"><?= render_result_summary($result, $params) ?></div>

      <div class="per-page">
        <label for="per-page">Rows</label>
        <select id="per-page" name="per_page" data-per-page>
          <?php foreach ([10, 20, 50] as $size): ?>
            <option value="<?= e((string) $size) ?>"<?= (int) ($params['per_page'] ?? 10) === $size ? ' selected' : '' ?>><?= e((string) $size) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if ($pages > 1): ?>
        <nav aria-label="Pagination">
          <ul class="pagination">
            <?php if ($page > 1): ?>
              <li><a class="arrow" href="<?= e(list_url(['page' => $page - 1])) ?>" rel="prev" aria-label="Previous page"><?= icon('chevron-left', 15) ?></a></li>
            <?php else: ?>
              <li><span class="arrow disabled" aria-hidden="true"><?= icon('chevron-left', 15) ?></span></li>
            <?php endif; ?>

            <?php foreach (pagination_window($page, $pages) as $item): ?>
              <?php if ($item === '...'): ?>
                <li><span class="gap">&hellip;</span></li>
              <?php elseif ((int) $item === $page): ?>
                <li><span class="current" aria-current="page"><?= e((string) $item) ?></span></li>
              <?php else: ?>
                <li><a href="<?= e(list_url(['page' => (int) $item])) ?>"><?= e((string) $item) ?></a></li>
              <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($page < $pages): ?>
              <li><a class="arrow" href="<?= e(list_url(['page' => $page + 1])) ?>" rel="next" aria-label="Next page"><?= icon('chevron-right', 15) ?></a></li>
            <?php else: ?>
              <li><span class="arrow disabled" aria-hidden="true"><?= icon('chevron-right', 15) ?></span></li>
            <?php endif; ?>
          </ul>
        </nav>
      <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * The page numbers to show, with "..." where a gap is.
 *
 * The first page, the last page and the pages around the current one are
 * always shown, so the bar stays about the same width on every page.
 *
 * @return array<int,int|string>
 */
function pagination_window(int $page, int $pages, int $around = 2): array
{
    if ($pages <= 1) {
        return [1];
    }

    $numbers = [];
    for ($i = 1; $i <= $pages; $i++) {
        if ($i === 1 || $i === $pages || abs($i - $page) <= $around) {
            $numbers[] = $i;
        }
    }

    $out      = [];
    $previous = 0;
    foreach ($numbers as $number) {
        if ($previous !== 0 && $number - $previous > 1) {
            $out[] = '...';
        }
        $out[] = $number;
        $previous = $number;
    }

    return $out;
}
