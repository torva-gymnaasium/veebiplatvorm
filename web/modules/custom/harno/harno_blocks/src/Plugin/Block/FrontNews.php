<?php


namespace Drupal\harno_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "harno_front_page_news_block",
 *   admin_label = @Translation("News"),
 *   category = @Translation("harno_blocks")
 * )
 */
class FrontNews extends BlockBase {
  public function build() {
    $conf = $this->getConfiguration();
    $current_page = \Drupal::request();
    $block_configuration = $this->configuration;
    $node = \Drupal::routeMatch()->getParameter('node');
    $limit = 4;
    if (isset($block_configuration['col_width'])) {
      if ($block_configuration['col_width'] == 75) {
        $limit = 3;
      }
    }
    $build = [];

    $build['#cache'] = [
      'tags' => [
        'node_list:article',
      ],
    ];
    $build['#configuration'] = $this->getConfiguration();
    $bundle = 'article';
    $query = \Drupal::entityQuery('node');
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $query->condition('status', 1);

    $languageConditions = $query->orConditionGroup();
    $languageConditions->condition('langcode',$language);
    $languageConditions->condition('langcode','und');
    $query->condition($languageConditions);
    $query->condition('type', $bundle);
    $query->sort('sticky','DESC');
    $query->condition('field_article_type', 1);
    $query->sort('created', 'DESC');
    $query->range(0,$limit);
    $nids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $news = $node_storage->loadMultiple($nids);
    $news_data = [];
    if (!empty($news)){
      foreach ($news as $news_article){

        if ($news_article->hasTranslation($language)){
          $news_article = $news_article->getTranslation($language);
        }
        $news_data[$news_article->id()]['title'] = $news_article->getTitle();
        if ($news_article->isSticky()) {
          $news_data[$news_article->id()]['sticky'] = true;
        }
        if (!empty($news_article->get('field_one_image'))){
          $gallery_image = $news_article->get('field_one_image')->target_id;
          if (!empty($gallery_image)) {
            $media = Media::load($gallery_image);
            $news_data[$news_article->id()]['image'] = $media;
          }
        }
        $created = date('d.m.Y',$news_article->get('created')->value);
        $news_data[$news_article->id()]['created'] = $created;
        $user_id = $news_article->getOwnerId();
        $user = \Drupal\user\Entity\User::load($user_id);
        $author_first_name = $user->get('field_first_name')->value;
        $author_last_name = $user->get('field_last_name')->value;
        $full_name = '';
        if (!empty($author_first_name) && !empty($author_last_name)){
          $full_name = $author_first_name. ' '.$author_last_name;
        }
        if ($news_article->hasField('field_author_name')) {
          $author_name = $news_article->get('field_author_name')->value;
          if (!empty($author_name)) {
            $full_name = $author_name;
          }
        }
        $abs_link = $news_article->toLink(NULL, 'canonical', ['absolute' => true])->getUrl()->toString();
        $news_data[$news_article->id()]['link']= $abs_link;

        $news_data[$news_article->id()]['author'] = $full_name;

      }
    }

    $build['#data'] = $news_data;

    if (isset($block_configuration['col_width'])) {
      $build['#data']['width'] = $block_configuration['col_width'];
    }
    if (isset($block_configuration['attributes'])) {
      $build['#attributes'] = $block_configuration['attributes'];
    }
    if (!empty($node)) {

      if (!empty($node->get('nid'))) {
        $build['#info']['nid'] = $node->get('nid')->value;
      }
    }
    else{
      $build['#info']['nid'] = rand(1,19999);
    }
    if (isset($block_configuration['uuid'])) {
      $build['#data']['uuid'] = $block_configuration['uuid'];
    }

    if (isset($block_configuration['delta'])){
      $build['#info']['delta'] = $block_configuration['delta'];
    }

    $default_logo = \Drupal::service('config.factory')->get('harno_settings.settings')->get('general');
    $default_logo = $default_logo['logo'];
    $build['#info']['default_logo'] = $default_logo;
    $build['#theme'] = 'harno-front-news-block';
    return $build;
  }
}
