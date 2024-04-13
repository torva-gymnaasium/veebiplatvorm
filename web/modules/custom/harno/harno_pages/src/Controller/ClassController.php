<?php

namespace Drupal\harno_pages\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class ClassController extends ControllerBase {

  /**
   *
   */
  public function index() {
    $build = [];
    $build['#theme'] = 'class-page';
    $contacts = $this->getAlumniList();
    $time = $this->getAlumniList(true);
    $build['#content'] = $contacts;
    $build['#time'] = $time;
    $build['#attached']['library'][] = 'harno_pages/harno_pages';
    $build['#cache'] = [
      'conttexts' => ['url.query_args'],
      'tags' => ['node_type:class'],
    ];
    return $build;
  }

  /**
   *
   */
  public function getAlumniList($time = false) {
    $bundle = 'class';

    $t = [];
    // Return latest node and first node creation date
    if($time){
      $query = \Drupal::entityQuery('node');
      $query->condition('status', 1);
      $query->condition('type', $bundle);

      $clone = clone $query;
      $clone->sort('changed', 'DESC');
      $clone->range(0,1);
      $entity_id = $clone->accessCheck()->execute();
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $nodes = $node_storage->loadMultiple($entity_id);
      foreach ($nodes as $node) {
        $t['changed'] = $node->get('changed')->getValue()[0]['value'];
      }
      $query->sort('created', 'ASC');
      $query->range(0,1);
      $entity_id = $query->accessCheck()->execute();
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $nodes = $node_storage->loadMultiple($entity_id);
      foreach ($nodes as $node) {
        $t['created'] = $node->get('created')->getValue()[0]['value'];
      }
      return $t;
    }
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', $bundle);
    $query->condition('langcode',[$language, 'und'], 'IN');
    $query->sort('field_weight', 'DESC');

    $entity_ids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $node_storage->loadMultiple($entity_ids);

    $nodes_grouped = [];
    $node_count = count($nodes);
    if(!empty($nodes)){
      foreach ($nodes as $node) {
        if ($node->hasTranslation($language)) {
          $node_trans = $node->getTranslation($language);
          $node = $node_trans;
        }
        $nodes_grouped[] = [
          'title' => $node->label(),
          'url' => $node->toUrl()->toString(),
        ];
      }
      $splitter = 4;
      if($node_count <= 1){
        $splitter = 1;
      }
      $split = round(($node_count / $splitter),0,PHP_ROUND_HALF_UP);
      $nodes_grouped = array_chunk($nodes_grouped, $split);
      foreach ($nodes_grouped as $key => $items){
        if(isset($nodes_grouped[4])){
          $last_element = reset($nodes_grouped[1]);
          array_shift($nodes_grouped[1]);
          $nodes_grouped[0][] = $last_element;
          $last_element = reset($nodes_grouped[2]);
          array_shift($nodes_grouped[2]);
          $nodes_grouped[1][] = $last_element;
          $last_element = reset($nodes_grouped[3]);
          array_shift($nodes_grouped[3]);
          $nodes_grouped[2][] = $last_element;
          $last_element = reset($nodes_grouped[4]);
          array_shift($nodes_grouped[4]);
          $nodes_grouped[3][] = $last_element;
          break;
        }
        else {
          foreach ($items as $item) {
            if(!empty($nodes_grouped[1])) {
              if ((abs(count($nodes_grouped[0]) - count($nodes_grouped[1])) >= 2)) {
                $last_element = end($nodes_grouped[0]);
                array_pop($nodes_grouped[0]);
                $nodes_grouped[1][] = $last_element;
                break;
              }
            }
            if(!empty($nodes_grouped[3])) {
              if ((abs(count($nodes_grouped[2]) - count($nodes_grouped[3])) >= 2)) {
                $last_element = end($nodes_grouped[2]);
                array_pop($nodes_grouped[2]);
                $nodes_grouped[3][] = $last_element;
                break;
              }
            }
          }
        }
      }
    }

    return $nodes_grouped;
  }

}
