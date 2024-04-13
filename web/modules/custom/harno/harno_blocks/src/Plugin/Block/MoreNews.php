<?php


namespace Drupal\harno_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "hanro_block_more_news",
 *   admin_label = @Translation("More News Block"),
 *   category = @Translation("harno_blocks")
 * )
 */
class MoreNews  extends  BlockBase{
  /**
   * {@inheritdoc}
   */
  public function build() {
    $current_page = \Drupal::request()->attributes->get('node');
    if (empty($current_page)) {
      return [];
    }
      $field_definition = $current_page->get('field_article_type')->getFieldDefinition()->getSetting('allowed_values');
    $article_type = $current_page->get('field_article_type')->first()->value;

    $current_id = $current_page->id();
    $bundle = 'article';
    $query = \Drupal::entityQuery('node');
    $query->condition('nid', $current_id,'!=');
    $query->condition('type', $bundle);
    $query->condition('field_article_type',$article_type);
    $query->condition('status', 1);
    $query->sort('created', 'DESC');
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $languageConditions = $query->orConditionGroup();
    $languageConditions->condition('langcode',$language);
    $languageConditions->condition('langcode','und');
    $query->condition($languageConditions);
    $query->range(0,4);
    $nids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $articles = $node_storage->loadMultiple($nids);
    if ($article_type!=2) {
      $important_query = \Drupal::entityQuery('node');
      $important_query->condition('status', 1);
      $important_query->condition('nid', $current_id, '!=');
      $important_query->condition('type', $bundle);
      $important_query->condition('sticky', 1);
      $important_query->sort('created', 'DESC');
      $important_news = $important_query->accessCheck()->execute();
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $importants = $node_storage->loadMultiple($important_news);
    }
    if(!empty($importants)){
      $important_article = reset($importants);
      $important_article_key = array_key_first($importants);
      if (isset($articles[$important_article_key])) {
        unset($articles[$important_article_key]);
      }
      $new_articles = [];
      $new_articles[$important_article_key] = $important_article;
      $new_articles+=$articles;
      if (count($new_articles)>4) {
        unset($new_articles[array_key_last($new_articles)]);
      }
      $articles=$new_articles;
    }
    $articles_data = [];
    $articles_data['title'] = t("More news");
    $articles_data['link_name'] = t("Look more");
    $url =  Url::fromRoute('harno_pages.news_page',['article_type'=>$article_type,'article_type_mobile'=>$article_type])->toString();
    $url = urldecode($url);
    if(!empty($url)){
      $articles_data['link'] = $url;
    }

    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
    $tag_name= $localconf->get('news_our_story');
    $tag_name = $tag_name['name'];
    if (!empty($articles)){
      $articles_data['items'] = [];
      $i = 0;
      foreach ($articles as $article) {
        if ($article->hasTranslation($language)) {
          $article = $article->getTranslation($language);
        }
        $title = $article->get('title')->value;
        $created = date('d.m.Y',$article->get('created')->value);
        $author = $article->get('field_author_name')->value;
        $article_link = $article->toLink()->getUrl()->toString();
        $sticky = $article->get('sticky')->value;
        if ($article_type==2) {
          $tag = $tag_name;
        }
        else{
          $tag = $field_definition[$article_type];
        }
        $articles_data['items'][$i] = [
          'article_link' => $article_link,
          'title' => $title,
          'sticky' =>$sticky,
          'tag' => $article_type==1? '': $tag,
          'created' => $created,
          'author' => $author,
        ];
        if(!empty($article->get('field_one_image'))){
          if (!empty($article->get('field_one_image')->entity)) {
            $media_image = $article->get('field_one_image')->entity->get('field_media_image')->getValue();
            $articles_data['items'][$i]['image'] = $media_image[0];
          }
        }
        $i++;
      }
    }
    $build['#info']['tag_name']  = $tag_name;

    $build['#cache'] = [
      'tags' => [
        'node_list:article',
      ],
    ];
    $build['#data'] = $articles_data;
    $build['#theme'] = 'harno-news-block';
    return $build;
  }
}
