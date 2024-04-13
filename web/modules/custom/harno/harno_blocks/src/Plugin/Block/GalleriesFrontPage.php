<?php


namespace Drupal\harno_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "harno_front_page_galleries_block",
 *   admin_label = @Translation("Galleries"),
 *   category = @Translation("harno_blocks")
 * )
 */
class GalleriesFrontPage extends BlockBase implements ContainerFactoryPluginInterface
{

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Constructs a new UserLoginBlock instance.
   *
   * @param array $configuration
   *   The plugin configuration, i.e. an array with configuration values keyed
   *   by configuration option name. The special key 'context' may be used to
   *   initialize the defined contexts by setting it to an array of context
   *   values keyed by context names.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, RouteMatchInterface $route_match)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function build()
  {
    $current_page = \Drupal::request();
    $block_configuration = $this->getConfiguration();
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
        'node_list:galleries',
      ],
    ];
    $build['#configuration'] = $this->getConfiguration();
    $bundle = 'gallery';
    $query = \Drupal::entityQuery('node');
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $query->condition('status', 1);

    $languageConditions = $query->orConditionGroup();
    $languageConditions->condition('langcode',$language);
    $languageConditions->condition('langcode','und');
    $query->condition($languageConditions);
    $query->condition('type', $bundle);
    $query->sort('created', 'DESC');
    $query->range(0, $limit);
    $nids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $galleries = $node_storage->loadMultiple($nids);
    $galleries_data = [];
    if (!empty($galleries)) {
      foreach ($galleries as $gallery) {
        if ($gallery->hasTranslation($language)){
          $gallery = $gallery->getTranslation($language);
        }
        $galleries_data[$gallery->id()]['title'] = $gallery->getTitle();
        if ($gallery->get('field_images')) {
          $gallery_image = $gallery->get('field_images')->target_id;
          $media = Media::load($gallery_image);
          $galleries_data[$gallery->id()]['image'] = $media;
        }
        $created = date('d.m.Y', $gallery->get('created')->value);
        $galleries_data[$gallery->id()]['created'] = $created;
//        $user_id = $gallery->getOwnerId();
//        $user = \Drupal\user\Entity\User::load($user_id);
//        $author_first_name = $user->get('field_first_name')->value;
//        $author_last_name = $user->get('field_last_name')->value;
//        $full_name = '';
//        if (!empty($author_first_name) && !empty($author_last_name)) {
//          $full_name = $author_first_name . ' ' . $author_last_name;
//        }
//
//        if ($gallery->hasField('field_author_name')) {
//          $author_name = $gallery->get('field_author_name')->value;
//          if (!empty($author_name)) {
//            $full_name = $author_name;
//          }
//        }
        $abs_link = $gallery->toLink(NULL, 'canonical', ['absolute' => true])->getUrl()->toString();
        $galleries_data[$gallery->id()]['link'] = $abs_link;
//        $galleries_data[$gallery->id()]['author'] = $full_name;
      }
    }

    $build['#data'] = $galleries_data;
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
    } else {
      $build['#data']['nid'] = rand(1, 19999);
    }
    if (isset($block_configuration['delta'])) {
      $build['#info']['delta'] = $block_configuration['delta'];
    }
    if (isset($block_configuration['uuid'])) {
      $build['#data']['uuid'] = $block_configuration['uuid'];
    }
    //        dump($block_configuration);
    if (isset($block_configuration['label'])) {
      if ($block_configuration['label_display'] == 'visible') {
        $build['#info']['label'] = $block_configuration['label'];
        $build['#info']['label_display'] = $block_configuration['label_display'];
      }
    }

    $default_logo = \Drupal::service('config.factory')->get('harno_settings.settings')->get('general');
    $default_logo = $default_logo['logo'];
    $build['#info']['default_logo'] = $default_logo;
    //        dump($build);
    //    dpm($block_configuration);
    $build['#theme'] = 'harno-front-galleries-block';
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state)
  {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();
    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state)
  {
    parent::blockSubmit($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match')
    );
  }
}
