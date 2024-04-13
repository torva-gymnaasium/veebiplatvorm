<?php


namespace Drupal\harno_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "harno_front_banners",
 *   admin_label = @Translation("Harno Banners front"),
 *   category = @Translation("harno_blocks_fixed")
 * )
 */
class FrontBanners  extends  BlockBase{
  public function build() {
    $localconf = \Drupal::service('config.factory')->get('harno_settings.frontpage');
    $conf = $localconf->get('general');

    $type = $conf['background_type'];

    if ($type == 1){
      $image_id = $conf['background_image'];
      $build['#data']['background_only'] = [
        'image_id'=>$image_id,
      ];
    }
    if ($type==2){
      $conf= $localconf->get('banner_images');
      for ($i=1;$i<=5;$i++){
        if ($conf['image_'.$i]!==0){
          $entity = $conf['link_entity_'.$i];
          $external = false;

          $url = $conf['link_url_'.$i];
          if (!empty($conf['link_url_'.$i])){
            $host = \Drupal::request()->getSchemeAndHttpHost();
            $internal_check = \Drupal\Component\Utility\UrlHelper::externalIsLocal($url,$host);
            if (empty($entity)) {
              if ($internal_check) {
                $external = false;
              } else {
                $external = true;
              }
            }
          }
          if (!empty($conf['link_entity_'.$i])){
            $external = false;

          }
          $build['#data']['background_text'][$i]=[
            'external'=>$external,
            'link_name' => $conf['link_name_'.$i],
            'entity'=> $entity,
            'image_id' => $conf['image_'.$i],
            'text'=> $conf['text_'.$i],
            'url' =>$url,
          ];
        }
      }
    }
    if ($type == 3){
      $images_n_links = $localconf->get('banner_boxes_images');
      $texts = $localconf->get('banner_boxes_text');
      for ($i=1; $i<=5;$i++){
        if ($images_n_links['image_'.$i]!==0){
          $image_id = $images_n_links['image_'.$i];
          $build['#data']['swiper_text']['background'][$i] = [
            'image_id'=>$image_id,
          ];
        }
      }
      for ($i=1;$i<=15;$i++){
        if (!empty($texts['title_'.$i])){
          $build['#data']['swiper_text']['boxes'][$i]['title']= $texts['title_'.$i];
        }
        if (!empty($texts['icon_'.$i])){
          $icon = $texts['icon_'.$i];
          if (strpos($icon,'lnr-')!==FALSE){
            $icon = str_replace('lnr-','lni-',$icon);
          }
          if (strpos($icon, 'lni-') !== false) {

          }
          else{
            $icon = 'lni-'.$icon;
          }
          if (strpos($icon,'lni-')!==FALSE){
          }
          $build['#data']['swiper_text']['boxes'][$i]['icon']= $icon;
        }
        if (!empty($texts['link_entity_'.$i])){
          $build['#data']['swiper_text']['boxes'][$i]['entity']= $texts['link_entity_'.$i];
        }
      }
    }
    $build['#theme'] = 'harno-banner-block';
    $build['#cache'] = [
      'tags' => [
        'harno-config',
      ],
    ];
    return $build;
  }
}
