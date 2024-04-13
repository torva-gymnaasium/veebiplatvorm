<?php
/**
 * Controller for controllingg Galleries.
 */
namespace Drupal\harno_pages\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class GalleriesController extends ControllerBase {

  /**
   *
   */
  public function index() {
    $build = [];
    $build['#theme'] = 'galleries-page';
    $build['#info']['older_tag'] = t('Older news');
    $galleries = $this->getGalleries();
    $academic_years = $this->getAcademicYears();
    $filter_form = \Drupal::formBuilder()->getForm('Drupal\harno_pages\Form\FilterForm', $academic_years);
    // devel_dump($filter_form);
    //      $build['#academic_years'] = $academic_years;.
    $build['#academic_years'] = $filter_form;
    $build['#content'] = $galleries;
    $build['#pager'] = [
      '#type' => 'pager',
      '#quantity'=>5];
    $build['#attached']['library'][] = 'harno_pages/harno_pages';
//    $build['#attached']['library'][] = 'harno_pages/filter_removal';
    $build['#cache'] = [
      'conttexts' => ['url.query_args'],
      'tags' => ['node_type:gallery'],

    ];
    return $build;
  }

  /**
   *
   */
  public function getGalleries() {
    $bundle = 'gallery';
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', $bundle);
    $query->sort('field_academic_year.entity.field_date_range', 'DESC');
    $query->sort('created', 'DESC');
    $query->condition('langcode',$language);
    if (!empty($_REQUEST)) {

      if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $parameters = $_GET;
      }
      else{
        $parameters = $_POST;
      }
      if(!empty($_REQUEST['_wrapper_format'])){
        if(isset($_REQUEST['page'])){
          $_REQUEST['page'] = 0;
          if(isset($_REQUEST['page'])){
            $_REQUEST['page']=0;
            $existingQuery = \Drupal::service('request_stack')->getCurrentRequest()->query->all();
            $existingQuery = \Drupal::service('request_stack')->getCurrentRequest()->query->remove('page');
          }
        }

      }
      if (!empty($parameters['gallerySearch'])) {
        $query->condition('title',$parameters['gallerySearch'], 'CONTAINS');
      }
      if (!empty($parameters['gallerySearchMobile'])) {
        $query->condition('title', $parameters['gallerySearchMobile'], 'CONTAINS');
      }
      if(empty($parameters['gallerySearch']) or empty($parameters['gallerySearchMobile'])){
        $query->condition('title','','CONTAINS');
      }
      if (!empty($parameters['date_start'])) {
        $startDate = strtotime('midnight' . $parameters['date_start']);
      }
      if (!empty($parameters['date_end'])) {
        $endDate = strtotime('midnight' . $parameters['date_end'] . '+1 day');
      }
      if (!empty($startDate)) {
        $query->condition('created', $startDate, '>=');
      }
      if (!empty($endDate)) {
        $query->condition('created', $endDate, '<=');
      }
      if (!empty($parameters['years']) or !empty($parameters['years-mobile'])) {
        $years = $parameters['years'];
        if (is_array($years)) {

        }
        else {
          $years = explode(',', $parameters['years']);
        }
        if (!empty($parameters['years-mobile'])){
          if (is_array($parameters['years-mobile'])){
            foreach ($parameters['years-mobile'] as $mobile_year_key => $mobile_year){
              $years[$mobile_year_key]=$mobile_year;
            }
          }
          else {
            $years_mobile = explode(',', $parameters['years-mobile']);
          }
        }
        // devel_dump($years);
        $year_group = $query->orConditionGroup();
        foreach ($years as $year) {
          if ($year == 'older') {
            $neweryears = $this->getAcademicYears();
            if (!empty($neweryears['older'])) {
              unset($neweryears['older']);
            }
            $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('academic_year');
            foreach ($terms as $term) {
              $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($term->tid);
              if (isset($neweryears[$term->getName()])) {
                continue;
              }
              else {
                $year_group->condition('field_academic_year.entity.name', $term->getName());
              }
            }
          }
          else {
            $year_group->condition('field_academic_year.entity.name', $year);
          }
        }
        $query->condition($year_group);
      }
    }
    $query->pager(12);
    $entity_ids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $node_storage->loadMultiple($entity_ids);
    $nodes_grouped = [];
    foreach ($nodes as $node) {
      $node = $node->getTranslation($language);
      if (!empty($node->get('field_academic_year'))) {
        if (!empty($node->get('field_academic_year')->entity)) {
          if (!empty($node->get('field_academic_year')->entity->getName())) {
            $academic_year = $node->get('field_academic_year')->entity->getName();
            $nodes_grouped[strval($academic_year)][] = $node;
          }
        }
      }
    }
    return $nodes_grouped;
  }

  /**
   *
   */
  public function getAcademicYears() {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('academic_year');

    if (!empty($terms)) {
      $active_terms = [];
      foreach ($terms as $academic_year) {
        $term_query = \Drupal::database()->select('node__field_academic_year', 'nfy');
        $term_query->fields('nfy');
        $term_query->condition('nfy.field_academic_year_target_id', $academic_year->tid);
        $term_query->condition('nfy.bundle', 'gallery');
        $term_query->range(0, 1);
        $results = $term_query->execute();
        while ($row = $results->fetchAllAssoc('field_academic_year_target_id')) {
          if (!empty($row)) {
            $active_terms[$academic_year->tid] = $academic_year->name;
            break;
          }
        }
      }
    }
    if (!empty($active_terms)) {
      foreach ($active_terms as $key => $term) {
        $term = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->load($key);
        $start = $term->get('field_date_range')->getValue()[0]['value'];
        $end = $term->get('field_date_range')->getValue()[0]['end_value'];
        $term->{'#start_year'} = $start;
        $terms_by_year[$key] = $term;
      }
    }
    if (!empty($terms_by_year)) {
      usort($terms_by_year, function ($a, $b) {
        if (!empty($a->{'#start_year'}) && !empty($b->{'#start_year'})) {
          $aweight = strtotime($a->{'#start_year'});
          $bweight = strtotime($b->{'#start_year'});
          return $bweight - $aweight;
        }
      });
    }

    $active_terms = [];
    $count = 0;
    if (!empty($terms_by_year)) {
      foreach ($terms_by_year as $term) {

        if ($count > 4) {
          $active_terms['older'] = t('Older galleries');
          break;
        }
        $active_terms[$term->getName()] = $term->getName();
        $count++;
      }
    }
    if (!empty($active_terms)) {
      return $active_terms;
    }
  }

}
