<?php

namespace Drupal\harno_pages\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Core\Url;

/**
 *
 */
class CateringController extends ControllerBase {

  /**
   *
   */
  public function index($tid=null) {
    $build = [];
    $build['#theme'] = 'catering';
    $menus = $this->getMenus($tid);
//    $side_menu = $this->getSideMenu();
//    $build['#side_menu'] = $side_menu;
    $build['#content'] = $menus;
    $build['#cache'] = [
      'contexts' => ['url.query_args'],
      'tags' => ['catering_menu:'.$tid],
    ];
    return $build;

  }

  public function getSideMenu(){
    $bundle = 'food_menu';
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', $bundle);
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('eating_places');
    $days = [
      'monday' => 'Monday',
      'tuesday' => 'Tuesday',
      'wednesday' => 'Wednesday',
      'thursday' => 'Thursday',
      'friday' => 'Friday',
      'saturday' => 'Saturday',
      'sunday' => 'Sunday',
    ];
    $today = date('d.m.Y', time());
    $monday = date('c', strtotime('monday this week'));
    $date = new DrupalDateTime($monday);
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $query->condition('field_week.value', $formatted, '>=');
    $query->sort('field_eating_place', 'ASC');
    $entity_ids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $node_storage->loadMultiple($entity_ids);
    $output = [];
    if (!empty($nodes))   {
      $set_active = false;
      foreach ($nodes as $node) {
        $place = $node->get('field_eating_place')->entity;
        $args['eating_place'] = $place->tid->value;
        $options = ['absolute'=>TRUE];
        $url = $this->url = Url::fromRoute('<current>',[],$options);
        $url->setOptions(array('query' => $args));
        $tid = $place->tid->value;
        if (!isset($output[$tid])) {
          $output[$place->tid->value] = [
            'name' => $place->get('name')->value,
            'url'=> $url->toString(),
            'active' => false,
          ];
        }
        if(empty($_REQUEST)){
          if ($set_active == false) {
            $output[$tid]['active'] = true;
            $set_active = true;
          }
        }
        if (!empty($_REQUEST)) {

        if (!empty($_REQUEST['eating_place'])) {
          if ($_REQUEST['eating_place'] == $tid) {
            $output[$tid]['active'] = true;
          }
        }
        }

      }

    }
    if (!empty($output)) {
      return $output;
    }
  }

  /**
   *
   */
  public function getMenus($tid=null) {
    $bundle = 'food_menu';
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', $bundle);
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('eating_places');
    if (!empty($tid)){
      $query->condition('field_eating_place', $tid);
    }
    else{
      $term = reset($terms);
      $query->condition('field_eating_place', $term->tid);
    }
    $days = [
      'monday' => 'Monday',
      'tuesday' => 'Tuesday',
      'wednesday' => 'Wednesday',
      'thursday' => 'Thursday',
      'friday' => 'Friday',
      'saturday' => 'Saturday',
      'sunday' => 'Sunday',
    ];
    $today = date('d.m.Y', time());
    $monday = date('c', strtotime('monday this week'));
    $date = new DrupalDateTime($monday);
    $date->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));
    $formatted = $date->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);
    $query->condition('field_week.value', $formatted, '>=');
    $query->sort('field_week.value', 'ASC');
    $query->groupBy('field_eating_place');
    $query->range(0, 3);
    $entity_ids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $node_storage->loadMultiple($entity_ids);
    $output = [];
    $output['filled_food_groups'] = [];
    if (!empty($nodes)) {
      foreach ($nodes as $node) {
        if ($node->hasTranslation($language)){
          $node = $node->getTranslation($language);
        }
        $created = $node->get('created')->value;
        $changed = $node->get('changed')->value;
        $output['published'] = date('d.m.Y',$created);
        $output['changed'] = date('d.m.Y',$changed);
        $eating_place= $node->get('field_eating_place')->entity;
        if ($eating_place->hasTranslation($language)){
          $place = $eating_place->getTranslation($language)->get('name')->value;
        }
        else{
          $place = $eating_place->get('name')->value;
        }
//        $place = $node->get('field_eating_place')->entity->get('name')->value;
        $output['place'] = $place;
        $week_start = $node->get('field_week')->value;
        $week_start = new DrupalDateTime($week_start, 'UTC');
        $week_start_date = clone $week_start;
        $week_start = $week_start->getTimestamp();

        $week_start_date = \Drupal::service('date.formatter')->format(
              $week_start,
              'custom',
              'd.m.Y'
          );
        $week_start = \Drupal::service('date.formatter')->format(
              $week_start,
              'custom',
              'd.m'
          );
        $week_end = $node->get('field_week')->end_value;
        $week_end = new DrupalDateTime($week_end, 'UTC');
        $week_end = $week_end->getTimestamp();
        $week_end = \Drupal::service('date.formatter')->format(
              $week_end,
              'custom',
              'd.m'
          );
        foreach ($days as $day_key => $day) {
          $day_date = strtotime($day_key, strtotime($week_start_date));
          $day_date = date('d', $day_date);
          if ($day_date == date('d',time())){
            $active_date = true;
          }
          else{
            $active_date = false;
          }
          $day_meals = $node->get('field_' . $day_key)->getValue();
          foreach ($day_meals as $day_meal) {

            $day_controller = \Drupal::entityTypeManager()->getStorage('paragraph');
            $day_meal = $day_controller->load($day_meal['target_id']);
            if ($day_meal->hasTranslation($language)){
              $day_meal = $day_meal->getTranslation($language);
            }
            $text = $day_meal->get('field_body_text')->value;
            if (empty($text) and ($day_key=='saturday' or $day_key=='sunday')){
              continue;
            }
            $food_group = $day_meal->get('field_food_group')->entity;
            if (!empty($food_group) && $food_group->hasTranslation($language)){
              $food_group = $food_group->getTranslation($language);
            }
            if (!empty($food_group)) {
              $food_group = $food_group->get('name')->value;
            }
              $output[$week_start . ' - ' . $week_end][$day]['date'] = $day_date;
              if ($active_date){
                $output[$week_start . ' - ' . $week_end][$day]['active'] = true;
              }

              $text = explode(PHP_EOL, $text);
            $text_out = '';
            foreach ($text as $key => $text_line) {
              if (!empty($text_line)) {

                $text_out = $text_out . '<p>' . $text_line . '</p>';
              }
            }
            if (!empty($food_group)) {
              if (!empty($text_out)) {
                $output['food_groups'][$food_group] = ['true' => true, 'weight' => $day_meal->get('field_food_group')->entity->get('weight')->value];
                $output['filled_food_groups'][$week_start . ' - ' . $week_end][$food_group] = 'true';
              }
            $output[$week_start . ' - ' . $week_end][$day][$food_group] = $text_out;
            }
            else{
              $output['food_groups']['no_group'] = ['true' => true, 'weight' => 1];
              $output[$week_start . ' - ' . $week_end][$day]['no_group'] = $text_out;
              $output['filled_food_groups'][$week_start . ' - ' . $week_end]['no_group'] = 'true';
            }
          }

        }
        $catering_provider_info = [];
        $catering_provider = $node->get("field_catering_service_provider")->entity;
        if ($catering_provider->hasTranslation($language)){
          $catering_provider = $catering_provider ->getTranslation($language);
        }
        $catering_fields = [
          'address'=>'field_address',
          'caterer_name'=>'field_caterer_name',
          'email'=>'field_email',
          'homepage'=>['key'=>'field_homepage','values_to_get'=>['uri','title','options']],
          'phone'=>'field_phone',
          'business_name'=>'name',
          'description'=>'description',
        ];
        foreach ($catering_fields as $key => $catering_field) {
          if (!is_array($catering_field)) {
            if ($key=='description'){
              $description = $catering_provider->get($catering_field)->value;
              $description = str_replace('<ul>','<ul class="list-styled">',$description);
              $catering_provider_info[$key] = $description;
            }
            else {
              $catering_provider_info[$key] = $catering_provider->get($catering_field)->value;
            }
          }
          else{
            foreach ($catering_field['values_to_get'] as $field_key) {
              if ($field_key=='uri'){
                $extrenal_check = \Drupal\Component\Utility\UrlHelper::isExternal($catering_provider->get($catering_field['key'])->$field_key);
                $catering_provider_info[$key]['external'] = $extrenal_check;
              }
              $catering_provider_info[$key][$field_key] = $catering_provider->get($catering_field['key'])->$field_key;
            }
          }
        }
        if (!empty($catering_provider_info)) {
          $output['catering_provider'] = $catering_provider_info;
        }
      }
    }
    if (!empty($output['food_groups'])){
      uasort($output['food_groups'],function ($a, $b) {
          if ($a['weight']==0 && $b['weight']!==0){
            return -1;
          }
          if ($a['weight']<$b['weight']){
            return -1;
          }
          else{
            return 1;
          }
      });
    }
    return $output;
  }

}
