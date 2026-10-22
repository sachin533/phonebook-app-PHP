<?php
/**
 * tests/LayoutTest.php - guards the shell layout.
 *
 * The application shell is a two column CSS grid: the sidebar in the first
 * column and the page in the second. Every element inside the shell is
 * therefore a grid item, unless it is taken out of the flow.
 *
 * That is easy to get wrong: an element that is only positioned inside a
 * media query takes a grid slot on a wide screen and pushes the whole page
 * onto the next row, below the fold. The dark overlay behind the drawer is
 * the element that caused it, so it is pinned here.
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class LayoutTest extends TestCase
{
    private function css(): string
    {
        $file = __DIR__ . '/../static/styles.css';
        $this->assertFileExists($file);
        return (string) file_get_contents($file);
    }

    /**
     * The rules that are NOT inside a media query.
     */
    private function baseRules(string $css): string
    {
        // Take the media blocks out, so only the always-on rules are left.
        return (string) preg_replace('/@media[^{]*\{(?:[^{}]*\{[^{}]*\}|[^{}]*)*\}/s', '', $css);
    }

    public function testTheDrawerOverlayIsOutOfTheFlowAtEveryWidth(): void
    {
        $base = $this->baseRules($this->css());

        $this->assertMatchesRegularExpression(
            '/\.nav-scrim\s*\{[^}]*position\s*:\s*fixed/s',
            $base,
            '.nav-scrim must be position: fixed outside a media query, otherwise it takes a '
            . 'slot in the app shell grid and pushes the page below the fold.'
        );
    }

    public function testTheShellIsATwoColumnGrid(): void
    {
        $base = $this->baseRules($this->css());

        $this->assertMatchesRegularExpression(
            '/\.app-shell\s*\{[^}]*grid-template-columns\s*:\s*var\(--sidebar-w\)\s+minmax\(0,\s*1fr\)/s',
            $base,
            '.app-shell must be a two column grid: the sidebar and the page.'
        );
    }

    public function testTheShellOnlyHoldsTheSidebarAndThePage(): void
    {
        $file = __DIR__ . '/../inc/header.php';
        $html = (string) file_get_contents($file);

        $this->assertStringContainsString('class="app-shell"', $html);
        $this->assertStringContainsString('class="sidebar"', $html);
        $this->assertStringContainsString('class="main-area"', $html);

        // The overlay is the only other element in the shell, and it is
        // taken out of the flow by the stylesheet.
        $this->assertMatchesRegularExpression(
            '/class="app-shell".*class="sidebar".*class="nav-scrim".*class="main-area"/s',
            $html,
            'The shell holds the sidebar, the overlay and the page.'
        );
    }
}
