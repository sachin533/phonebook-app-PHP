<?php
/**
 * inc/stats.php - the numbers behind the dashboard and the statistics page.
 *
 * The list itself (search, filters, sorting, pagination) lives in
 * inc\list.php. This file only holds the grouped queries the overview
 * pages need.
 */
declare(strict_types=1);

require_once __DIR__ . '/validate.php';

/**
 * The number of applications in each status.
 *
 * @return array<string,int> Every status is present, even with a count of 0.
 */
function status_counts(int $userId): array
{
    $counts = array_fill_keys(STATUSES, 0);

    $stmt = db()->prepare(
        'SELECT status, COUNT(*) AS n FROM applications
          WHERE user_id = ? GROUP BY status'
    );
    $stmt->execute([$userId]);

    foreach ($stmt->fetchAll() as $row) {
        $status = (string) $row['status'];
        if (array_key_exists($status, $counts)) {
            $counts[$status] = (int) $row['n'];
        }
    }

    return $counts;
}

/**
 * The total number of applications.
 */
function total_applications(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * The most recently applied for applications.
 *
 * @return array<int,array<string,mixed>>
 */
function recent_applications(int $userId, int $limit = 5): array
{
    $limit = max(1, min(50, $limit));

    $stmt = db()->prepare(
        'SELECT id, company, role, status, applied_on, created_at
           FROM applications
          WHERE user_id = ?
          ORDER BY (applied_on IS NULL) ASC, applied_on DESC, created_at DESC, id DESC
          LIMIT ' . $limit
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Applications that need a decision: an interview to prepare for, or an
 * offer to answer. This is what the bell in the top bar counts.
 *
 * @return array<int,array<string,mixed>>
 */
function attention_applications(int $userId, int $limit = 6): array
{
    $limit = max(1, min(20, $limit));

    $stmt = db()->prepare(
        "SELECT id, company, role, status, applied_on
           FROM applications
          WHERE user_id = ? AND status IN ('Interview', 'Offer')
          ORDER BY applied_on DESC, id DESC
          LIMIT " . $limit
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * How many applications need attention.
 */
function attention_count(int $userId): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM applications
          WHERE user_id = ? AND status IN ('Interview', 'Offer')"
    );
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

/**
 * The companies that were applied to, with how many applications each has.
 *
 * @return array<int,array<string,mixed>>
 */
function company_summary(int $userId, string $q = '', int $limit = 200): array
{
    $limit = max(1, min(500, $limit));

    $sql = "SELECT company,
                    COUNT(*) AS n,
                    SUM(status = 'Offer')     AS offer,
                    SUM(status = 'Interview') AS interview,
                    SUM(status = 'Applied')   AS applied,
                    SUM(status = 'Rejected')  AS rejected,
                    SUM(status = 'Wishlist')  AS wishlist
               FROM applications
              WHERE user_id = ?";
    $values = [$userId];

    if ($q !== '') {
        // addcslashes neutralises the % and _ wildcards, the same way
        // inc\list.php does for the main search.
        $sql     .= ' AND company LIKE ?';
        $values[] = '%' . addcslashes($q, '\\%_') . '%';
    }

    $sql .= ' GROUP BY company ORDER BY n DESC, company ASC LIMIT ' . $limit;

    $stmt = db()->prepare($sql);
    $stmt->execute($values);
    return $stmt->fetchAll();
}

/**
 * How many applications were made in each of the last months.
 *
 * @return array<int,array{month:string,label:string,n:int}>
 */
function monthly_trend(int $userId, int $months = 6): array
{
    $months = max(1, min(24, $months));

    $stmt = db()->prepare(
        "SELECT DATE_FORMAT(applied_on, '%Y-%m') AS month, COUNT(*) AS n
           FROM applications
          WHERE user_id = ? AND applied_on IS NOT NULL
          GROUP BY month"
    );
    $stmt->execute([$userId]);

    $counts = [];
    foreach ($stmt->fetchAll() as $row) {
        $counts[(string) $row['month']] = (int) $row['n'];
    }

    // A continuous list of months, ending with the current one.
    $out = [];
    for ($i = $months - 1; $i >= 0; $i--) {
        $ts  = strtotime("-{$i} months");
        $key = date('Y-m', $ts);
        $out[] = [
            'month' => $key,
            'label' => date('M Y', $ts),
            'n'     => $counts[$key] ?? 0,
        ];
    }

    return $out;
}

/**
 * The roles that were applied to, most common first.
 *
 * @return array<int,array<string,mixed>>
 */
function role_summary(int $userId, int $limit = 8): array
{
    $limit = max(1, min(50, $limit));

    $stmt = db()->prepare(
        'SELECT role, COUNT(*) AS n FROM applications
          WHERE user_id = ?
          GROUP BY role ORDER BY n DESC, role ASC LIMIT ' . $limit
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
