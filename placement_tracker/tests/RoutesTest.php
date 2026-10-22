<?php
/**
 * tests/RoutesTest.php - the pages, the status codes and the full journey.
 */
declare(strict_types=1);

final class RoutesTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->signIn();
    }

    public function testStatusCodes(): void
    {
        $this->assertSame(200, $this->request('GET', '/applications.php')[0]);
        $this->assertSame(200, $this->request('GET', '/dashboard.php')[0]);
        $this->assertSame(200, $this->request('GET', '/companies.php')[0]);
        $this->assertSame(200, $this->request('GET', '/statistics.php')[0]);
        $this->assertSame(200, $this->request('GET', '/profile.php')[0]);
        $this->assertSame(200, $this->request('GET', '/new.php')[0]);

        $this->assertSame(404, $this->request('GET', '/view.php?id=999999')[0]);
        $this->assertSame(404, $this->request('GET', '/view.php?id=abc')[0]);
        $this->assertSame(404, $this->request('GET', '/view.php?id=0')[0]);
        $this->assertSame(404, $this->request('GET', '/edit.php?id=999999')[0]);

        $this->assertSame(405, $this->request('GET', '/delete.php')[0]);
        $this->assertSame(403, $this->request('POST', '/delete.php', ['id' => 1])[0]);
    }

    public function testTheOldIndexAddressStillWorks(): void
    {
        [$status, , $headers] = $this->request('GET', '/index.php');
        $this->assertSame(302, $status);
        $this->assertStringContainsString('/applications.php', $this->location($headers));
    }

    public function testTheErrorPagesAreSelfContained(): void
    {
        $expected = ['/404.php' => 404, '/403.php' => 403, '/405.php' => 405];

        foreach ($expected as $path => $code) {
            [$status, $body] = $this->request('GET', $path);
            $this->assertSame($code, $status, "$path should answer $code");
            $this->assertStringContainsString('stylesheet', $body, "$path should be styled");
        }
    }

    public function testThePagesCarryTheDesign(): void
    {
        [$status, $body] = $this->request('GET', '/dashboard.php');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('class="sidebar"', $body);
        $this->assertStringContainsString('class="topbar"', $body);
        $this->assertStringContainsString('class="donut"', $body);
        $this->assertStringContainsString('class="stat-grid"', $body);
        $this->assertStringContainsString('data-search-input', $body);
        $this->assertStringContainsString('styles.css', $body);
    }

    public function testEveryPageRendersItsWholeContent(): void
    {
        // A record that belongs to the test account, so the edit form has
        // something to show.
        $id = $this->makeApplication(['company' => 'Render Check Co']);

        // A page that stops half way through still answers 200, so the
        // content itself has to be checked.
        $pages = [
            '/dashboard.php'    => ['Dashboard', 'Total Applications', 'Application Status', 'Recent Applications'],
            '/applications.php' => ['Applications', 'Add application', 'data-results-table', 'pagination-bar'],
            '/companies.php'    => ['Companies', 'Search company name'],
            '/statistics.php'   => ['Statistics', 'Status breakdown', 'Conversion', 'Applications per month'],
            '/profile.php'      => ['Profile', 'Change password', self::TEST_EMAIL, 'Member since'],
            '/new.php'          => ['Add New Application', 'Company name', 'name="csrf"'],
            "/edit.php?id=$id"  => ['Edit Application', 'name="company"', 'Render Check Co'],
        ];

        foreach ($pages as $path => $needles) {
            [$status, $body] = $this->request('GET', $path);

            $this->assertSame(200, $status, "$path should return 200");
            $this->assertStringNotContainsString('Server error', $body, "$path must not be an error page");
            $this->assertStringNotContainsString('Uncaught', $body, "$path must not contain a stack trace");

            foreach ($needles as $needle) {
                $this->assertStringContainsString($needle, $body, "$path should contain '$needle'");
            }
        }

        $this->clearTestRecords();
    }

    public function testCreateReadUpdateDeleteJourney(): void
    {
        $company = 'Journey Co ' . bin2hex(random_bytes(4));

        // --- create -------------------------------------------------------
        $token = $this->token('/new.php');

        [$status, , $headers] = $this->request('POST', '/new.php', [
            'csrf'       => $token,
            'company'    => $company,
            'role'       => 'Backend Intern',
            'status'     => 'Applied',
            'applied_on' => '2026-09-16',
            'location'   => 'Pune, Maharashtra',
            'salary'     => '10 LPA',
            'job_url'    => 'https://careers.example.com/journey',
            'notes'      => 'Created by the route test.',
        ]);
        $this->assertSame(302, $status);

        $location = $this->location($headers);
        $this->assertMatchesRegularExpression('#/view\.php\?id=\d+$#', $location);
        $id = (int) substr($location, strrpos($location, '=') + 1);
        $this->assertGreaterThan(0, $id);

        // --- read ---------------------------------------------------------
        [$status, $body] = $this->request('GET', "/view.php?id=$id");
        $this->assertSame(200, $status);
        $this->assertStringContainsString($company, $body);
        $this->assertStringContainsString('Backend Intern', $body);
        $this->assertStringContainsString('Pune, Maharashtra', $body);
        $this->assertStringContainsString('10 LPA', $body);

        // --- update -------------------------------------------------------
        [$status, $body] = $this->request('GET', "/edit.php?id=$id");
        $this->assertSame(200, $status);
        $this->assertStringContainsString($company, $body, 'The edit form should be pre-filled.');

        $token = $this->token("/edit.php?id=$id");

        [$status] = $this->request('POST', "/edit.php?id=$id", [
            'csrf'       => $token,
            'company'    => $company,
            'role'       => 'Backend Intern',
            'status'     => 'Interview',
            'applied_on' => '2026-09-16',
            'location'   => 'Pune, Maharashtra',
            'salary'     => '10 LPA',
            'job_url'    => 'https://careers.example.com/journey',
            'notes'      => 'Status changed by the route test.',
        ]);
        $this->assertSame(302, $status);

        [$status, $body] = $this->request('GET', "/view.php?id=$id");
        $this->assertSame(200, $status);
        $this->assertStringContainsString('Interview', $body, 'The new status should be saved.');

        // --- delete -------------------------------------------------------
        $token = $this->token("/view.php?id=$id");

        [$status] = $this->request('POST', '/delete.php', [
            'csrf' => $token,
            'id'   => $id,
        ]);
        $this->assertSame(302, $status);

        $this->assertSame(404, $this->request('GET', "/view.php?id=$id")[0], 'The record should be gone.');
    }

    public function testAnInvalidFormReturns422AndKeepsTheValues(): void
    {
        $token = $this->token('/new.php');

        [$status, $body] = $this->request('POST', '/new.php', [
            'csrf'       => $token,
            'company'    => 'A',
            'role'       => '',
            'status'     => 'Pending',
            'applied_on' => '2026-02-30',
        ]);

        $this->assertSame(422, $status);
        $this->assertStringContainsString('Company must be between 2 and 100 characters', $body);
        $this->assertStringContainsString('value="A"', $body);
    }

    public function testOutputIsEncoded(): void
    {
        $token   = $this->token('/new.php');
        $company = '<script>alert(1)</script>';

        $this->request('POST', '/new.php', [
            'csrf'       => $token,
            'company'    => $company,
            'role'       => 'Tester',
            'status'     => 'Applied',
            'applied_on' => '2026-09-16',
        ]);

        [, $body] = $this->request('GET', '/applications.php?q=' . rawurlencode($company));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body, 'The script tag must be encoded.');
        $this->assertStringContainsString('&lt;script&gt;', $body);

        // Clean up the record that was just created.
        $userId = $this->ensureTestUser();
        $stmt   = db()->prepare('DELETE FROM applications WHERE user_id = ? AND company = ?');
        $stmt->execute([$userId, $company]);
    }

    public function testTheApiEndpointAnswersJson(): void
    {
        $raw = $this->request('GET', '/api/applications.php?q=Test');

        $this->assertSame(200, $raw[0]);
        $this->assertStringContainsString('application/json', $raw[2]);

        $data = json_decode($raw[1], true);
        $this->assertIsArray($data);
        $this->assertTrue($data['ok']);
        $this->assertArrayHasKey('table_html', $data);
        $this->assertArrayHasKey('pagination_html', $data);
        $this->assertArrayHasKey('summary_html', $data);
    }

    public function testTheApiEndpointRefusesAGuest(): void
    {
        [$status] = $this->asGuest(fn () => $this->request('GET', '/api/applications.php'));
        $this->assertSame(401, $status);
    }
}
