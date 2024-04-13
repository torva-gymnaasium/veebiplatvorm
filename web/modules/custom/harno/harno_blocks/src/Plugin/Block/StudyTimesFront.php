<?php


namespace Drupal\harno_blocks\Plugin\Block;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\media\Entity\Media;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "harno_front_page_study_times_block",
 *   admin_label = @Translation("Hours"),
 *   category = @Translation("harno_blocks")
 * )
 */
class StudyTimesFront  extends  BlockBase
{
  public function build()
  {
    $build = array();
    $block_configuration = $this->getConfiguration();

    $build['#cache'] = [
      'tags' => [
        'taxonomy_term_list:school_hours',
      ],
    ];
    $build['#configuration'] = $block_configuration;
    $limit = 4;
    $bundle = 'school_hours';
    $today = strtotime('today midnight');
    $today = date('Y-m-d', $today);
    $query = \Drupal::entityQuery('taxonomy_term');
    $query->condition('status', 1);
    $query->condition('vid', $bundle);
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    //    $query->condition('field_start_date',$today,'>=');
    //    $query->condition('field_end_date',$today,'>=');
    //    $query->sort('field_start_date', 'DESC');
    //    $query->range(0,$limit);
    //    $query->sort('field_end_date')
    $tids = $query->accessCheck()->execute();
    $tax_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $contents =  $tax_storage->loadMultiple($tids);
    $today = strtotime('today midnight');
    $today = date('l', $today);
    $days_filters = [
      'E-R' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
      'E-P' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
      'E' => ['Monday'],
      'T' => ['Tuesday'],
      'K' => ['Wednesday'],
      'N' => ['Thursday'],
      'R' => ['Friday'],
      'L' => ['Saturday'],
      'P' => ['Sunday'],
    ];
    //    dump($today);
    //    dump($contents);
    $content_data = [];
    $filtered_hours = [];
    foreach ($contents as $content) {
      if ($content->hasTranslation($language)){
        $content = $content->getTranslation($language);
      }
      $tid = $content->get('tid')->value;

      $filtered_hours[$tid] = [];
      $filtered_hours[$tid]['name'] = $content->get('name')->value;
      $school_hours_groups = $content->get('field_school_hours_group')->getValue();
      //      dump($school_hours_groups);
      $max_hours = 0;
      foreach ($school_hours_groups as $school_hours_group) {
        $par_controller = \Drupal::entityTypeManager()->getStorage('paragraph');
        $hours_group_id = $school_hours_group['target_id'];
        $hours_group_info = $par_controller->load($school_hours_group['target_id']);
        if ($hours_group_info->hasTranslation($language)){
          $hours_group_info = $hours_group_info->getTranslation($language);
        }
        $filtered_hours[$tid][$hours_group_id]['name'] = $hours_group_info->get('field_name')->value;
        $school_hours_days = $hours_group_info->get('field_school_hour_day')->getValue();
        foreach ($school_hours_days as $school_hours_day) {
          $day_target_id = $school_hours_day['target_id'];
          $par_controller = \Drupal::entityTypeManager()->getStorage('paragraph');
          $school_hours_day = $par_controller->load($school_hours_day['target_id']);
          $days_it_applies = $school_hours_day->get('field_school_hour_days')->value;
          $filter_days = $days_filters[$days_it_applies];
          if (!in_array($today, $filter_days)) {
            continue;
          }
          if (count($filter_days) == 1) {
            if (!empty($filtered_hours[$tid][$hours_group_id]['day'])) {
              $filtered_hours[$tid][$hours_group_id]['day'] = [];
            }
          }
          $filtered_hours[$tid][$hours_group_id]['day']['day_rule'] = $days_it_applies;
          $school_hours_hours = $school_hours_day->get('field_school_hour')->getValue();
          $group_count = 0;
          foreach ($school_hours_hours as $school_hours_hour) {
            $hours_id = $school_hours_hour['target_id'];
            $par_controller = \Drupal::entityTypeManager()->getStorage('paragraph');
            $school_hours_hour = $par_controller->load($school_hours_hour['target_id']);
            $opening_time = $school_hours_hour->get('field_opening_time')->value;
            $closing_time = $school_hours_hour->get('field_closing_time')->value;
            $closing_time = new DrupalDateTime(date('c', $closing_time));
            $closing_time->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));

            $start_time = new DrupalDateTime(date('c', $opening_time));
            $start_time->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));

            $hour_type = $school_hours_hour->get('field_school_hour_type')->value;
            $hour_array = [
              'type' => $hour_type,
              'open' => $start_time->format('H:i'),
              'close' => $closing_time->format("H:i")
            ];
            $group_count++;
            $filtered_hours[$tid][$hours_group_id]['day']['hours'][$group_count] = $hour_array;
            if ($group_count > $max_hours) {
              $max_hours = $group_count;
            }
          }
          usort($filtered_hours[$tid][$hours_group_id]['day']['hours'], function ($a, $b) {
            if ($a['open'] < $b['open']) {
              return -1;
            } elseif ($a['open'] > $b['open']) {
              return 1;
            } elseif ($a['open'] == $b['open']) {
              if ($a['closed'] < $b['closed']) {
                return -1;
              } elseif ($a['closed'] > $b['closed']) {
                return 1;
              } else {
                return -1;
              }
            }
          });
        }
        $filtered_hours[$tid]['count'] = $max_hours;
      }
    }
    $build['#info'] = [];
    $build['#info']['link_title'] = t('School hours today');
    if (isset($max_hours) && $max_hours != 0) {
      $build['#data'] = $filtered_hours;
    }
    if (!empty($block_configuration['col_width'])) {
      $build['#info']['width'] = $block_configuration['col_width'];
    }
    if (!empty($block_configuration['schedule_link'])) {
      if (UrlHelper::isExternal($block_configuration['schedule_link'])) {
        $link = $block_configuration['schedule_link'];
      } else {
        $link = $block_configuration['schedule_link'];
        $link = \Drupal::service('path_alias.manager')->getAliasByPath($link);
        //        $link = $link->toUriString();
        //        dpm($link);
      }
      $build['#info']['link'] = $link;
    }
    if (!empty($block_configuration['schedule_link_title'])) {
      $build['#info']['link_title'] = $block_configuration['schedule_link_title'];
    }
    $build['#theme'] = 'harno-front-study-times-block';
    if (isset($block_configuration['delta'])) {
      $build['#info']['delta'] = $block_configuration['delta'];
    }

    $node = \Drupal::routeMatch()->getParameter('node');
    if (!empty($node)) {

      if (!empty($node->get('nid'))) {
        $build['#info']['nid'] = $node->get('nid')->value;
      }
    } else {
      $build['#info']['nid'] = rand(1, 19999);
    }
    if (isset($block_configuration['attributes'])) {
      $build['#attributes'] = $block_configuration['attributes'];
    }
    if (isset($block_configuration['label'])) {
        $build['#info']['label'] = $block_configuration['label'];
        $build['#info']['label_display'] = $block_configuration['label_display'];
    }
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('webform')) {
      $build['#attached']['library'][] = 'webform/libraries.jquery.select2';
    }
    return $build;
  }
  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state)
  {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

//    $form['schedule_link_title'] = [
//      '#type' => 'textfield',
//      '#title' => $this->t('Schedule link button title'),
//      '#description' => $this->t('Button title for schedule link'),
//      '#default_value' => $config['schedule_link'] ?? '',
//    ];
    $form['schedule_link'] = [
      '#type' => 'linkit',
      '#title' => $this->t('Schedule link'),
      '#description' => $this->t('Link where schedule links'),
      '#autocomplete_route_name' => 'linkit.autocomplete',
      '#autocomplete_route_parameters' => [
        'linkit_profile_id' => 'default',
      ],
      '#default_value' => isset($config['schedule_link']) ? $config['schedule_link'] : '',
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
