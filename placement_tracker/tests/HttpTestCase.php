<?php
/**
 * tests/HttpTestCase.php - the shared base class for the HTTP tests.
 *
 * It knows how to talk to a running server with curl, how to keep the
 * session cookie, how to read the CSRF token out of a form and how to sign
 * in.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

abstract class HttpTestCase extends TestCase
{
    protected string $baseUrl;
    protected string $cookieFile;

    /** The account the HTTP tests sign in with. */
    protected const TEST_EMAIL    = 'route.test@example.com';
    protected const TEST_USERNAME = 'routetest';
    protected const TEST_PASSWORD = 'Route-Test-9';

    protected function setUp(): void
    {
        $this->baseUrl    = rtrim((string) (getenv('APP_BASE_URL') ?: 'http://127.0.0.1:8000'), '/');
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'pt_cookies_');

        $this->guardAgainstTheRealDatabase();
    }

    /**
     * The tests write records and accounts, so they must never run against
     * the database the real site uses.
     */
    private function guardAgainstTheRealDatabase(): void
    {
        $name = (string) config('DB_NAME');
        if ($name === '' || str_ends_with($name, '_test')) {
            return;
        }

        $this->fail(
            "These tests write to the database, so they must not run against '$name'." . PHP_EOL .
            'Point them at the test database:' . PHP_EOL .
            '    $env:DB_NAME = "placement_tracker_test"' . PHP_EOL .
            '    $env:DB_PORT = "3306"' . PHP_EOL .
            'and start the server with the same DB_NAME.'
        );
    }

    protected function tearDown(): void
    {
        if ($this->cookieFile !== '' && is_file($this->cookieFile)) {
            unlink($this->cookieFile);
        }
    }

    /**
     * Send a request and return [status, body, headers].
     *
     * Redirects are NOT followed: the test needs to see the 302 itself.
     *
     * @param array<string,string> $data Form fields for a POST.
     * @return array{0:int,1:string,2:string}
     */
    protected function request(string $method, string $path, array $data = []): array
    {
        $ch = curl_init($this->baseUrl . $path);
        if ($ch === false) {
            throw new RuntimeException('Could not start curl.');
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR      => $this->cookieFile,
            CURLOPT_COOKIEFILE     => $this->cookieFile,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $raw        = curl_exec($ch);
        $status     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error      = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            throw new RuntimeException(
                sprintf('Request %s %s failed: %s. Is the server running on %s?', $method, $path, $error, $this->baseUrl)
            );
        }

        $raw = (string) $raw;

        return [$status, substr($raw, $headerSize), substr($raw, 0, $headerSize)];
    }

    /**
     * Read the CSRF token out of a rendered form.
     */
    protected function token(string $path): string
    {
        [$status, $body] = $this->request('GET', $path);
        $this->assertSame(200, $status, "GET $path should return 200");

        if (!preg_match('/name="csrf" value="([a-f0-9]{64})"/', $body, $matches)) {
            $this->fail("No CSRF token found in $path");
        }
        return $matches[1];
    }

    /**
     * The Location header of a response.
     */
    protected function location(string $headers): string
    {
        if (preg_match('/^Location:\s*(.+)$/mi', $headers, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * Make sure the test account exists in the database.
     */
    protected function ensureTestUser(): int
    {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([self::TEST_EMAIL]);
        $id = $stmt->fetchColumn();

        if ($id !== false) {
            return (int) $id;
        }

        $stmt = db()->prepare(
            'INSERT INTO users (name, email, username, password_hash)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            'Route Test',
            self::TEST_EMAIL,
            self::TEST_USERNAME,
            password_hash(self::TEST_PASSWORD, PASSWORD_DEFAULT),
        ]);

        return (int) db()->lastInsertId();
    }

    /**
     * Sign in over HTTP and keep the session cookie.
     */
    protected function signIn(): void
    {
        $this->ensureTestUser();

        $token = $this->token('/login.php');
        [$status, , $headers] = $this->request('POST', '/login.php', [
            'csrf'       => $token,
            'identifier' => self::TEST_EMAIL,
            'password'   => self::TEST_PASSWORD,
        ]);

        $this->assertSame(302, $status, 'Signing in should redirect.');
    }

    /**
     * Run something with a brand new session, so it is a guest.
     *
     * @param callable $fn
     * @return mixed
     */
    protected function asGuest(callable $fn)
    {
        $original = $this->cookieFile;
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'pt_guest_');

        try {
            return $fn();
        } finally {
            @unlink($this->cookieFile);
            $this->cookieFile = $original;
        }
    }

    /**
     * Remove every record that belongs to the test account.
     */
    protected function clearTestRecords(): void
    {
        $id = $this->ensureTestUser();
        $stmt = db()->prepare('DELETE FROM applications WHERE user_id = ?');
        $stmt->execute([$id]);
    }

    /**
     * Insert a record for the test account.
     *
     * @param array<string,mixed> $fields
     */
    protected function makeApplication(array $fields = []): int
    {
        $id = $this->ensureTestUser();

        $row = $fields + [
            'company'    => 'Test Co',
            'role'       => 'Software Engineer',
            'status'     => 'Applied',
            'applied_on' => '2026-09-16',
            'location'   => 'Bengaluru, Karnataka',
            'salary'     => '12 LPA',
            'job_url'    => 'https://careers.example.com/test',
            'notes'      => 'Created by the test suite.',
        ];

        $stmt = db()->prepare(
            'INSERT INTO applications (user_id, company, role, status, applied_on, location, salary, job_url, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $id, $row['company'], $row['role'], $row['status'], $row['applied_on'],
            $row['location'], $row['salary'], $row['job_url'], $row['notes'],
        ]);

        return (int) db()->lastInsertId();
    }
}
