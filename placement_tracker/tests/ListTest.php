<?php
/**
 * tests/ListTest.php - the search, the filters, the sorting and the
 * pagination.
 *
 * The records are created straight in the database, then the list is read
 * back over HTTP. That proves the SQL does the work, not the browser.
 */
declare(strict_types=1);

final class ListTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
        $this->clearTestRecords();
    }

    protected function tearDown(): void
    {
        $this->clearTestRecords();
        parent::tearDown();
    }

    /**
     * How many rows the summary says there are.
     */
    private function totalOf(string $body): int
    {
        if (preg_match('/of <strong>(\d+)<\/strong> results?/', $body, $matches)) {
            return (int) $matches[1];
        }
        if (preg_match('/No results for/', $body)) {
            return 0;
        }
        return -1;
    }

    /**
     * The rows of the results table, and nothing else.
     *
     * The page also holds the company filter, which lists every company
     * name, so a test about the rows has to look at the table only.
     *
     * @return array<int,string> The company names in the table.
     */
    private function rowsOf(string $body): array
    {
        if (!preg_match('#<tbody>(.*?)</tbody>#s', $body, $matches)) {
            return [];
        }
        preg_match_all('/class="company-name"[^>]*>([^<]+)</', $matches[1], $names);
        return $names[1];
    }

    public function testTheSearchFindsACompany(): void
    {
        $this->makeApplication(['company' => 'Zephyr Analytics', 'role' => 'Data Analyst']);
        $this->makeApplication(['company' => 'Other Co', 'role' => 'Designer']);

        [, $body] = $this->request('GET', '/applications.php?q=Zephyr');

        $this->assertSame(1, $this->totalOf($body));
        $this->assertSame(['Zephyr Analytics'], $this->rowsOf($body));
    }

    public function testTheSearchIsCaseInsensitive(): void
    {
        $this->makeApplication(['company' => 'Zephyr Analytics']);

        [, $lower] = $this->request('GET', '/applications.php?q=zephyr');
        [, $upper] = $this->request('GET', '/applications.php?q=ZEPhYr');

        $this->assertSame(1, $this->totalOf($lower));
        $this->assertSame($this->totalOf($lower), $this->totalOf($upper));
    }

    public function testTheSearchFindsARole(): void
    {
        $this->makeApplication(['company' => 'Alpha', 'role' => 'PHP Developer']);
        $this->makeApplication(['company' => 'Beta', 'role' => 'Designer']);

        [, $body] = $this->request('GET', '/applications.php?q=' . rawurlencode('PHP Developer'));

        $this->assertSame(1, $this->totalOf($body));
        $this->assertSame(['Alpha'], $this->rowsOf($body));
    }

    public function testTheSearchFindsALocation(): void
    {
        $this->makeApplication(['company' => 'Alpha', 'location' => 'Kochi, Kerala']);
        $this->makeApplication(['company' => 'Beta', 'location' => 'Mumbai, Maharashtra']);

        [, $body] = $this->request('GET', '/applications.php?q=Kochi');

        $this->assertSame(1, $this->totalOf($body));
        $this->assertSame(['Alpha'], $this->rowsOf($body));
    }

    public function testTheSearchFindsAStatus(): void
    {
        $this->makeApplication(['company' => 'Alpha', 'status' => 'Offer']);
        $this->makeApplication(['company' => 'Beta', 'status' => 'Rejected']);

        [, $body] = $this->request('GET', '/applications.php?q=Offer');

        $this->assertSame(1, $this->totalOf($body));
        $this->assertSame(['Alpha'], $this->rowsOf($body));
    }

    public function testTheSearchFindsANote(): void
    {
        $this->makeApplication(['company' => 'Alpha', 'notes' => 'Referred by the alumni group.']);
        $this->makeApplication(['company' => 'Beta', 'notes' => 'Nothing special.']);

        [, $body] = $this->request('GET', '/applications.php?q=alumni');

        $this->assertSame(1, $this->totalOf($body));
        $this->assertSame(['Alpha'], $this->rowsOf($body));
    }

    public function testTheSearchMatchesPartOfAWord(): void
    {
        $this->makeApplication(['company' => 'Northwind Systems']);
        $this->makeApplication(['company' => 'Southwind Systems']);

        [, $body] = $this->request('GET', '/applications.php?q=wind');

        $this->assertSame(2, $this->totalOf($body));
    }

    public function testTheSearchTreatsAWildcardAsText(): void
    {
        $this->makeApplication(['company' => 'Discount 50% Co']);
        $this->makeApplication(['company' => 'Alpha', 'role' => 'Dev', 'notes' => 'x']);

        // "%" must look for a percent sign, not match every row.
        [, $body] = $this->request('GET', '/applications.php?q=' . rawurlencode('50%'));
        $this->assertSame(1, $this->totalOf($body));
        $this->assertSame(['Discount 50% Co'], $this->rowsOf($body));
    }

    public function testTheStatusFilterWorks(): void
    {
        $this->makeApplication(['company' => 'Alpha', 'status' => 'Applied']);
        $this->makeApplication(['company' => 'Beta', 'status' => 'Rejected']);
        $this->makeApplication(['company' => 'Gamma', 'status' => 'Applied']);

        [, $body] = $this->request('GET', '/applications.php?status=Applied');

        $this->assertSame(2, $this->totalOf($body));
        $this->assertNotContains('Beta', $this->rowsOf($body));
    }

    public function testTheCompanyFilterWorks(): void
    {
        $this->makeApplication(['company' => 'Alpha']);
        $this->makeApplication(['company' => 'Beta']);

        [, $body] = $this->request('GET', '/applications.php?company=Alpha');

        $this->assertSame(1, $this->totalOf($body));
    }

    public function testTheDateFilterWorks(): void
    {
        $this->makeApplication(['company' => 'Old', 'applied_on' => '2026-01-10']);
        $this->makeApplication(['company' => 'New', 'applied_on' => '2026-09-10']);

        [, $body] = $this->request('GET', '/applications.php?date_from=2026-06-01');
        $this->assertSame(1, $this->totalOf($body));
        $this->assertStringContainsString('New', $body);

        [, $body] = $this->request('GET', '/applications.php?date_to=2026-03-01');
        $this->assertSame(1, $this->totalOf($body));
        $this->assertStringContainsString('Old', $body);
    }

    public function testTheSearchAndTheFiltersCombine(): void
    {
        $this->makeApplication(['company' => 'Alpha Co', 'status' => 'Applied', 'role' => 'Tester']);
        $this->makeApplication(['company' => 'Gamma Co', 'status' => 'Rejected', 'role' => 'Tester']);
        $this->makeApplication(['company' => 'Beta Co', 'status' => 'Applied', 'role' => 'Tester']);

        [, $body] = $this->request('GET', '/applications.php?q=Tester&status=Applied');

        $this->assertSame(2, $this->totalOf($body));
        $rows = $this->rowsOf($body);
        $this->assertContains('Alpha Co', $rows);
        $this->assertContains('Beta Co', $rows);
        // The rejected one is out because of the status filter.
        $this->assertNotContains('Gamma Co', $rows);
    }

    public function testAnInvalidFilterIsIgnored(): void
    {
        $this->makeApplication(['company' => 'Alpha']);

        // A status that does not exist is dropped, so every row shows.
        [, $body] = $this->request('GET', '/applications.php?status=Nonsense');
        $this->assertSame(1, $this->totalOf($body));

        // A date that is not real is dropped as well.
        [, $body] = $this->request('GET', '/applications.php?date_from=2026-02-30');
        $this->assertSame(1, $this->totalOf($body));
    }

    public function testTheListIsPaged(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->makeApplication(['company' => sprintf('Paged Co %02d', $i)]);
        }

        [, $first] = $this->request('GET', '/applications.php?per_page=10');
        $this->assertSame(10, substr_count($first, 'class="company-name"'));
        $this->assertStringContainsString('Showing <strong>1</strong> to <strong>10</strong> of <strong>25</strong>', $first);

        [, $second] = $this->request('GET', '/applications.php?per_page=10&page=2');
        $this->assertStringContainsString('Showing <strong>11</strong> to <strong>20</strong>', $second);

        [, $last] = $this->request('GET', '/applications.php?per_page=10&page=3');
        $this->assertStringContainsString('Showing <strong>21</strong> to <strong>25</strong>', $last);
    }

    public function testThePagesShowDifferentRows(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->makeApplication(['company' => sprintf('Unique Co %02d', $i)]);
        }

        [, $first] = $this->request('GET', '/applications.php?per_page=10&page=1&sort=company&dir=asc');
        [, $second] = $this->request('GET', '/applications.php?per_page=10&page=2&sort=company&dir=asc');

        preg_match_all('/class="company-name"[^>]*>([^<]+)</', $first, $firstNames);
        preg_match_all('/class="company-name"[^>]*>([^<]+)</', $second, $secondNames);

        $this->assertCount(10, $firstNames[1]);
        $this->assertCount(10, $secondNames[1]);
        $this->assertSame([], array_intersect($firstNames[1], $secondNames[1]), 'The two pages must not show the same rows.');
    }

    public function testAPageNumberPastTheEndIsClamped(): void
    {
        $this->makeApplication(['company' => 'Alpha']);

        [$status, $body] = $this->request('GET', '/applications.php?page=9999');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Showing <strong>1</strong> to <strong>1</strong> of <strong>1</strong>', $body);
    }

    public function testAnInvalidPageNumberFallsBackToTheFirstPage(): void
    {
        $this->makeApplication(['company' => 'Alpha']);

        [$status, $body] = $this->request('GET', '/applications.php?page=-5');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Showing <strong>1</strong>', $body);
    }

    public function testThePageSizeCanBeChanged(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makeApplication(['company' => sprintf('Sized Co %02d', $i)]);
        }

        [, $ten]   = $this->request('GET', '/applications.php?per_page=10');
        [, $twenty] = $this->request('GET', '/applications.php?per_page=20');
        [, $fifty] = $this->request('GET', '/applications.php?per_page=50');

        $this->assertSame(10, substr_count($ten, 'class="company-name"'));
        $this->assertSame(20, substr_count($twenty, 'class="company-name"'));
        $this->assertSame(30, substr_count($fifty, 'class="company-name"'));

        // A page size that is not on the list falls back to 10.
        [, $odd] = $this->request('GET', '/applications.php?per_page=17');
        $this->assertSame(10, substr_count($odd, 'class="company-name"'));
    }

    public function testThePaginationKeepsTheSearchAndTheFilters(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->makeApplication(['company' => 'Findable Co ' . sprintf('%02d', $i), 'status' => 'Applied']);
        }

        [, $body] = $this->request('GET', '/applications.php?q=Findable&status=Applied&page=2&per_page=10');

        $this->assertStringContainsString('Showing <strong>11</strong> to <strong>20</strong>', $body);
        // The link to page one keeps the search and the filter.
        $this->assertStringContainsString('q=Findable', $body);
        $this->assertStringContainsString('status=Applied', $body);
    }

    public function testThePaginationShowsTheRightButtons(): void
    {
        for ($i = 0; $i < 200; $i++) {
            $this->makeApplication(['company' => sprintf('Many Co %03d', $i)]);
        }

        // 200 rows, 10 per page = 20 pages.
        [, $first] = $this->request('GET', '/applications.php?per_page=10');
        $this->assertStringContainsString('aria-label="Next page"', $first);
        // On the first page there is no page before it.
        $this->assertStringContainsString('class="arrow disabled"', $first);

        [, $middle] = $this->request('GET', '/applications.php?per_page=10&page=10');
        $this->assertStringContainsString('aria-current="page"', $middle);
        $this->assertStringContainsString('aria-label="Previous page"', $middle);
        $this->assertStringContainsString('aria-label="Next page"', $middle);
        $this->assertStringContainsString('&hellip;', $middle);

        [, $last] = $this->request('GET', '/applications.php?per_page=10&page=20');
        $this->assertStringContainsString('Showing <strong>191</strong> to <strong>200</strong>', $last);
        // On the last page there is no page after it.
        $this->assertStringContainsString('class="arrow disabled"', $last);
    }

    public function testTheSortingSwitchesTheOrder(): void
    {
        $this->makeApplication(['company' => 'Bravo Co', 'applied_on' => '2026-03-01']);
        $this->makeApplication(['company' => 'Alpha Co', 'applied_on' => '2026-05-01']);

        [, $body] = $this->request('GET', '/applications.php?sort=company&dir=asc');
        preg_match_all('/class="company-name"[^>]*>([^<]+)</', $body, $matches);
        $this->assertSame(['Alpha Co', 'Bravo Co'], $matches[1]);

        [, $body] = $this->request('GET', '/applications.php?sort=company&dir=desc');
        preg_match_all('/class="company-name"[^>]*>([^<]+)</', $body, $matches);
        $this->assertSame(['Bravo Co', 'Alpha Co'], $matches[1]);
    }

    public function testAnUnknownSortColumnIsIgnored(): void
    {
        $this->makeApplication(['company' => 'Alpha Co']);

        [$status, $body] = $this->request('GET', '/applications.php?sort=' . rawurlencode('password_hash'));
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Alpha Co', $body);
    }

    public function testAnEmptyResultSetIsHandled(): void
    {
        $this->makeApplication(['company' => 'Alpha Co']);

        [$status, $body] = $this->request('GET', '/applications.php?q=zzznothingmatches');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('No matching applications', $body);
        $this->assertStringContainsString('Clear all filters', $body);
    }

    public function testTheApiReturnsTheSameCount(): void
    {
        $this->makeApplication(['company' => 'Findable Co']);
        $this->makeApplication(['company' => 'Other Co']);

        [, $page] = $this->request('GET', '/applications.php?q=Findable');
        [, $api]  = $this->request('GET', '/api/applications.php?q=Findable');

        $data = json_decode($api, true);
        $this->assertTrue($data['ok']);
        $this->assertSame($this->totalOf($page), $data['total']);
        $this->assertStringContainsString('Findable Co', $data['table_html']);
    }

    // ---- the recent list on the dashboard ----------------------------------

    public function testTheRecentListPutsTheDatedRowsFirst(): void
    {
        // A Wishlist row has no application date, so it must not be
        // treated as the most recent thing that happened.
        $this->makeApplication(['company' => 'Dated Co', 'applied_on' => '2026-10-01']);
        $this->makeApplication(['company' => 'Undated Co', 'status' => 'Wishlist', 'applied_on' => null]);

        $rows   = recent_applications($this->ensureTestUser(), 10);
        $names  = array_column($rows, 'company');

        $this->assertNotEmpty($names);
        $this->assertSame('Dated Co', $names[0], 'A dated row has to come before a row without a date.');
    }

    public function testTheDashboardShowsTheRecentApplications(): void
    {
        $this->makeApplication(['company' => 'Recent Co', 'applied_on' => date('Y-m-d')]);
        $this->makeApplication(['company' => 'Undated Co', 'status' => 'Wishlist', 'applied_on' => null]);

        [$status, $body] = $this->request('GET', '/dashboard.php');

        $this->assertSame(200, $status);
        $this->assertStringNotContainsString('Nothing here yet', $body, 'The recent list must not be empty.');
        $this->assertStringContainsString('Recent Co', $body);
    }

    // ---- loading the demonstration records ---------------------------------

    public function testTheEmptyListOffersTheSampleRecords(): void
    {
        $this->clearTestRecords();

        [, $body] = $this->request('GET', '/applications.php');
        $this->assertStringContainsString('Load 500+ sample records', $body);
        $this->assertStringContainsString('name="csrf"', $body);
    }

    public function testLoadingTheSampleRecordsFillsTheAccount(): void
    {
        $this->clearTestRecords();
        $userId = $this->ensureTestUser();

        $token = $this->token('/applications.php');
        [$status] = $this->request('POST', '/demo-data.php', [
            'csrf'      => $token,
            'total'     => '523',
            'return_to' => '/applications.php',
        ]);

        $this->assertSame(302, $status);

        $stmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE user_id = ?');
        $stmt->execute([$userId]);
        $this->assertSame(523, (int) $stmt->fetchColumn(), 'The account should now hold the sample records.');

        // They are marked as sample data, and the list shows them.
        $stmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE user_id = ? AND is_sample = 1');
        $stmt->execute([$userId]);
        $this->assertSame(523, (int) $stmt->fetchColumn());

        [, $body] = $this->request('GET', '/applications.php');
        $this->assertStringContainsString('Showing <strong>1</strong> to <strong>10</strong> of <strong>523</strong>', $body);

        $this->clearTestRecords();
    }

    public function testLoadingTheSampleRecordsTwiceDoesNotDuplicateThem(): void
    {
        $this->clearTestRecords();
        $userId = $this->ensureTestUser();

        foreach (range(1, 2) as $ignored) {
            $token = $this->token('/applications.php');
            $this->request('POST', '/demo-data.php', ['csrf' => $token, 'total' => '523']);
        }

        $stmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE user_id = ?');
        $stmt->execute([$userId]);
        $this->assertSame(523, (int) $stmt->fetchColumn(), 'A second run must not add anything.');

        $this->clearTestRecords();
    }

    public function testLoadingTheSampleRecordsNeedsACsrfToken(): void
    {
        [$status] = $this->request('POST', '/demo-data.php', ['total' => '523']);
        $this->assertSame(403, $status);
    }
}
