<?php
/**
 * inc/icons.php - inline SVG icons.
 *
 * The icons are drawn here rather than loaded from an icon font or a CDN,
 * so the application has no external dependency and works offline.
 */
declare(strict_types=1);

/**
 * The path data for every icon, drawn on a 24x24 grid.
 */
function icon_paths(): array
{
    return [
        'dashboard'   => '<rect x="3" y="3" width="7" height="9" rx="1.5"></rect><rect x="14" y="3" width="7" height="5" rx="1.5"></rect><rect x="14" y="12" width="7" height="9" rx="1.5"></rect><rect x="3" y="16" width="7" height="5" rx="1.5"></rect>',
        'applications' => '<rect x="2.5" y="7" width="19" height="13.5" rx="2.5"></rect><path d="M8.5 7V5.2A2.2 2.2 0 0 1 10.7 3h2.6a2.2 2.2 0 0 1 2.2 2.2V7"></path><path d="M2.5 12.5h19"></path>',
        'companies'   => '<path d="M3 21h18"></path><path d="M5 21V6.5a1.5 1.5 0 0 1 1.5-1.5h5A1.5 1.5 0 0 1 13 6.5V21"></path><path d="M13 21V11h4.5A1.5 1.5 0 0 1 19 12.5V21"></path><path d="M8 9h2M8 13h2M8 17h2M15.5 15h1M15.5 18h1"></path>',
        'statistics'  => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"></path>',
        'profile'     => '<circle cx="12" cy="8" r="4"></circle><path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path>',
        'logout'      => '<path d="M9 21H5.5A1.5 1.5 0 0 1 4 19.5v-15A1.5 1.5 0 0 1 5.5 3H9"></path><path d="M16 16l5-4-5-4"></path><path d="M21 12H9"></path>',
        'search'      => '<circle cx="11" cy="11" r="7"></circle><line x1="20.5" y1="20.5" x2="16" y2="16"></line>',
        'bell'        => '<path d="M18 8.5a6 6 0 1 0-12 0c0 6-2.5 7.5-2.5 7.5h17S18 14.5 18 8.5"></path><path d="M13.7 20a2 2 0 0 1-3.4 0"></path>',
        'plus'        => '<line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line>',
        'eye'         => '<path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12Z"></path><circle cx="12" cy="12" r="2.8"></circle>',
        'edit'        => '<path d="M12 20h8"></path><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path>',
        'trash'       => '<path d="M3.5 6.5h17"></path><path d="M8 6.5V5a1.5 1.5 0 0 1 1.5-1.5h5A1.5 1.5 0 0 1 16 5v1.5"></path><path d="M18.5 6.5 17.7 19a1.5 1.5 0 0 1-1.5 1.4H7.8a1.5 1.5 0 0 1-1.5-1.4L5.5 6.5"></path>',
        'chevron-left'  => '<polyline points="15 18 9 12 15 6"></polyline>',
        'chevron-right' => '<polyline points="9 18 15 12 9 6"></polyline>',
        'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2.5"></rect><path d="M3 10h18"></path><path d="M8 3v4M16 3v4"></path>',
        'location'    => '<path d="M20 10.5c0 5.5-8 11-8 11s-8-5.5-8-11a8 8 0 1 1 16 0Z"></path><circle cx="12" cy="10.3" r="2.8"></circle>',
        'money'       => '<rect x="2.5" y="6" width="19" height="12" rx="2.5"></rect><circle cx="12" cy="12" r="2.8"></circle><path d="M6 10v4M18 10v4"></path>',
        'close'       => '<line x1="6" y1="6" x2="18" y2="18"></line><line x1="18" y1="6" x2="6" y2="18"></line>',
        'check'       => '<polyline points="20 6 9 17 4 12"></polyline>',
        'warning'     => '<path d="M10.3 3.9 1.9 18a2 2 0 0 0 1.7 3h16.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"></path><line x1="12" y1="9" x2="12" y2="13.5"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
        'filter'      => '<polygon points="21 4 3 4 10 12.5 10 19 14 21 14 12.5 21 4"></polygon>',
        'menu'        => '<line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="18" x2="21" y2="18"></line>',
        'briefcase'   => '<rect x="2.5" y="7" width="19" height="13.5" rx="2.5"></rect><path d="M8.5 7V5.2A2.2 2.2 0 0 1 10.7 3h2.6a2.2 2.2 0 0 1 2.2 2.2V7"></path>',
        'arrow-up'    => '<line x1="12" y1="19" x2="12" y2="5"></line><polyline points="6 11 12 5 18 11"></polyline>',
        'arrow-down'  => '<line x1="12" y1="5" x2="12" y2="19"></line><polyline points="6 13 12 19 18 13"></polyline>',
        'mail'        => '<rect x="2.5" y="4.5" width="19" height="15" rx="2.5"></rect><polyline points="3 7 12 13 21 7"></polyline>',
        'lock'        => '<rect x="4" y="10.5" width="16" height="10.5" rx="2.5"></rect><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"></path>',
        'user'        => '<circle cx="12" cy="8" r="4"></circle><path d="M4.5 21a7.5 7.5 0 0 1 15 0"></path>',
        'external'    => '<path d="M18 13.5V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h5.5"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line>',
    ];
}

/**
 * Draw an icon.
 *
 * @param string $name  The icon name.
 * @param int    $size  The width and height in pixels.
 * @param string $class Extra CSS classes.
 */
function icon(string $name, int $size = 18, string $class = ''): string
{
    $paths = icon_paths();
    if (!isset($paths[$name])) {
        return '';
    }
    return '<svg class="icon ' . e($class) . '" width="' . $size . '" height="' . $size . '" '
         . 'viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" '
         . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
         . $paths[$name]
         . '</svg>';
}
