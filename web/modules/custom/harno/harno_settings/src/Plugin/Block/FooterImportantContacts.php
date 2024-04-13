<?php

namespace Drupal\harno_settings\Plugin\Block;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Block\BlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block that creates the social media block to show in the footer area
 * @Block(
 * 	id = "harno_footer_important_contacts",
 * 	admin_label = @Translation("Footer Important Contacts Block"),
 * 	category = @Translation("Footer Block"),
 * )
 */
class FooterImportantContacts extends BlockBase{
  /**
   * Function to build actual block
   *
   */
  public	function build(){
    $information = 'important_contacts';
    $info = $this-> getInfo($information);
    if (!empty($info)) {
      $build = [];
      $build['#theme'] = 'harno_important_contacts_block';
      $build['#data'] = $info;
      return $build;
    }
  }
  public function getInfo($information=null){
    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
    $conf = $localconf->get($information);
    $names = [
      'name_',
      'body_',
      'weight_',
    ];
    $usable_array = [];
    if (!empty($conf)) {
      foreach ($conf as $conf_item => $conf_value) {
        $key = str_replace($names,'',$conf_item);
        $conf_name = str_replace('_'.$key,'',$conf_item);
        if(!empty($conf_value) && $conf_name!='weight'){
          if (filter_var($conf_value, FILTER_VALIDATE_EMAIL)) {
            $usable_array[$key]['type'] = 'email';
          }
          elseif (filter_var($conf_value, FILTER_VALIDATE_URL)) {
            $usable_array[$key]['type'] = 'link';
          }
          else{
            $usable_array[$key]['type'] = 'text';
          }
          if($conf_name == 'body') {
            preg_match("/([+(\d]{1})(([\d+() -.]){5,16})([+(\d]{1})/",$conf_value,$phone_numbers);
            if(!empty($phone_numbers)){
              $new_number = str_replace(' ', '&nbsp;',$phone_numbers[0]);
              $conf_value = str_replace($phone_numbers[0], $new_number, $conf_value);
            }
          }
          $usable_array[$key][$conf_name] = $conf_value;
        }
      }
    }
    if(!empty($usable_array)){
      return $usable_array;
    }

  }
}
