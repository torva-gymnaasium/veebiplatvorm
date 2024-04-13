<?php

namespace Drupal\harno_settings\Plugin\Block;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Block\BlockBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block that creates the social media block to show in the footer area
 * @Block(
 * 	id = "harno_footer_social",
 * 	admin_label = @Translation("Footer Social Media Icons Block"),
 * 	category = @Translation("Footer Block"),
 * )
 */
class FooterSocial extends BlockBase{
    /**
     * Function to build actual block
     *
     */
    public	function build(){
        $social_info = $this->getSocialInfo();

        $build = [];
        if(!empty($social_info)) {
          $build['#theme'] = 'harno_social_block';
          $build['#data'] = $social_info;
        }
        return $build;
    }
    public function getSocialInfo(){
        $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
        $conf = $localconf->get('footer_socialmedia_links');
        $names = [
            'link_name_',
            'link_icon_',
            'link_weight_',
            'link_url_',
        ];
        $usable_array = [];
        if(!empty($conf)){
            foreach ($conf as $conf_item => $conf_value) {
                $key = str_replace($names,'',$conf_item);
                $conf_name = str_replace('_'.$key,'',$conf_item);
                if(!empty($conf_value) && $conf_name!='link_weight'){

                    $usable_array[$key][$conf_name] = $conf_value;
                }
            }
        }
        if(!empty($usable_array)){
            $i=1;
            $row=1;
            $return_array = [];
            foreach ($usable_array as $existing_key => $existing_value) {
                $return_array[$row][$existing_key] = $existing_value;
                if($i%3==0){
                    $row++;
                }
                $i++;
            }
            return $return_array;

        }

    }
}
