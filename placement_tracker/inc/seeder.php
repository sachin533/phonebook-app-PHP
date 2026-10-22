<?php
/**
 * inc/seeder.php - generates the demonstration records.
 *
 * The rows are DEMONSTRATION DATA. The company names are invented, the job
 * links point at example.com and every row is marked with is_sample = 1 and
 * carries a "Sample" badge in the interface. Nothing here claims that a real
 * vacancy exists.
 *
 * Every generated row has a stable fingerprint and the insert uses INSERT
 * IGNORE against a unique index, so running the seeder again never creates
 * a duplicate.
 *
 * This is used by db\seed_sample.php (the command line) and by demo-data.php
 * (the "load sample data" button in the application).
 */
declare(strict_types=1);

/** Software product companies. */
const SAMPLE_COMPANIES_PRODUCT = [
    'Northwind Systems', 'Brightlane Technologies', 'Kaveri Digital', 'Silverpine Software',
    'Trilok Technologies', 'Maplewood Labs', 'Zenith Codeworks', 'Blue Harbour Software',
    'Corvus Technologies', 'Ironvale Solutions', 'Sapphire Logic', 'Halcyon Software',
    'Redwood Computing', 'Quantum Bridge', 'Lumen Software Labs', 'Everline Systems',
    'Cobalt Peak Software', 'Arclight Technologies', 'Greenfield Systems', 'Polarbyte',
    'Starling Software', 'Riverstone Tech', 'Nimbus Software', 'Orionbyte Systems',
    'Clearwater Software', 'Crestline Technologies', 'Pinnacle Logic', 'Aurora Code',
    'Wexford Software', 'Blackpine Technologies', 'Solstice Systems', 'Ironclad Software',
];

/** Startups. */
const SAMPLE_COMPANIES_STARTUP = [
    'Looply', 'Sproutly', 'Dataloom', 'Cartwheel AI', 'Fintrack', 'Mailmint', 'Queuewise',
    'Patchwork Labs', 'Notegrid', 'Breezemint', 'Cratehouse', 'Threadbare', 'Hearthside',
    'Kitefly', 'Parceloop', 'Trellis Labs', 'Peachpine', 'Wavelength', 'Fernpost', 'Kettle',
];

/** IT services and consultancies. */
const SAMPLE_COMPANIES_SERVICES = [
    'Vertex Consulting', 'Nexbridge Solutions', 'Summit Digital Services', 'Orbit Infotech',
    'Crestwave Consulting', 'Pinnacle Tech Services', 'Bluepeak Systems', 'Momentum IT',
    'Elevate Solutions', 'Corevine Technologies', 'Stackbridge Consulting', 'Anchorbyte',
    'Waypoint Infotech', 'Kite & Anchor Tech', 'Northstar Consulting', 'Sable IT Services',
];

/** Banks, finance and other employers. */
const SAMPLE_COMPANIES_FINANCE = [
    'Meridian Bank', 'Ashoka Finance', 'Kaveri Capital', 'Pinnacle Capital', 'Sagar Credit',
    'Trilok Insurance', 'Suryodaya Mutual Fund', 'Havelock Capital', 'Nirvana Payments',
    'Crestline Bank', 'Ganga Finance', 'Everest Capital',
];

/** Product, retail and other employers. */
const SAMPLE_COMPANIES_OTHER = [
    'Greenbasket Retail', 'Swiftcart Logistics', 'HealthBridge Care', 'Eduverse Learning',
    'TravelGlow', 'FoodForest', 'UrbanNest Realty', 'CleanWave Energy', 'Bookworm Media',
    'Streamly Digital', 'FitTrack Health', 'PlayVerse Studios',
];

/** The roles that were applied for. */
const SAMPLE_ROLES = [
    'Software Engineer', 'Java Developer', 'PHP Developer', 'Backend Developer',
    'Frontend Developer', 'Full Stack Developer', 'QA Engineer', 'Data Analyst',
    'Data Engineer', 'DevOps Engineer', 'Mobile Developer', 'Machine Learning Engineer',
    'Software Development Engineer in Test', 'Business Analyst', 'UI/UX Designer',
    'Associate Software Engineer', 'Graduate Engineer Trainee', 'Summer Internship',
    'Winter Internship', 'Post Graduate Developer Trainee', 'Platform Engineer',
    'Site Reliability Engineer', 'Product Engineer', 'Security Engineer',
];

/** Where the jobs were. */
const SAMPLE_LOCATIONS = [
    'Bengaluru, Karnataka', 'Hyderabad, Telangana', 'Pune, Maharashtra', 'Mumbai, Maharashtra',
    'Chennai, Tamil Nadu', 'Delhi NCR', 'Gurugram, Haryana', 'Noida, Uttar Pradesh',
    'Kolkata, West Bengal', 'Ahmedabad, Gujarat', 'Kochi, Kerala', 'Jaipur, Rajasthan',
    'Indore, Madhya Pradesh', 'Visakhapatnam, Andhra Pradesh', 'Remote, India',
    'Coimbatore, Tamil Nadu', 'Thiruvananthapuram, Kerala', 'Bhubaneswar, Odisha',
];

/** The salary on offer. */
const SAMPLE_SALARIES = [
    '6 LPA', '8 LPA', '10 LPA', '12 LPA', '12.5 LPA', '14 LPA', '16 LPA', '18 LPA',
    '20 LPA', '24 LPA', '28 LPA', '32 LPA', '36 LPA', '3.5 LPA (internship)',
    '4.5 LPA (internship)', 'Not disclosed', 'Competitive',
];

/** The notes that were written. */
const SAMPLE_NOTES = [
    'Referred by the campus placement cell. Applied through the careers page.',
    'Online assessment done. Waiting for the interview slot confirmation.',
    'Rejected after the technical round. Keep as a reminder for next year.',
    'Hiring manager mentioned a referral would help, so asked around the alumni group.',
    'The recruiter said the interview is scheduled for next week.',
    'Coding round cleared, moving on to the system design round.',
    'Applied on the company careers page, no referral code.',
    'The job description mentions a hybrid setup with two days in the office.',
    'Had a phone screen with the hiring manager about the project work.',
    'Portal said the application is under review, no update yet.',
    'The role is for the platform team, mostly Go and Kubernetes.',
    'Not applied yet, the team opens applications in the next hiring drive.',
    'Followed up on the portal, still waiting for a response.',
    'Assessment was a take home, submitted within two days.',
    'The internship converts to a full time role after six months.',
    'Went through the alumni referral form.',
];

/**
 * Split a number of rows across the statuses, in the same proportions as a
 * real application funnel: most applications are pending, few turn into
 * offers.
 *
 * @param array<string,int> $weights
 * @return array<string,int>
 */
function distribute_by_weight(int $rows, array $weights): array
{
    $total = array_sum($weights);
    if ($rows <= 0 || $total === 0) {
        return array_fill_keys(array_keys($weights), 0);
    }

    $out  = [];
    $used = 0;
    foreach ($weights as $key => $weight) {
        $share        = (int) floor($rows * $weight / $total);
        $out[$key]    = $share;
        $used         += $share;
    }

    // Hand the remainder to the biggest bucket, so nothing is left over.
    $remainder = $rows - $used;
    if ($remainder > 0) {
        $biggest = array_keys($weights, max($weights))[0];
        $out[$biggest] += $remainder;
    }

    return $out;
}

/**
 * The stable id of a generated row. The same row always gets the same
 * fingerprint, which is what makes the seeder safe to run again.
 */
function sample_fingerprint(string $company, string $role, string $status, ?string $appliedOn): string
{
    return sha1($company . '|' . $role . '|' . $status . '|' . ($appliedOn ?? 'none'));
}

/**
 * Fill an account with demonstration records until it has $targetTotal rows.
 *
 * Rows that are already there are left alone, so the function is safe to
 * call again and again.
 *
 * @return array{inserted:int,skipped:int,total:int}
 */
function seed_sample_records(int $userId, int $targetTotal = 523): array
{
    $targetTotal = max(0, min(5000, $targetTotal));

    $stmt = db()->prepare('SELECT COUNT(*) FROM applications WHERE user_id = ?');
    $stmt->execute([$userId]);
    $existing = (int) $stmt->fetchColumn();

    $toAdd = max(0, $targetTotal - $existing);
    if ($toAdd === 0) {
        return ['inserted' => 0, 'skipped' => 0, 'total' => $existing];
    }

    // The weights come from a real application funnel.
    $quotas = distribute_by_weight($toAdd, [
        'Applied'   => 198,
        'Interview' => 76,
        'Offer'     => 24,
        'Rejected'  => 102,
        'Wishlist'  => 123,
    ]);

    $companies = array_merge(
        SAMPLE_COMPANIES_PRODUCT,
        SAMPLE_COMPANIES_STARTUP,
        SAMPLE_COMPANIES_SERVICES,
        SAMPLE_COMPANIES_FINANCE,
        SAMPLE_COMPANIES_OTHER
    );

    // A fixed seed, so the same rows are generated every time.
    mt_srand(20260921);

    $inserted = 0;
    $skipped  = 0;
    $attempts = 0;
    $maxTries = $toAdd * 25 + 500;

    while ($inserted < $toAdd && $attempts < $maxTries) {
        $attempts++;

        // Pick the status that still needs the most rows.
        $status = null;
        $best   = -1;
        foreach ($quotas as $key => $left) {
            if ($left > $best) {
                $best   = $left;
                $status = $key;
            }
        }
        if ($status === null || $best <= 0) {
            break;
        }

        $company  = $companies[mt_rand(0, count($companies) - 1)];
        $role     = SAMPLE_ROLES[mt_rand(0, count(SAMPLE_ROLES) - 1)];
        $location = SAMPLE_LOCATIONS[mt_rand(0, count(SAMPLE_LOCATIONS) - 1)];
        $salary   = SAMPLE_SALARIES[mt_rand(0, count(SAMPLE_SALARIES) - 1)];
        $note     = SAMPLE_NOTES[mt_rand(0, count(SAMPLE_NOTES) - 1)];

        // A Wishlist row has not been sent yet, so it has no date.
        $appliedOn = $status === 'Wishlist'
            ? null
            : date('Y-m-d', strtotime('-' . mt_rand(0, 400) . ' days'));

        $fingerprint = sample_fingerprint($company, $role, $status, $appliedOn);

        // The job link is an example address, never a real vacancy.
        $jobUrl = 'https://careers.example.com/'
                . strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $company))
                . '/' . strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $role));

        // created_at follows the application date, so the "added on" column
        // tells a sensible story.
        $createdAt = $appliedOn === null
            ? date('Y-m-d H:i:s', strtotime('-' . mt_rand(1, 30) . ' days'))
            : date('Y-m-d H:i:s', strtotime($appliedOn . ' ' . sprintf('%02d:%02d:00', mt_rand(9, 19), mt_rand(0, 59))));

        $stmt = db()->prepare(
            'INSERT IGNORE INTO applications
                (user_id, company, role, status, applied_on, location, salary, job_url, notes, created_at, fingerprint, is_sample)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );
        $stmt->execute([
            $userId, $company, $role, $status, $appliedOn, $location,
            $salary, $jobUrl, $note, $createdAt, $fingerprint,
        ]);

        if ($stmt->rowCount() > 0) {
            $inserted++;
            $quotas[$status] = max(0, $quotas[$status] - 1);
        } else {
            // The fingerprint was already there, so this row is a duplicate.
            $skipped++;
        }
    }

    return ['inserted' => $inserted, 'skipped' => $skipped, 'total' => $existing + $inserted];
}
