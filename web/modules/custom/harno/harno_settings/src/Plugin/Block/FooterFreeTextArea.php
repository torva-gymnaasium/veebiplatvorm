<?php

namespace Drupal\harno_settings\Plugin\Block;


use Drupal\Core\Block\BlockBase;

/**
 * Block that creates the social media block to show in the footer area
 * @Block(
 * 	id = "harno_footer_free_text_area",
 * 	admin_label = @Translation("Footer Free Form Text Area"),
 * 	category = @Translation("Footer Block"),
 * )
 */
class FooterFreeTextArea extends BlockBase{

  /**
   * Function to build actual block
   *
   */

  public	function build(){
    $information = 'footer_free_text_area';
    $info = $this-> getInfo($information);
    if (!empty($info)) {
      $build = [];
      $build['#theme'] = 'harno_footer_free_text_block';
      $build['#data'] = $info;
      return $build;
    }
  }
  public function getInfo($information=null){
    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
    $conf = $localconf->get($information);
    $out = [];
    if (!empty($conf['name'])){
      $out['name'] = $conf['name'];
    }
    if(!empty($conf['body'])){
      $out['body'] = nl2br($conf['body']);
    }
    return $out;
  }
}
