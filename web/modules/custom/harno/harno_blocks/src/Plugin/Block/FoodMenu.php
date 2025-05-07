<?php


namespace Drupal\harno_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Exception;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "harno_front_page_food_menu",
 *   admin_label = @Translation("Food menu"),
 *   category = @Translation("harno_blocks")
 * )
 */
class FoodMenu  extends  BlockBase {
  public function build() {
    $menu = $this->getFoodMenu();
    $config = $this->getConfiguration();
    $build = [];
    $build['#configuration'] = $config;
    $build['#theme'] = 'front-food-menu';
    $build['#data'] = $menu;
    $build['#cache'] = [
      'tags' => [
        'node_list:food_menu',
      ],
    ];

    if (isset($config['delta'])){
      $build['#info']['delta'] = $config['delta'];
    }
    if (isset($config['link_url'])){
      $url = Url::fromRoute('entity.node.canonical', array('node' => $config['link_url']));
      try {
        $build['#info']['link_url'] = $url->toString();
      }
      catch(Exception $e) {

      }
    }
    else{
      $places = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('eating_places');
      $first_place = reset($places);
      if (!empty($first_place)){
        $url = Url::fromRoute('entity.taxonomy_term.canonical', array('taxonomy_term' => $first_place->tid));
        try {
          $build['#info']['link_url'] = $url->toString();
        }
        catch(Exception $e) {

        }
      }
    }
    $node = \Drupal::routeMatch()->getParameter('node');
    if (!empty($node)) {

      if (!empty($node->get('nid'))) {
        $build['#info']['nid'] = $node->get('nid')->value;
      }
    }
    else{
      $build['#info']['nid'] = rand(1,19999);
    }

    if (isset($config['attributes'])) {
      $build['#attributes'] = $config['attributes'];
    }
    if (isset($config['label'])){
      if ($config['label_display']=='visible'){
        $build['#info']['label'] = $config['label'];
        $build['#info']['label_display'] = $config['label_display'];
      }
    }
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('webform')) {
      $build['#attached']['library'][] = 'webform/libraries.jquery.select2';
    }
    return $build;
  }
  public function getFoodMenu(){
    $query = \Drupal::entityQuery('node');
    $bundle = 'food_menu';
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $query->condition('status', 1);
    $query->condition('type', $bundle);
    $query->sort('field_eating_place.entity.weight','ASC');
    $query->condition('langcode',$language);

    $this_week_start = strtotime('this week monday midnight');
    $this_week_end = strtotime('this week sunday 23:59');
    $start_date = new DrupalDateTime(date('c',$this_week_start));
    $start_date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted_start = $start_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $end_date = new DrupalDateTime(date('c',$this_week_end));
    $end_date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted_end = $end_date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $this_week_end = date('c',$this_week_end);
    $query->condition('field_week.value',$formatted_start,'>=');
    $query->condition('field_week.end_value',$formatted_end,'<=');
    $entity_ids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $node_storage->loadMultiple($entity_ids);
    $output = [];
    $today = date('l',time());
    $tomorrow = strtotime('midnight tomorrow');
    $tomorrow = date('l',$tomorrow);
    if (!empty($nodes)){
      $field_suffixes = [
        'monday'=>'Monday',
        'tuesday'=>'Tuesday',
        'wednesday'=>'Wednesday',
        'thursday'=>'Thursday',
        'friday'=>'Friday',
        'saturday'=>'Saturday',
        'sunday'=>'Sunday',
      ];
      $k= 10;
      foreach ($nodes as $node){
        if ($node->hasTranslation($language)){
          $node = $node->getTranslation($language);
        }
        $eating_place = $node->get('field_eating_place')->entity;
        if ($eating_place->hasTranslation($language)){
          $eating_place = $eating_place->getTranslation($language);
        }
        $eating_place = $eating_place->get('name')->value;
        $eating_place_tid = $node->get('field_eating_place')->entity->id();
        $j=0;
        foreach ($field_suffixes as $field_key => $field_suffix){
          if ($field_suffix==$today || $field_suffix==$tomorrow) {
            $day_meals = $node->get('field_' . $field_key)->getValue();
            $output[$j][$k] = [];
            $output[$j][$k]['place'] = $eating_place;
            $output[$j][$k]['place_tid'] = $eating_place_tid;

            if ($field_suffix==$today ){
              $output[$j]['type'] = 'Today';
            }
            if ($field_suffix == $tomorrow){
              $output[$j]['type'] = 'Tomorrow';
            }
            foreach ($day_meals as $day_meal) {
              $par_controller = \Drupal::entityTypeManager()->getStorage('paragraph');
              $day_meal = $par_controller->load($day_meal['target_id']);
              if ($day_meal->hasTranslation($language)){
                $day_meal = $day_meal->getTranslation($language);
              }
              $food_group = $day_meal->get('field_food_group')->entity;
              if (!empty($food_group)) {
                if ($food_group->hasTranslation($language)){
                  $food_group = $food_group->getTranslation($language);
                }
              $food_group_name = $food_group->get('name')->value;
              $meals = $day_meal->get('field_body_text')->value ?? "";
              $group_menu = explode(PHP_EOL, $meals);
              $group_menu_out = [];
              if ($group_menu[0]=='') {
                $group_menu = [];
              }
              if (!empty($group_menu)) {

              foreach ($group_menu as $group_menu_item){
                $group_menu_out[] = $group_menu_item;
              }
              }
              $output[$j][$k][$food_group_name] = $group_menu_out;
              }
            }
          }
          $j++;
          $k++;
        }

      }
    }
    return $output;

  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {

    $config = $this->getConfiguration();
    $form = parent::blockForm($form, $form_state);
    if (!empty($config['link_url'])) {
      $default_entity = \Drupal::entityTypeManager()->getStorage('node')->load($config['link_url']);
    }
    $form['link_url'] = [
      '#type' => 'entity_autocomplete',
      '#title' => 'Food menu page ' ,
      '#description'=> t('Select page you want the bottom link to redirect'),
      '#title_display' => 'invisible',
      '#target_type' => 'node',
      '#selection_handler' => 'default',
      '#default_value' => !empty($default_entity)?$default_entity:''
    ];



    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['link_url'] = $values['link_url'];
  }
}
