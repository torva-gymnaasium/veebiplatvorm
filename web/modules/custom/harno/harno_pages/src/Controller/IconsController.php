<?php

namespace Drupal\harno_pages\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class IconsController extends ControllerBase {

  /**
   *
   */
  public function get_icons_html() {
    $build = [];
    $build['#theme'] = 'icons-page';
    return $build;
  }
}
