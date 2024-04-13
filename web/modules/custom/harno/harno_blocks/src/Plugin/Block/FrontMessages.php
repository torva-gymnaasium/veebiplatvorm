<?php


namespace Drupal\harno_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "harno_front_page_messages",
 *   admin_label = @Translation("Harno Front Page Messages"),
 *   category = @Translation("harno_blocks_fixed")
 * )
 */
class FrontMessages  extends  BlockBase
{
  public function build()
  {
    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');

    $conf = $localconf->get('frontpage_messages');
    $messages = [];
    if ($conf != null) {
      for ($i = 1; $i <= 2; $i++) {
        if ($conf['published_' . $i]) {
          if ($conf['type_' . $i] == 1) {
            $type = 'alert';
          } else {
            $type = 'info';
          }
          if (!empty($conf['body_' . $i]) && !empty($type)) {
            $messages[] = [
              'type' => $type,
              'message' => $conf['body_' . $i],
            ];
          }
        }
      }
    }
    if (!empty($messages)) {
      $build['#data']['messages'] = $messages;
    }

    $build['#theme'] = 'harno-front-messages-block';
    $build['#cache'] = [
      'tags' => [
        'harno-config',
      ],
    ];
    return $build;
  }
}
