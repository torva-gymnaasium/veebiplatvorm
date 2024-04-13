<?php


namespace Drupal\harno_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "harno_front_page_logos_block",
 *   admin_label = @Translation("Recognitions"),
 *   category = @Translation("harno_blocks")
 * )
 */
class LogosFrontPage  extends  BlockBase{
  public function build() {
    $subtype = 1;
    $current_page = \Drupal::request();
    $block_configuration = $this->configuration;
    $current_page = \Drupal::request();
    $block_configuration = $this->configuration;
    $node = \Drupal::routeMatch()->getParameter('node');
    $limit = 8;
    if (isset($block_configuration['col_width'])) {
      if ($block_configuration['col_width'] == 75) {
        $limit = 8;
      }
    }
    if (isset($block_configuration['number_of_rows'])){
      $limit = $block_configuration['number_of_rows'];
    }
    $build = [];
    $build['#cache'] = [
      'tags' => [
        'node_list:partner_logo',
      ],
    ];
    $build['#configuration'] = $this->getConfiguration();
    $bundle = 'partner_logo';
    $query = \Drupal::entityQuery('node');
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $query->condition('status', 1);
    $languageConditions = $query->orConditionGroup();
    $languageConditions->condition('langcode',$language);
    $languageConditions->condition('langcode','und');
    $query->condition($languageConditions);
    $query->condition('type', $bundle);
    $query->condition('field_partner_logo_type',$subtype);
    $query->sort('created', 'DESC');
    $query->range(0,8);
    $nids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $contents =  $node_storage->loadMultiple($nids);
    $content_data = [];
    foreach ($contents as $content){
//      dump($content);
      if ($content->hasTranslation($language)){
        $content = $content->getTranslation($language);
      }
      $content_data[$content->id()]['title'] = $content->getTitle();
      $link = $content->get('field_link')->getValue();
      if (empty($link)) {
        $link = [0=>['uri'=>'base:/']];
      }
      $link = reset($link);
      $link = Url::fromUri($link['uri'],['absolute'=>true])->toString();
      $host = \Drupal::request()->getSchemeAndHttpHost();
      $internal_check = \Drupal\Component\Utility\UrlHelper::externalIsLocal($link,$host);
      $external = false;
      if (!empty($content)) {
        if ($internal_check) {
          $external = false;
        } else {
          $external = true;
        }
      }
      $content_data[$content->id()]['external'] = $external;
      $content_data[$content->id()]['link'] = $link;
      $logo = $content->get('field_one_image')->getValue();

      $logo = reset($logo);
      $media = Media::load($logo['target_id']);
      if ($media->hasTranslation($language)){
        $media = $media->getTranslation($language);
      }
      $content_data[$content->id()]['logo'] = $media;

    }

    $build['#data'] = $content_data;
    $build['#data']['type'] = $subtype;
    if (isset($block_configuration['col_width'])) {
      $build['#data']['width'] = $block_configuration['col_width'];
    }
    if (isset($block_configuration['attributes'])) {
      $build['#attributes'] = $block_configuration['attributes'];
    }
    if (!empty($node)) {

      if (!empty($node->get('nid'))) {
        $build['#data']['nid'] = $node->get('nid')->value;
      }
    }
    else{
      $build['#data']['nid'] = rand(1,19999);
    }
    if (isset($block_configuration['uuid'])) {
      $build['#data']['uuid'] = $block_configuration['uuid'];
    }

    if (isset($block_configuration['delta'])){
      $build['#info']['delta'] = $block_configuration['delta'];
    }

    $build['#theme'] = 'harno-front-logo-block';
    return $build;
  }
}
