<?php

declare(strict_types = 1);

namespace Drupal\front_layout\Plugin\Layout;

use Drupal\front_layout\DemoLayout;

/**
 * Provides a plugin class for one column layouts.
 */
final class threeColumns extends LayoutBase {

  /**
   * {@inheritdoc}
   */
  protected function getColumnWidths(): array {
    return [
      DemoLayout::ROW_WIDTH_50_25_25 => $this->t('50% / 25% / 25%'),
      DemoLayout::ROW_WIDTH_25_25_50 => $this->t('25% / 25% / 50%'),
      DemoLayout::ROW_WIDTH_25_50_25=> $this->t('25% / 50% / 25%'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function getDefaultColumnWidth(): string {
    return DemoLayout::ROW_WIDTH_50_25_25;
  }
}
