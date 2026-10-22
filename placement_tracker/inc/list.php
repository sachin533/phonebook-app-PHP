<?php
/**
 * inc/list.php - server-side list query: search, filters, sorting, pagination.
 *
 * The browser never receives more than one page of rows: a COUNT query
 * supplies the total and LIMIT / OFFSET supplies the page. Every value is
 * bound through the prepared statement; no SQL is concatenated from input.
 */
declare(strict_types=1);

require_once __DIR__ . '/validate.php';

/** The columns the list may be sorted by, mapped to real column names. */
const SORTABLE = [
    'company'    => 'company',
    'role'       => 'role',
    'status'     => 'status',
    'location'   => 'location',
    'salary'     => 'salary',
    'applied_on' => 'applied_on',
    'created_at' => 'created_at',
];

/**
 * Read the list parameters from the query string, sanitised.
 *
 * @return array<string,mixed> q, status, company, date_from, date_to, sort, dir, per_page, page
 */
function list_params(): array
{
    $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

    $status = (string) ($_GET['status'] ?? '');
    if (!in_array($status, STATUSES, true)) {
        $status = '';
    }

    $company = mb_substr(trim((string) ($_GET['company'] ?? '')), 0, 100);

    $sort = (string) ($_GET['sort'] ?? 'created_at');
    $sort = SORTABLE[$sort] ?? 'created_at';

    $dir = strtolower((string) ($_GET['dir'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

    $perPage = (int) ($_GET['per_page'] ?? 10);
    if (!in_array($perPage, [10, 20, 50], true)) {
        $perPage = 10;
    }

    $page = (int) ($_GET['page'] ?? 1);

    return [
        'q'         => $q,
        'status'    => $status,
        'company'   => $company,
        'date_from' => list_date($_GET['date_from'] ?? ''),
        'date_to'   => list_date($_GET['date_to'] ?? ''),
        'sort'      => $sort,
        'dir'       => $dir,
        'per_page'  => $perPage,
        'page'      => max(1, $page),
    ];
}

/** A query-string date is only used when it is a real Y-m-d value. */
function list_date(mixed $raw): ?string
{
    $value = trim((string) $raw);
    if ($value === '') {
        return null;
    }
    return is_iso_date($value) ? $value : null;
}

/**
 * The WHERE clause + bound values for a user's list query.
 *
 * @param array<string,mixed> $p Sanitised parameters from list_params().
 * @return array{0:string,1:array<string,mixed>} ['WHERE ...', [params in order]]
 */
function list_conditions(int $userId, array $p): array
{
    $where   = ['user_id = ?'];
    $params  = [$userId];

    if ($p['q'] !== '') {
        // addcslashes neutralises the % and _ wildcards so a search for
        // "100%" does not match everything. MySQL's default escape is \.
        $like   = '%' . addcslashes($p['q'], '\\%_') . '%';
        $where[] = '(company LIKE ? OR role LIKE ? OR status LIKE ? OR location LIKE ? OR notes LIKE ?)';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if ($p['status'] !== '') {
        $where[]  = 'status = ?';
        $params[] = $p['status'];
    }
    if ($p['company'] !== '') {
        $where[]  = 'company = ?';
        $params[] = $p['company'];
    }
    if ($p['date_from'] !== null) {
        $where[]  = 'applied_on >= ?';
        $params[] = $p['date_from'];
    }
    if ($p['date_to'] !== null) {
        $where[]  = 'applied_on <= ?';
        $params[] = $p['date_to'];
    }

    return ['WHERE ' . implode(' AND ', $where), $params];
}

/**
 * One page of applications plus the totals for the same conditions.
 *
 * @param array<string,mixed> $p Sanitised parameters from list_params().
 * @return array{rows:array,total:int,page:int,pages:int,from:int,to:int}
 */
function fetch_applications_page(int $userId, array $p): array
{
    [$where, $params] = list_conditions($userId, $p);

    $stmt = db()->prepare("SELECT COUNT(*) FROM applications {$where}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    $pages  = max(1, (int) ceil($total / $p['per_page']));
    $page   = min($p['page'], $pages); // ?page=999 clamps to the last page
    $offset = ($page - 1) * $p['per_page'];

    // LIMIT / OFFSET are built from the already-sanitised integers above.
    $sql = "SELECT id, company, role, status, applied_on, location, salary, job_url, is_sample, created_at
              FROM applications {$where}
             ORDER BY {$p['sort']} {$p['dir']}, id DESC
             LIMIT {$p['per_page']} OFFSET {$offset}";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return [
        'rows'  => $stmt->fetchAll(),
        'total' => $total,
        'page'  => $page,
        'pages' => $pages,
        'from'  => $total === 0 ? 0 : $offset + 1,
        'to'    => min($offset + $p['per_page'], $total),
    ];
}

/** The current user's company names, for the filter dropdown. */
function company_options(int $userId): array
{
    $stmt = db()->prepare('SELECT DISTINCT company FROM applications WHERE user_id = ? ORDER BY company');
    $stmt->execute([$userId]);
    return array_column($stmt->fetchAll(), 'company');
}

/**
 * The list URL with some parameters overridden.
 *
 * Used for sort links, pagination and keeping the search when the page
 * size changes. Defaults that add nothing are dropped from the URL.
 *
 * @param array<string,mixed> $overrides Values to change.
 */
function list_url(array $overrides = []): string
{
    $p      = array_merge(list_params(), $overrides);
    $params = [
        'q'         => $p['q'],
        'status'    => $p['status'],
        'company'   => $p['company'],
        'date_from' => $p['date_from'],
        'date_to'   => $p['date_to'],
        'sort'      => $p['sort'] === 'created_at' ? null : $p['sort'],
        'dir'       => $p['dir'] === 'DESC' ? null : strtolower($p['dir']),
        'per_page'  => $p['per_page'] === 10 ? null : $p['per_page'],
        'page'      => $p['page'] <= 1 ? null : $p['page'],
    ];
    $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');
    $qs     = http_build_query($params);
    return '/applications.php' . ($qs !== '' ? '?' . $qs : '');
}

/** True when the user typed a search or set any filter. */
function list_has_criteria(array $p): bool
{
    return $p['q'] !== '' || $p['status'] !== '' || $p['company'] !== ''
        || $p['date_from'] !== null || $p['date_to'] !== null;
}
