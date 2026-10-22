<?php
/**
 * inc/charts.php - the small SVG charts.
 *
 * The charts are drawn by the server as plain SVG. There is no charting
 * library, so nothing has to be downloaded and the charts work without
 * JavaScript.
 */
declare(strict_types=1);

/**
 * The colour used for each status, in the order the legend shows them.
 *
 * @return array<string,string>
 */
function status_colors(): array
{
    return [
        'Applied'   => '#2563eb',
        'Interview' => '#f59e0b',
        'Offer'     => '#22c55e',
        'Rejected'  => '#ef4444',
        'Wishlist'  => '#94a3b8',
    ];
}

/**
 * The donut chart of the status split.
 *
 * @param array<string,int> $counts The number of applications per status.
 */
function render_donut(array $counts): string
{
    $total  = array_sum($counts);
    $colors = status_colors();
    $radius = 60;
    $circumference = 2 * M_PI * $radius;

    ob_start();
    ?>
    <div class="donut-wrap">
      <div class="donut">
        <svg viewBox="0 0 160 160" width="176" height="176" role="img"
             aria-label="Applications by status: <?= e((string) $total) ?> in total">
          <circle cx="80" cy="80" r="<?= e((string) $radius) ?>" stroke="#eef2f7"></circle>
          <?php
          $offset = 0.0;
          foreach ($colors as $status => $color):
              $n = (int) ($counts[$status] ?? 0);
              if ($n === 0 || $total === 0) {
                  continue;
              }
              $length = $circumference * ($n / $total);
              ?>
              <circle cx="80" cy="80" r="<?= e((string) $radius) ?>"
                      stroke="<?= e($color) ?>"
                      stroke-dasharray="<?= e((string) round($length, 2)) ?> <?= e((string) round($circumference - $length, 2)) ?>"
                      stroke-dashoffset="<?= e((string) round(-$offset, 2)) ?>"></circle>
              <?php
              $offset += $length;
          endforeach; ?>
        </svg>
        <div class="donut-center">
          <span class="donut-total"><?= e((string) $total) ?></span>
          <span class="donut-caption">Total</span>
        </div>
      </div>

      <ul class="chart-legend">
        <?php foreach ($colors as $status => $color): ?>
          <?php $n = (int) ($counts[$status] ?? 0); ?>
          <li>
            <span class="swatch" style="background:<?= e($color) ?>"></span>
            <span class="legend-name"><?= e($status) ?></span>
            <span class="legend-count"><?= e((string) $n) ?></span>
            <span class="legend-pct"><?= $total > 0 ? e((string) round($n / $total * 100)) . '%' : '0%' ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * The bar chart of applications per month.
 *
 * @param array<int,array{month:string,label:string,n:int}> $months
 */
function render_bar_chart(array $months): string
{
    $max = 0;
    foreach ($months as $month) {
        $max = max($max, (int) $month['n']);
    }
    $max = max($max, 1);

    ob_start();
    ?>
    <div class="bar-chart">
      <?php foreach ($months as $month): ?>
        <?php $n = (int) $month['n']; ?>
        <div class="bar-col">
          <span class="bar-value"><?= e((string) $n) ?></span>
          <div class="bar-track">
            <div class="bar" style="height:<?= e((string) round($n / $max * 100, 2)) ?>%"
                 title="<?= e((string) $n) ?> application(s) in <?= e((string) $month['label']) ?>"></div>
          </div>
          <span class="bar-label"><?= e((string) $month['label']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

/**
 * A progress bar for a share of the total.
 */
function render_share_bar(int $value, int $total, string $color): string
{
    $percent = $total > 0 ? round($value / $total * 100, 1) : 0;
    return '<div class="progress-line"><span style="width:' . e((string) $percent) . '%;background:' . e($color) . '"></span></div>';
}
