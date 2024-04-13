<?php


namespace Drupal\harno_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Component\Utility\UrlHelper;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "harno_quicklinks_for_front",
 *   admin_label = @Translation("Harno Quicklinks front"),
 *   category = @Translation("harno_blocks_fixed")
 * )
 */
class FrontQuickLinks extends BlockBase {

  /**
   * Build function.
   *
   * @return array
   *   Returns renderable block.
   */
  public function build() {
    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');

    $conf = $localconf->get('frontpage_quick_links');
    for ($i = 1; $i <= 8; $i++) {
      $name = 'link_name_' . $i;
      $name = $conf[$name];
      $entity = 'link_entity_' . $i;
      $entity = $conf[$entity];
      $url = 'link_url_' . $i;
      $url = $conf[$url];
      if (!empty($entity) || !empty($url)) {
        $external = FALSE;
        if (!empty($url)) {
          $host = \Drupal::request()->getSchemeAndHttpHost();
          $internal_check = UrlHelper::externalIsLocal($url, $host);
          if (empty($entity)) {
            if ($internal_check) {
              $external = FALSE;
            }
            else {
              $external = TRUE;
            }
          }
        }
        $weight = 'link_weight_' . $i;
        $weight = $conf[$weight];
        $build['#data']['entities'][$i] = [
          'external' => $external,
          'name' => $name,
          'entity' => $entity,
          'url' => $url,
          'weight' => $weight,
        ];
      }

    }
    $build['#theme'] = 'harno-quicklinks-block';
    $build['#cache'] = [
      'tags' => [
        'harno-config',
      ],
    ];
    return $build;
  }

}
