<?php

namespace Drupal\front_layout\Controller;

use Drupal\Core\Controller\ControllerBase;
use \Symfony\Component\HttpFoundation\Response;

/**
 * An example controller.
 */
class ReNew extends ControllerBase {

  /**
   * {@inheritdoc}
   */
  public function Render() {
    $build = array(
      '#type' => 'markup',
      '#markup' => t('Hello World!'),
    );
    // This is the important part, because will render only the TWIG template.
    return new Response(\Drupal::service('renderer')->render($build));
  }

}
