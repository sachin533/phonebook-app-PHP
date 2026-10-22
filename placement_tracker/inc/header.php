<?php
/**
 * inc/header.php - the top of every page.
 *
 * Two layouts:
 *   'app'  the sidebar + top bar shell used by every signed-in page
 *   'auth' the split sign in / sign up layout
 *
 * Pages set $title and, for the app layout, $active before including this.
 */
declare(strict_types=1);

require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/list.php';
require_once __DIR__ . '/stats.php';

$layout     = $layout     ?? 'app';
$pageTitle  = $title      ?? 'Placement Tracker';
$active     = $active     ?? '';
$listParams = $listParams ?? [];

// The values the search box and the filters start with. The applications
// page passes them in so the top bar search keeps the other filters.
$searchQ       = (string) ($listParams['q'] ?? '');
$searchStatus  = (string) ($listParams['status'] ?? '');
$searchCompany = (string) ($listParams['company'] ?? '');
$searchFrom    = (string) ($listParams['date_from'] ?? '');
$searchTo      = (string) ($listParams['date_to'] ?? '');
$searchPerPage = (int) ($listParams['per_page'] ?? 10);
$searchSort    = (string) ($listParams['sort'] ?? '');
$searchDir     = (string) ($listParams['dir'] ?? '');

$user = ($layout === 'app') ? current_user() : null;

// The sidebar count and the "needs attention" list are the same on every
// page, so they are worked out once per request.
$sidebarCount   = 0;
$attentionCount = 0;
$attentionList  = [];
if ($layout === 'app' && $user !== null) {
    $userId         = (int) $user['id'];
    $sidebarCount   = total_applications($userId);
    $attentionCount = attention_count($userId);
    $attentionList  = attention_applications($userId);
}
?>
<?php if ($layout === 'auth'): ?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> &middot; Placement Tracker</title>
<link rel="stylesheet" href="/static/styles.css">
</head>
<body>

<div class="auth-page">
  <aside class="auth-aside">
    <div class="auth-brand">
      <span class="brand-mark"><?= icon('briefcase', 19) ?></span>
      Placement Tracker
    </div>

    <h1>Track Your Career Journey</h1>
    <p class="lead">Keep every job and internship application in one place, from the first wishlist entry to the offer letter.</p>

    <ul class="auth-points">
      <li>
        <span class="tick"><?= icon('check', 13) ?></span>
        <span>Manage every application with the status you are really in</span>
      </li>
      <li>
        <span class="tick"><?= icon('check', 13) ?></span>
        <span>Search company, role, location and notes in one keystroke</span>
      </li>
      <li>
        <span class="tick"><?= icon('check', 13) ?></span>
        <span>See the numbers: offers, interviews and everything in between</span>
      </li>
    </ul>

    <div class="auth-art" aria-hidden="true">
      <svg viewBox="0 0 340 200" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect x="8" y="10" width="324" height="180" rx="12" fill="rgba(255,255,255,.14)"/>
        <rect x="8" y="10" width="324" height="30" rx="12" fill="rgba(255,255,255,.16)"/>
        <circle cx="28" cy="25" r="4" fill="rgba(255,255,255,.5)"/>
        <circle cx="42" cy="25" r="4" fill="rgba(255,255,255,.35)"/>
        <circle cx="56" cy="25" r="4" fill="rgba(255,255,255,.22)"/>
        <rect x="26" y="58" width="86" height="60" rx="8" fill="rgba(255,255,255,.2)"/>
        <rect x="38" y="74" width="42" height="7" rx="3.5" fill="rgba(255,255,255,.65)"/>
        <rect x="38" y="90" width="26" height="6" rx="3" fill="rgba(255,255,255,.35)"/>
        <rect x="126" y="58" width="86" height="60" rx="8" fill="rgba(255,255,255,.2)"/>
        <rect x="138" y="74" width="42" height="7" rx="3.5" fill="rgba(255,255,255,.65)"/>
        <rect x="138" y="90" width="26" height="6" rx="3" fill="rgba(255,255,255,.35)"/>
        <rect x="226" y="58" width="86" height="60" rx="8" fill="rgba(255,255,255,.2)"/>
        <rect x="238" y="74" width="42" height="7" rx="3.5" fill="rgba(255,255,255,.65)"/>
        <rect x="238" y="90" width="26" height="6" rx="3" fill="rgba(255,255,255,.35)"/>
        <rect x="26" y="134" width="286" height="38" rx="8" fill="rgba(255,255,255,.14)"/>
        <rect x="42" y="148" width="120" height="10" rx="5" fill="rgba(255,255,255,.4)"/>
        <rect x="240" y="146" width="56" height="14" rx="7" fill="rgba(255,255,255,.6)"/>
      </svg>
    </div>
  </aside>

  <main class="auth-main">
    <div class="auth-card">

<?php else: ?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> &middot; Placement Tracker</title>
<link rel="stylesheet" href="/static/styles.css">
</head>
<body>

<div class="app-shell" id="appShell">
  <aside class="sidebar" id="sidebar">
    <a class="sidebar-brand" href="/dashboard.php">
      <span class="brand-mark"><?= icon('briefcase', 19) ?></span>
      Placement Tracker
    </a>

    <nav class="sidebar-nav" aria-label="Main">
      <div class="nav-label">Menu</div>

      <a class="nav-item<?= $active === 'dashboard' ? ' active' : '' ?>" href="/dashboard.php">
        <?= icon('dashboard', 17) ?> Dashboard
      </a>
      <a class="nav-item<?= $active === 'applications' ? ' active' : '' ?>" href="/applications.php">
        <?= icon('applications', 17) ?> Applications
        <?php if ($sidebarCount > 0): ?>
          <span class="nav-badge"><?= e((string) $sidebarCount) ?></span>
        <?php endif; ?>
      </a>
      <a class="nav-item<?= $active === 'companies' ? ' active' : '' ?>" href="/companies.php">
        <?= icon('companies', 17) ?> Companies
      </a>
      <a class="nav-item<?= $active === 'statistics' ? ' active' : '' ?>" href="/statistics.php">
        <?= icon('statistics', 17) ?> Statistics
      </a>

      <div class="nav-label">Account</div>

      <a class="nav-item<?= $active === 'profile' ? ' active' : '' ?>" href="/profile.php">
        <?= icon('profile', 17) ?> Profile
      </a>
      <a class="nav-item" href="/logout.php">
        <?= icon('logout', 17) ?> Logout
      </a>
    </nav>

    <div class="sidebar-footer">
      <a class="sidebar-user" href="/profile.php">
        <span class="avatar"><?= e(initials((string) ($user['name'] ?? '?'))) ?></span>
        <span style="min-width:0">
          <span class="sidebar-user-name"><?= e((string) ($user['name'] ?? '')) ?></span>
          <span class="sidebar-user-mail"><?= e((string) ($user['email'] ?? '')) ?></span>
        </span>
      </a>
    </div>
  </aside>

  <div class="nav-scrim" data-nav-close></div>

  <div class="main-area">
    <header class="topbar">
      <button type="button" class="icon-button sidebar-toggle" data-nav-toggle aria-label="Open the menu">
        <?= icon('menu', 19) ?>
      </button>

      <form class="topbar-search<?= $searchQ !== '' ? ' has-value' : '' ?>" method="get" action="/applications.php" role="search" data-search-form>
        <span class="search-icon"><?= icon('search', 16) ?></span>
        <label class="visually-hidden" for="topbar-search-input">Search applications</label>
        <input type="search" id="topbar-search-input" name="q" data-search-input
               placeholder="Search applications, companies, roles..."
               value="<?= e($searchQ) ?>" autocomplete="off">
        <button type="button" class="search-clear" data-search-clear aria-label="Clear the search">
          <?= icon('close', 14) ?>
        </button>

        <?php /* The other filters survive a search from the top bar. */ ?>
        <input type="hidden" name="status" value="<?= e($searchStatus) ?>">
        <input type="hidden" name="company" value="<?= e($searchCompany) ?>">
        <input type="hidden" name="date_from" value="<?= e($searchFrom) ?>">
        <input type="hidden" name="date_to" value="<?= e($searchTo) ?>">
        <input type="hidden" name="per_page" value="<?= e((string) $searchPerPage) ?>">
        <input type="hidden" name="sort" value="<?= e($searchSort) ?>">
        <input type="hidden" name="dir" value="<?= e($searchDir) ?>">
      </form>

      <div class="topbar-actions">
        <div class="profile-menu" id="noticeMenu">
          <button type="button" class="icon-button" data-menu-toggle aria-label="Items that need attention" title="Needs attention">
            <?= icon('bell', 18) ?>
            <?php if ($attentionCount > 0): ?>
              <span class="dot"></span>
            <?php endif; ?>
          </button>

          <div class="profile-menu-list" style="min-width:290px">
            <div class="profile-menu-head">
              <strong>Needs attention</strong>
              <span><?= e((string) $attentionCount) ?> application(s) waiting on you</span>
            </div>
            <div class="menu-sep"></div>
            <?php if ($attentionList === []): ?>
              <div style="padding:10px;color:var(--muted);font-size:13px">Nothing needs your attention right now.</div>
            <?php else: ?>
              <?php foreach ($attentionList as $item): ?>
                <a href="/view.php?id=<?= e((string) $item['id']) ?>">
                  <?= icon('bell', 15) ?>
                  <span style="min-width:0">
                    <span style="display:block;font-weight:600;color:var(--ink)"><?= e((string) $item['company']) ?></span>
                    <span style="display:block;color:var(--muted);font-size:12px"><?= e((string) $item['role']) ?> &middot; <?= e((string) $item['status']) ?></span>
                  </span>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <span class="topbar-divider"></span>

        <div class="profile-menu" id="userMenu">
          <button type="button" class="profile-trigger" data-menu-toggle aria-haspopup="true" aria-expanded="false">
            <span class="avatar"><?= e(initials((string) ($user['name'] ?? '?'))) ?></span>
            <span class="profile-text">
              <span class="profile-name"><?= e((string) ($user['name'] ?? '')) ?></span>
              <span class="profile-role"><?= e((string) ($user['email'] ?? '')) ?></span>
            </span>
            <svg class="icon" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </button>

          <ul class="profile-menu-list">
            <li class="profile-menu-head">
              <strong><?= e((string) ($user['name'] ?? '')) ?></strong>
              <span><?= e((string) ($user['email'] ?? '')) ?></span>
            </li>
            <li class="menu-sep"></li>
            <li><a href="/profile.php"><?= icon('profile', 16) ?> Profile</a></li>
            <li><a href="/profile.php#password"><?= icon('lock', 16) ?> Change password</a></li>
            <li class="menu-sep"></li>
            <li>
              <form method="post" action="/logout.php">
                <?= csrf_field() ?>
                <button type="submit"><?= icon('logout', 16) ?> Log out</button>
              </form>
            </li>
          </ul>
        </div>
      </div>
    </header>

    <main class="content<?= $contentClass ?? '' ?>">
      <?php $flash = take_flash(); ?>
      <?php if (($flash['message'] ?? '') !== ''): ?>
        <div class="alert alert-<?= e((string) ($flash['type'] ?? 'success')) ?>" role="status">
          <?= icon(($flash['type'] ?? 'success') === 'error' ? 'warning' : 'check', 17) ?>
          <span><?= e((string) $flash['message']) ?></span>
        </div>
      <?php endif; ?>

<?php endif; ?>
