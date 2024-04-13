<?php

namespace Drupal\harno_pages\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;

/**
 *
 */
class ContactsController extends ControllerBase {

  /**
   *
   */
  public function index() {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $build = [];
    $build['#theme'] = 'contacts-page';
    $contacts = $this->getContacts();
    $time = $this->getContacts(true);
    $filters = $this->getFilters();
    $filter_form = \Drupal::formBuilder()->getForm('Drupal\harno_pages\Form\ContactsFilterForm', $filters,'contacts');
    $build['#contact_filters'] = $filter_form;
    $build['#content'] = $contacts;
    $build['#time'] = $time;
    $enabled_languages = \Drupal::languageManager()->getLanguages();
    if (count($enabled_languages) ==1) {
      $build['#no_language_prefix'] = 1;
    }
    $build['#language'] = $language;
    $build['#attached']['library'][] = 'harno_pages/harno_pages';
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('webform')) {
      $build['#attached']['library'][] = 'webform/libraries.jquery.select2';
    }
    $build['#attached']['library'][] = 'harno_pages/select2fix';
    $build['#cache'] = [
      'conttexts' => ['url.query_args'],
      'tags' => ['node_type:worker'],
    ];
    return $build;
  }

  /**
   *
   */
  public function getContacts($time = false) {
    $bundle = 'worker';
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $t = [];
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

    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', $bundle);
    #$query->condition('langcode',$language);
    $query->sort('created', 'DESC');
    if (!empty($_REQUEST)) {
      if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $parameters = $_GET;
      }
      else{
        $parameters = $_POST;
      }
      if(!empty($parameters['positions']) or !empty($parameters['positions_mobile'])){
        $key = empty($parameters['positions']) ? $parameters['positions_mobile']  : $parameters['positions'] ;
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('positions');
        foreach ($terms as $term) {
          $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term->tid);
          if($term->getName() == $key) {
            $query->condition('field_position.entity.name', $term->getName());
          }
        }
      }
      if(!empty($parameters['departments']) or !empty($parameters['departments_mobile'])){
        $key = empty($parameters['departments']) ? $parameters['departments_mobile']  : $parameters['departments'] ;
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('departments');
        foreach ($terms as $term) {
          $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term->tid);
          if($term->getName() == $key) {
            $query->condition('field_department.entity.field_department.entity.name', $term->getName());
          }
        }
      }

      if (!empty($parameters['contactsSearch'])) {
        $textsearchGroup = $query->orConditionGroup();
        $textsearchGroup->condition('title',$parameters['contactsSearch'], 'CONTAINS');
        $textsearchGroup->condition('field_phone',$parameters['contactsSearch'],'CONTAINS');
        $query->condition($textsearchGroup);
      }
      if (!empty($parameters['contactsSearchMobile'])) {
        $textsearchGroup = $query->orConditionGroup();
        $textsearchGroup->condition('title',$parameters['contactsSearchMobile'], 'CONTAINS');
        $textsearchGroup->condition('field_phone',$parameters['contactsSearchMobile'],'CONTAINS');
        $query->condition($textsearchGroup);

      }
      if(empty($parameters['contactsSearch']) or empty($parameters['contactsSearchMobile'])){
        $query->condition('title','','CONTAINS');
      }
    }

    $entity_ids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $node_storage->loadMultiple($entity_ids);
    usort($nodes, function ($a, $b) {
      $asticky = $a->isSticky();
      if($asticky){
        $asticky = 1;
      }
      else{
        $asticky=0;
      }
      $bsticky = $b->isSticky();
      if($bsticky){
        $bsticky = 1;
      }
      else{
        $bsticky=0;
      }
        return $bsticky - $asticky;
    });
    usort($nodes, function($a,$b){
      if($a->get('changed')->value != null) {
        $achanged = $a->get('changed')->value;
      }
      else{
        $achanged = $a->get('created')->value;
      }
      if($b->get('changed')->value != null) {
        $bchanged = $b->get('changed')->value;
      } else {$bchanged = $b->get('created')->value; }
      $bchanged = $b->get('changed')->value;
      return $bchanged - $achanged;
    });

    $nodes_grouped = [];
    $i = 0;
    foreach ($nodes as $node) {
      #$node = $node->getTranslation($language);
      if (!empty($node->get('field_department'))) {
        foreach ($node->get('field_department') as $department){
          if (!empty($department->entity)) {
            $department_id = $department->entity->get('field_department')->getValue()[0]['target_id'];
            $worker_weight = $department->entity->get('field_weight')->getValue()['0']['value'];
            $taxonomy = Term::load($department_id);
            $taxonomy_term_trans = \Drupal::service('entity.repository')->getTranslationFromContext($taxonomy, $language);
            $nodes_grouped[$taxonomy_term_trans->getWeight()][strval($taxonomy_term_trans->getName())][$worker_weight][] = $node;
            if($taxonomy_term_trans->get('field_introduction') and !empty($taxonomy_term_trans->get('field_introduction')->getValue()[0]['value'])){
              $nodes_grouped[$taxonomy_term_trans->getWeight()][strval($taxonomy_term_trans->getName())]['description'] = $taxonomy_term_trans->get('field_introduction')->getValue()[0]['value'];
            }
            if(!isset($nodes_grouped[$taxonomy->getWeight()][strval($taxonomy_term_trans->getName())]['total'])){
              $nodes_grouped[$taxonomy_term_trans->getWeight()][strval($taxonomy_term_trans->getName())]['total'] = 1;
            }
            else {
              $nodes_grouped[$taxonomy_term_trans->getWeight()][strval($taxonomy_term_trans->getName())]['total'] = $nodes_grouped[$taxonomy_term_trans->getWeight()][strval($taxonomy_term_trans->getName())]['total'] + 1;
            }
            ksort($nodes_grouped[$taxonomy_term_trans->getWeight()][strval($taxonomy_term_trans->getName())]);
          }
        }
        $i++;
      }
    }
    if($i > 0) {
      $nodes_grouped['overall_total'] = $i;
    }
    ksort($nodes_grouped);
    return $nodes_grouped;
  }

  /**
   *
   */
  public function getFilters() {

    $positions = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('positions');
    $departments = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('departments');

    $active_terms = [];
    if (!empty($positions)) {
      $active_terms['positions'][''] = t('All');
      $active_terms['positions']['all'] = t('All');
      $bundle = 'worker';
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $query = \Drupal::entityQuery('node');
      $query->condition('status', 1);
      $query->condition('type', $bundle);
      #$query->condition('langcode',$language);
      $entity_ids = $query->accessCheck()->execute();
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $nodes = $node_storage->loadMultiple($entity_ids);
      foreach ($nodes as $node){
        $depart = $node->get('field_position')->getValue();
        foreach ($depart as $item){
          foreach ($positions as $position) {
            if ($position->tid == $item['target_id']) {
              $taxonomy_term = \Drupal\taxonomy\Entity\Term::load($position->tid);
              $taxonomy_term_trans = \Drupal::service('entity.repository')->getTranslationFromContext($taxonomy_term, $language);
              $active_terms['positions'][$position->name] = $taxonomy_term_trans->name->value;
            }
          }
        }
      }
    }
    if (!empty($departments)) {
      $active_terms['departments'][''] = t('All');
      $active_terms['departments']['all'] = t('All');

      $bundle = 'worker';
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      $query = \Drupal::entityQuery('node');
      $query->condition('status', 1);
      $query->condition('type', $bundle);
      #$query->condition('langcode',$language);
      $entity_ids = $query->accessCheck()->execute();
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $nodes = $node_storage->loadMultiple($entity_ids);
      foreach ($nodes as $node){
        $depart = $node->get('field_department')->getValue();
        foreach ($depart as $item){
          $para = Paragraph::load($item['target_id']);
          foreach ($departments as $department) {
            if ($department->tid == $para->get('field_department')->getValue()[0]['target_id']) {
              $taxonomy_term = \Drupal\taxonomy\Entity\Term::load($department->tid);
              $taxonomy_term_trans = \Drupal::service('entity.repository')->getTranslationFromContext($taxonomy_term, $language);
              $active_terms['departments'][$department->name] = $taxonomy_term_trans->name->value;
            }
          }
        }
      }
    }
    if (!empty($active_terms)) {
      return $active_terms;
    }
  }

}
