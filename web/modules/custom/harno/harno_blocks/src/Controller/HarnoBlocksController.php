<?php

namespace Drupal\harno_blocks\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Returns responses for harno_blocks routes.
 */
class HarnoBlocksController extends ControllerBase {

  /**
   * Builds the response.
   */
  public function build() {

    $build['content'] = [
      '#type' => 'item',
      '#markup' => $this->t('It works!'),
      '#cache' => [
        'tags' => ['node_type:article']
      ],
    ];

    return $build;
  }

}
