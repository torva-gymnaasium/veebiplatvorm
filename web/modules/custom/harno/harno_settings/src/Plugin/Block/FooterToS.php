<?php

namespace Drupal\harno_settings\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Block that creates the social media block to show in the footer area
 * @Block(
 * 	id = "harno_footer_terms_of_service_block",
 * 	admin_label = @Translation("Footer Terms of Service Block"),
 * 	category = @Translation("Footer Block"),
 * )
 */
class FooterToS extends BlockBase{
  /**
   * Function to build actual block
   *
   */
  public	function build(){
    $info = $this->getInfo();
    $build = [];
    $build['#theme'] = 'harno_tos_block';
    $build['#data'] = $info;
    return $build;
  }
  public function getInfo(){
    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
    $conf = $localconf->get('footer_copyright');
    $output = [];
    if(!empty($conf['name'])){
      $output['message'] = $conf['name'];
    }
    return $output;
  }
}
