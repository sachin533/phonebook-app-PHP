<?php
/**
 * tests/ValidationTest.php - every validation rule, one test at a time.
 *
 * These tests are pure: they call validate_application() with an array and
 * check what comes back. No database and no web server are needed.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    /**
     * A set of values that passes validation. Individual tests change one
     * field at a time.
     *
     * @param array<string,string> $overrides
     * @return array<string,string>
     */
    private function valid(array $overrides = []): array
    {
        return $overrides + [
            'company'    => 'Acme Labs',
            'role'       => 'Software Intern',
            'status'     => 'Applied',
            'applied_on' => '2026-09-16',
            'job_url'    => 'https://acme.example/careers',
            'notes'      => 'Applied through the careers page.',
        ];
    }

    /**
     * Run the validator and return only the error map.
     *
     * @param array<string,string> $overrides
     * @return array<string,string>
     */
    private function errors(array $overrides = []): array
    {
        return validate_application($this->valid($overrides))[1];
    }

    // ---- the happy path ----------------------------------------------------

    public function testValidInputHasNoErrors(): void
    {
        $this->assertSame([], $this->errors());
    }

    public function testValidInputReturnsTheCleanedValues(): void
    {
        [$data, $errors] = validate_application($this->valid());

        $this->assertSame([], $errors);
        $this->assertSame('Acme Labs', $data['company']);
        $this->assertSame('Software Intern', $data['role']);
        $this->assertSame('Applied', $data['status']);
        $this->assertSame('2026-09-16', $data['applied_on']);
    }

    public function testOptionalEmptyFieldsBecomeNull(): void
    {
        [$data] = validate_application($this->valid([
            'status'     => 'Wishlist',
            'applied_on' => '',
            'job_url'    => '',
            'notes'      => '',
        ]));

        $this->assertNull($data['applied_on']);
        $this->assertNull($data['job_url']);
        $this->assertNull($data['notes']);
    }

    public function testValuesAreTrimmed(): void
    {
        [$data] = validate_application($this->valid(['company' => '  Acme Labs  ']));

        $this->assertSame('Acme Labs', $data['company']);
    }

    // ---- company and role --------------------------------------------------

    public function testCompanyIsRequired(): void
    {
        $this->assertArrayHasKey('company', $this->errors(['company' => '']));
    }

    public function testCompanyMustBeAtLeastTwoCharacters(): void
    {
        $this->assertArrayHasKey('company', $this->errors(['company' => 'A']));
        $this->assertArrayNotHasKey('company', $this->errors(['company' => 'AB']));
    }

    public function testCompanyMustNotExceedOneHundredCharacters(): void
    {
        $this->assertArrayNotHasKey('company', $this->errors(['company' => str_repeat('a', 100)]));
        $this->assertArrayHasKey('company', $this->errors(['company' => str_repeat('a', 101)]));
    }

    public function testRoleIsRequired(): void
    {
        $this->assertArrayHasKey('role', $this->errors(['role' => '']));
    }

    public function testRoleMustBeAtLeastTwoCharacters(): void
    {
        $this->assertArrayHasKey('role', $this->errors(['role' => 'A']));
        $this->assertArrayNotHasKey('role', $this->errors(['role' => 'AB']));
    }

    public function testRoleMustNotExceedOneHundredCharacters(): void
    {
        $this->assertArrayHasKey('role', $this->errors(['role' => str_repeat('a', 101)]));
    }

    // ---- status ------------------------------------------------------------

    public function testUnknownStatusRejected(): void
    {
        $this->assertArrayHasKey('status', $this->errors(['status' => 'Pending']));
    }

    public function testEmptyStatusRejected(): void
    {
        $this->assertArrayHasKey('status', $this->errors(['status' => '']));
    }

    public function testEveryAllowedStatusIsAccepted(): void
    {
        foreach (STATUSES as $status) {
            $overrides = ['status' => $status];
            if ($status !== 'Wishlist') {
                $overrides['applied_on'] = '2026-09-16';
            }
            $this->assertArrayNotHasKey('status', $this->errors($overrides), $status . ' should be allowed');
        }
    }

    // ---- applied_on --------------------------------------------------------

    public function testDateRequiredUnlessWishlist(): void
    {
        $this->assertArrayHasKey('applied_on', $this->errors(['applied_on' => '']));
        $this->assertSame([], $this->errors(['status' => 'Wishlist', 'applied_on' => '']));
    }

    public function testImpossibleDateIsRejected(): void
    {
        // 2026 is not a leap year, so 29 February does not exist.
        $this->assertArrayHasKey('applied_on', $this->errors(['applied_on' => '2026-02-29']));
        $this->assertArrayHasKey('applied_on', $this->errors(['applied_on' => '2026-02-30']));
        $this->assertArrayHasKey('applied_on', $this->errors(['applied_on' => '2026-13-01']));
    }

    public function testRealDateIsAccepted(): void
    {
        $this->assertArrayNotHasKey('applied_on', $this->errors(['applied_on' => '2026-02-28']));
        // 2024 is a leap year, so 29 February exists.
        $this->assertArrayNotHasKey('applied_on', $this->errors(['applied_on' => '2024-02-29']));
    }

    public function testMalformedDateIsRejected(): void
    {
        $this->assertArrayHasKey('applied_on', $this->errors(['applied_on' => '16/09/2026']));
        $this->assertArrayHasKey('applied_on', $this->errors(['applied_on' => '2026-9-16']));
        $this->assertArrayHasKey('applied_on', $this->errors(['applied_on' => 'yesterday']));
    }

    // ---- job_url -----------------------------------------------------------

    public function testJobUrlMustStartWithHttpOrHttps(): void
    {
        $this->assertArrayHasKey('job_url', $this->errors(['job_url' => 'acme.example/careers']));
        $this->assertArrayHasKey('job_url', $this->errors(['job_url' => 'javascript:alert(1)']));
        $this->assertArrayNotHasKey('job_url', $this->errors(['job_url' => 'http://acme.example']));
        $this->assertArrayNotHasKey('job_url', $this->errors(['job_url' => 'https://acme.example']));
    }

    public function testJobUrlMayBeEmpty(): void
    {
        $this->assertArrayNotHasKey('job_url', $this->errors(['job_url' => '']));
    }

    public function testJobUrlMustNotExceedFiveHundredCharacters(): void
    {
        $prefix = 'https://acme.example/';
        $exact  = $prefix . str_repeat('a', 500 - strlen($prefix));
        $tooLong = $prefix . str_repeat('a', 501 - strlen($prefix));

        $this->assertSame(500, strlen($exact));
        $this->assertArrayNotHasKey('job_url', $this->errors(['job_url' => $exact]));
        $this->assertArrayHasKey('job_url', $this->errors(['job_url' => $tooLong]));
    }

    // ---- notes -------------------------------------------------------------

    public function testNotesMustNotExceedOneThousandCharacters(): void
    {
        $this->assertArrayNotHasKey('notes', $this->errors(['notes' => str_repeat('n', 1000)]));
        $this->assertArrayHasKey('notes', $this->errors(['notes' => str_repeat('n', 1001)]));
    }

    public function testNotesMayBeEmpty(): void
    {
        $this->assertArrayNotHasKey('notes', $this->errors(['notes' => '']));
    }

    // ---- the helper predicates --------------------------------------------

    public function testIsIsoDateAcceptsRealDatesOnly(): void
    {
        $this->assertTrue(is_iso_date('2026-09-16'));
        $this->assertTrue(is_iso_date('2024-02-29'));
        $this->assertFalse(is_iso_date('2026-02-29'));
        $this->assertFalse(is_iso_date('2026-9-6'));
        $this->assertFalse(is_iso_date(''));
    }

    public function testHasHttpSchemeAcceptsBothSchemes(): void
    {
        $this->assertTrue(has_http_scheme('http://a.example'));
        $this->assertTrue(has_http_scheme('https://a.example'));
        $this->assertTrue(has_http_scheme('HTTPS://a.example'));
        $this->assertFalse(has_http_scheme('ftp://a.example'));
        $this->assertFalse(has_http_scheme('a.example'));
    }

    // ---- non-ASCII input ---------------------------------------------------

    public function testNonAsciiCompanyAndNotesAreAccepted(): void
    {
        $this->assertArrayNotHasKey('company', $this->errors(['company' => 'Infosys']));
        // Length is counted in characters, not bytes, so emoji count as one.
        $this->assertArrayNotHasKey('notes', $this->errors(['notes' => str_repeat('🎯', 1000)]));
    }

    // ---- location and salary -----------------------------------------------

    public function testLocationIsOptional(): void
    {
        $this->assertArrayNotHasKey('location', $this->errors(['location' => '']));
        $this->assertArrayNotHasKey('location', $this->errors(['location' => 'Bengaluru, Karnataka']));
    }

    public function testLocationMustNotExceedOneHundredAndTwentyCharacters(): void
    {
        $this->assertArrayNotHasKey('location', $this->errors(['location' => str_repeat('a', 120)]));
        $this->assertArrayHasKey('location', $this->errors(['location' => str_repeat('a', 121)]));
    }

    public function testSalaryIsOptional(): void
    {
        $this->assertArrayNotHasKey('salary', $this->errors(['salary' => '']));
        $this->assertArrayNotHasKey('salary', $this->errors(['salary' => '12 LPA']));
    }

    public function testSalaryMustNotExceedFiftyCharacters(): void
    {
        $this->assertArrayNotHasKey('salary', $this->errors(['salary' => str_repeat('a', 50)]));
        $this->assertArrayHasKey('salary', $this->errors(['salary' => str_repeat('a', 51)]));
    }

    public function testTheNewFieldsComeBackCleaned(): void
    {
        [$data] = validate_application($this->valid([
            'location' => '  Chennai, Tamil Nadu  ',
            'salary'   => '  8 LPA  ',
        ]));

        $this->assertSame('Chennai, Tamil Nadu', $data['location']);
        $this->assertSame('8 LPA', $data['salary']);
    }

    public function testEmptyNewFieldsBecomeNull(): void
    {
        [$data] = validate_application($this->valid(['location' => '', 'salary' => '']));

        $this->assertNull($data['location']);
        $this->assertNull($data['salary']);
    }

    // ---- the sign up rules --------------------------------------------------

    public function testRegistrationNeedsAName(): void
    {
        $this->assertArrayHasKey('name', $this->registrationErrors(['name' => '']));
        $this->assertArrayNotHasKey('name', $this->registrationErrors(['name' => 'Shubham Aran']));
    }

    public function testRegistrationNeedsAValidEmail(): void
    {
        $this->assertArrayHasKey('email', $this->registrationErrors(['email' => '']));
        $this->assertArrayHasKey('email', $this->registrationErrors(['email' => 'not-an-email']));
        $this->assertArrayNotHasKey('email', $this->registrationErrors(['email' => 'ada@example.com']));
    }

    public function testRegistrationNeedsAUsableUsername(): void
    {
        $this->assertArrayHasKey('username', $this->registrationErrors(['username' => '']));
        $this->assertArrayHasKey('username', $this->registrationErrors(['username' => 'ab']));
        $this->assertArrayHasKey('username', $this->registrationErrors(['username' => 'has spaces']));
        $this->assertArrayNotHasKey('username', $this->registrationErrors(['username' => 'ada.lovelace_99']));
    }

    public function testRegistrationNeedsAStrongEnoughPassword(): void
    {
        $this->assertArrayHasKey('password', $this->registrationErrors(['password' => 'short1']));
        $this->assertArrayHasKey('password', $this->registrationErrors(['password' => 'nodigitshere']));
        $this->assertArrayHasKey('password', $this->registrationErrors(['password' => '12345678']));
        $this->assertArrayNotHasKey('password', $this->registrationErrors(['password' => 'Placement2026']));
    }

    public function testRegistrationNeedsTheTwoPasswordsToMatch(): void
    {
        $errors = $this->registrationErrors(['password_confirm' => 'SomethingElse9']);
        $this->assertArrayHasKey('password_confirm', $errors);
    }

    public function testTheEmailIsLowercased(): void
    {
        [$data] = validate_registration($this->registrationInput(['email' => '  Ada@Example.COM ']));
        $this->assertSame('ada@example.com', $data['email']);
    }

    /**
     * The registration input, with the password and its confirmation filled
     * in so only the field under test is missing.
     *
     * @param array<string,string> $overrides
     * @return array<string,string>
     */
    private function registrationInput(array $overrides = []): array
    {
        return $overrides + [
            'name'             => 'Ada Lovelace',
            'email'            => 'ada@example.com',
            'username'         => 'ada',
            'password'         => 'Placement2026',
            'password_confirm' => 'Placement2026',
        ];
    }

    /**
     * @param array<string,string> $overrides
     * @return array<string,string>
     */
    private function registrationErrors(array $overrides = []): array
    {
        return validate_registration($this->registrationInput($overrides))[1];
    }

    // ---- the sign in rules --------------------------------------------------

    public function testSignInNeedsAnIdentifierAndAPassword(): void
    {
        $errors = validate_login(['identifier' => '', 'password' => ''])[1];
        $this->assertArrayHasKey('identifier', $errors);
        $this->assertArrayHasKey('password', $errors);
    }

    public function testSignInWithBothFieldsHasNoErrors(): void
    {
        $errors = validate_login(['identifier' => 'ada@example.com', 'password' => 'Placement2026'])[1];
        $this->assertSame([], $errors);
    }
}
