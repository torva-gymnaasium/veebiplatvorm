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
 *   id = "harno_front_page_stories_block",
 *   admin_label = @Translation("Our stories"),
 *   category = @Translation("harno_blocks")
 * )
 */
class FrontStories extends BlockBase {
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
    $query->condition('field_article_type', 2);
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
        if ($news_article->get('field_one_image')){
          $gallery_image = $news_article->get('field_one_image')->target_id;
          $media = Media::load($gallery_image);
          $news_data[$news_article->id()]['image'] = $media;
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
    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
    $tag_name= $localconf->get('news_our_story');
    $tag_name = $tag_name['name'];
    if (!empty($tag_name)){
      $build['#info']['tag_name'] = $tag_name;
    }
    $default_logo = \Drupal::service('config.factory')->get('harno_settings.settings')->get('general');
    $default_logo = $default_logo['logo'];
    $build['#info']['default_logo'] = $default_logo;
    $build['#theme'] = 'harno-front-stories-block';
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state)
  {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();
    $form['form_id_block'] = [
      '#type'=> 'hidden',
      '#value' => 'stories_block'
    ];
    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state)
  {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['schedule_link'] = $values['schedule_link'];
    $this->configuration['schedule_link_title'] = $values['schedule_link_title'];
  }
}
