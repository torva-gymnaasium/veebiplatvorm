<?php
declare(strict_types=1);

namespace Drupal\front_layout\Plugin\Layout;

use Drupal\front_layout\DemoLayout;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutDefault;

/**
 * Provides a layout base for custom layouts.
 */
abstract class LayoutBase extends LayoutDefault
{
  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {

    $columnWidths = $this->getColumnWidths();

    if (!empty($columnWidths)) {
      $form['layout'] = [
        '#type' => 'details',
        '#title' => $this->t('Layout'),
        '#open' => TRUE,
        '#weight' => 30,
      ];

      $form['layout']['column_width'] = [
        '#type' => 'radios',
        '#title' => $this->t('Column Width'),
        '#options' => $columnWidths,
        '#default_value' => $this->configuration['column_width'],
        '#required' => TRUE,
      ];
      if (count($columnWidths)==1){
        $form['layout']['column_width']['#value'] = array_key_first($columnWidths);
        $form['layout']['column_width']['#attributes']['style'] = 'display:none';
      }
    }

    $form['#attached']['library'][] = 'front_layout/layout_builder';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $this->configuration['column_width'] = $values['layout']['column_width'];
  }
  /**
   * Get the column widths.
   *
   * @return array
   *   The column widths.
   */
  abstract protected function getColumnWidths(): array;
  /**
   * {@inheritdoc}
   */
  public function build(array $regions): array {
    $build = parent::build($regions);

    $columnWidth = $this->configuration['column_width'];
    if ($columnWidth) {
      $build['#attributes']['class'][] = 'demo-layout__row-width--' . $columnWidth;
    }

    return $build;
  }
}
