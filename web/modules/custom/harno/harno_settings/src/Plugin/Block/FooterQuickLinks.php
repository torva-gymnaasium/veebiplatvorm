<?php

namespace Drupal\harno_settings\Plugin\Block;

use Drupal\Core\Block\BlockBase;
/**
 * Block that creates the social media block to show in the footer area
 * @Block(
 * 	id = "harno_footer_quick_links",
 * 	admin_label = @Translation("Footer Quick Links Block"),
 * 	category = @Translation("Footer Block"),
 * )
 */
class FooterQuickLinks extends BlockBase{
  /**
   * Function to build actual block
   *
   */
  public	function build(){
    $info = $this->getInfo();
    $build = [];
    if (!empty($info)) {
      $build['#theme'] = 'harno_footer_quick_links_block';
      $build['#data'] = $info;
    }
    return $build;
  }
  public function getInfo(){
    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
    $conf = $localconf->get('footer_quick_links');
    $names = [
      'link_name_',
      'link_entity_',
      'link_weight_',
      'link_url_',
    ];
    $usable_array = [];
    if (!empty($conf)) {
      foreach ($conf as $conf_item => $conf_value) {
        $key = str_replace($names,'',$conf_item);
        $conf_name = str_replace('_'.$key,'',$conf_item);
        if(!empty($conf_value) && $conf_name!='link_weight'){
          if($conf_name=='link_entity'){
            $entity = \Drupal::entityTypeManager()->getStorage('node')->load($conf_value);
            $link_name = $conf['link_name_'.$key];
            if(!empty($entity)) {
              $link_internal = $entity->toLink()->getUrl()->toString();
              $link = $entity->toLink($link_name)
                ->toString()
                ->getGeneratedLink();
              $usable_array[$key]['link'] = $link;
              $usable_array[$key]['link_internal'] = $link_internal;
              $usable_array[$key]['type'] = 'internal_link';
            }
          }
          $usable_array[$key][$conf_name] = $conf_value;
        }
      }
      
      return $usable_array;
    }


  }
}
