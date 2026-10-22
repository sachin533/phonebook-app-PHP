<?php
/**
 * tests/AuthTest.php - signing in, signing out and staying signed in.
 */
declare(strict_types=1);

final class AuthTest extends HttpTestCase
{
    public function testGuestIsSentToTheLoginPage(): void
    {
        foreach (['/dashboard.php', '/applications.php', '/companies.php', '/statistics.php', '/profile.php', '/new.php'] as $path) {
            [$status, , $headers] = $this->request('GET', $path);
            $this->assertSame(302, $status, "$path should redirect a guest");
            $this->assertStringContainsString('/login.php', $this->location($headers));
        }
    }

    public function testTheLoginPageIsReachable(): void
    {
        [$status, $body] = $this->request('GET', '/login.php');
        $this->assertSame(200, $status);
        $this->assertStringContainsString('name="identifier"', $body);
        $this->assertStringContainsString('type="password"', $body);
    }

    public function testTheLoginPageHasNoFakeGoogleButton(): void
    {
        [, $body] = $this->request('GET', '/login.php');
        $this->assertStringNotContainsString('Sign in with Google', $body);
        $this->assertStringNotContainsString('google', strtolower($body));
    }

    public function testAWrongPasswordIsRejected(): void
    {
        $this->ensureTestUser();

        $token = $this->token('/login.php');
        [$status, $body] = $this->request('POST', '/login.php', [
            'csrf'       => $token,
            'identifier' => self::TEST_EMAIL,
            'password'   => 'not-the-password',
        ]);

        $this->assertSame(422, $status);
        $this->assertStringContainsString('not right', $body);

        // The session must not have been created.
        [$status] = $this->request('GET', '/dashboard.php');
        $this->assertSame(302, $status, 'A failed sign in must not sign anybody in.');
    }

    public function testTheUsernameAlsoWorks(): void
    {
        $this->ensureTestUser();

        $token = $this->token('/login.php');
        [$status] = $this->request('POST', '/login.php', [
            'csrf'       => $token,
            'identifier' => self::TEST_USERNAME,
            'password'   => self::TEST_PASSWORD,
        ]);

        $this->assertSame(302, $status);
    }

    public function testSigningInGivesAccessToTheProtectedPages(): void
    {
        $this->signIn();

        foreach (['/dashboard.php', '/applications.php', '/companies.php', '/statistics.php', '/profile.php', '/new.php'] as $path) {
            [$status] = $this->request('GET', $path);
            $this->assertSame(200, $status, "$path should load once signed in");
        }
    }

    public function testSigningOutEndsTheSession(): void
    {
        $this->signIn();

        $token = $this->token('/dashboard.php');
        [$status] = $this->request('POST', '/logout.php', ['csrf' => $token]);
        $this->assertSame(302, $status);

        [$status] = $this->request('GET', '/dashboard.php');
        $this->assertSame(302, $status, 'The pages should be protected again.');
    }

    public function testAPostWithoutACsrfTokenIsRejected(): void
    {
        $this->signIn();

        [$status] = $this->request('POST', '/new.php', [
            'company' => 'No Token Ltd',
            'role'    => 'Tester',
            'status'  => 'Applied',
        ]);

        $this->assertSame(403, $status);
    }

    public function testThePasswordIsStoredAsAHash(): void
    {
        $email = 'hash.check@example.com';

        $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            $stmt = db()->prepare(
                'INSERT INTO users (name, email, username, password_hash)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute(['Hash Check', $email, 'hashcheck', password_hash('Hash-Check-9', PASSWORD_DEFAULT)]);
            $id = db()->lastInsertId();
        }

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $hash = (string) $stmt->fetchColumn();

        $this->assertNotSame('Hash-Check-9', $hash, 'The plain password must never be stored.');
        $this->assertStringNotContainsString('Hash-Check-9', $hash);
        $this->assertTrue(password_verify('Hash-Check-9', $hash), 'The hash must verify.');
        $this->assertFalse(password_verify('wrong', $hash));

        // Clean up after the test.
        $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }

    public function testRegisteringCreatesAnAccountAndClaimsTheRecords(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $email  = "new.user.$suffix@example.com";
        $user   = "newuser$suffix";

        $token = $this->token('/register.php');
        [$status, , $headers] = $this->request('POST', '/register.php', [
            'csrf'             => $token,
            'name'             => 'New User',
            'email'            => $email,
            'username'         => $user,
            'password'         => 'Register-Me-9',
            'password_confirm' => 'Register-Me-9',
        ]);

        $this->assertSame(302, $status);
        $this->assertStringContainsString('/dashboard.php', $this->location($headers));

        $stmt = db()->prepare('SELECT password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $hash = (string) $stmt->fetchColumn();

        $this->assertNotSame('', $hash);
        $this->assertTrue(password_verify('Register-Me-9', $hash));

        // Clean up after the test.
        $stmt = db()->prepare('DELETE FROM applications WHERE user_id IN (SELECT id FROM users WHERE email = ?)');
        $stmt->execute([$email]);
        $stmt = db()->prepare('DELETE FROM users WHERE email = ?');
        $stmt->execute([$email]);
    }

    public function testRegisteringRefusesADuplicateEmail(): void
    {
        $this->ensureTestUser();

        $token = $this->token('/register.php');
        [$status, $body] = $this->request('POST', '/register.php', [
            'csrf'             => $token,
            'name'             => 'Duplicate',
            'email'            => self::TEST_EMAIL,
            'username'         => 'someoneelse',
            'password'         => 'Register-Me-9',
            'password_confirm' => 'Register-Me-9',
        ]);

        $this->assertSame(422, $status);
        $this->assertStringContainsString('already exists', $body);
    }

    public function testRegisteringRefusesAMismatchedPassword(): void
    {
        $token = $this->token('/register.php');
        [$status, $body] = $this->request('POST', '/register.php', [
            'csrf'             => $token,
            'name'             => 'Mismatch',
            'email'            => 'mismatch@example.com',
            'username'         => 'mismatch',
            'password'         => 'Register-Me-9',
            'password_confirm' => 'Register-Me-8',
        ]);

        $this->assertSame(422, $status);
        $this->assertStringContainsString('do not match', $body);
    }

    public function testOnePersonCannotOpenAnotherPersonsRecord(): void
    {
        $this->signIn();

        // A second account, so the check does not depend on what else
        // happens to be in the database.
        $otherId = $this->makeSecondAccount();

        $stmt = db()->prepare(
            'INSERT INTO applications (user_id, company, role, status, applied_on)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$otherId, 'Someone Else Co', 'Tester', 'Applied', '2026-09-16']);
        $id = (int) db()->lastInsertId();

        [$status] = $this->request('GET', "/view.php?id=$id");
        $this->assertSame(404, $status, 'Another person\'s record must look like it does not exist.');

        // The same for the edit page and the delete endpoint.
        $this->assertSame(404, $this->request('GET', "/edit.php?id=$id")[0]);

        $token = $this->token('/dashboard.php');
        [$status] = $this->request('POST', '/delete.php', ['csrf' => $token, 'id' => $id]);
        $this->assertSame(404, $status, 'Another person\'s record must not be deletable.');

        // The record is still there, owned by the other account.
        $stmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE id = ?');
        $stmt->execute([$id]);
        $this->assertSame(1, (int) $stmt->fetchColumn());

        $this->deleteSecondAccount($otherId);
    }

    /**
     * Create a throwaway account, so a test can check that one person
     * cannot touch another person's records.
     */
    private function makeSecondAccount(): int
    {
        $stmt = db()->prepare(
            'INSERT INTO users (name, email, username, password_hash)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            'Other Person',
            'other.person@example.com',
            'otherperson',
            password_hash('Other-Person-9', PASSWORD_DEFAULT),
        ]);
        return (int) db()->lastInsertId();
    }

    private function deleteSecondAccount(int $id): void
    {
        $stmt = db()->prepare('DELETE FROM applications WHERE user_id = ?');
        $stmt->execute([$id]);
        $stmt = db()->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([$id]);
    }
}
