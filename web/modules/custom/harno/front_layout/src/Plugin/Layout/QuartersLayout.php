<?php

declare(strict_types = 1);

namespace Drupal\front_layout\Plugin\Layout;

use Drupal\front_layout\DemoLayout;

/**
 * Provides a plugin class for one column layouts.
 */
final class QuartersLayout extends LayoutBase {

  /**
   * {@inheritdoc}
   */
  protected function getColumnWidths(): array {
    return [
      DemoLayout::ROW_WIDTH_25_25_25_25 => $this->t('25%'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultColumnWidth(): string {
    return DemoLayout::ROW_WIDTH_25_25_25_25;
  }
}
