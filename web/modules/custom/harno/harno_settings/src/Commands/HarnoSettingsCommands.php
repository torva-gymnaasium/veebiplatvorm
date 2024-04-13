<?php

namespace Drupal\harno_settings\Commands;

use Drush\Commands\DrushCommands;
use Drupal\Core\Cache\Cache;

/**
 * A Drush commandfile.
 */
class HarnoSettingsCommands extends DrushCommands {

  /**
   * Remove colors translation
   *
   * @usage remove-color-translation
   *   Usage description
   *
   * @command remove-color-translation
   * @aliases rct
   */
  public function removeColorsTranslation() {
    $database = \Drupal::service('database');
    $num_deleted = $database->delete('config')
      ->condition('name', 'harno_settings.colors')
      ->condition('collection', 'language.en')
      ->execute();
    $message = "Deleted " . $num_deleted . " colors translation.";
    \Drupal::logger('harno_settings')->info($message);
    $messenger = \Drupal::messenger();
    $messenger->addMessage($message, 'status');
  }
}
