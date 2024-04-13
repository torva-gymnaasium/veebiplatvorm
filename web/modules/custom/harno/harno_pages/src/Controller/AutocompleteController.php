<?php
namespace Drupal\harno_pages\Controller;

//use Drupal\Core\Controller\ControllerBase;
//use GuzzleHttp\Psr7\Request;
//use Symfony\Component\HttpFoundation\JsonResponse;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Entity\Element\EntityAutocomplete;

class AutocompleteController extends ControllerBase {
  public function handleAutocomplete(Request $request){
    $result = [];
    if (!empty($request->query) && !empty($request->query->all())){
      $paramaters = $request->query->all();
      $referer = $request->headers->get('referer');
      $parts = parse_url($referer);
      if(!empty($parts['query'])){
        parse_str($parts['query'], $query_parts);
      }
      if(!empty($paramaters['q'])){
        $request_time = \Drupal::time()->getRequestTime();
        $text = $paramaters['q'];

    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
        $query = \Drupal::entityQuery('node')
          ->condition('status', 1)
          ->condition('changed', $request_time, '<')
          ->condition('type','gallery')
          ->condition('langcode',$language)
          ->condition('title', $text, 'CONTAINS');
        if(!empty($query_parts['years'])) {
          $year_group = $query->orConditionGroup();
          foreach ($query_parts['years'] as $year) {
            if ($year == 'older') {
              $neweryears = $this->getAcademicYears('gallery');
              if (!empty($neweryears['older'])) {
                unset($neweryears['older']);
              }
              $terms = \Drupal::entityTypeManager()
                ->getStorage('taxonomy_term')
                ->loadTree('academic_year');
              foreach ($terms as $term) {
                $term = \Drupal::entityTypeManager()
                  ->getStorage('taxonomy_term')
                  ->load($term->tid);
                if (isset($neweryears[$term->getName()])) {
                  continue;
                }
                else {
                  $year_group->condition('field_academic_year.entity.name', $term->getName());
                }
              }
            }
            $year_group->condition('field_academic_year.entity.name', $year);
          }
          $query->condition($year_group);
        }
        if (!empty($query_parts['date_start'] || !empty($query_parts['date_end']))){
          if (!empty($query_parts['date_start'])) {
            $startDate = strtotime('midnight' . $query_parts['date_start']);
          }
          if (!empty($query_parts['date_end'])) {
            $endDate = strtotime('midnight' . $query_parts['date_end'] . '+1 day');
          }
          if (!empty($startDate)) {
            $query->condition('created', $startDate, '>=');
          }
          if (!empty($endDate)) {
            $query->condition('created', $endDate, '<=');
          }
        }
        $nids = $query->accessCheck()->execute();
        $node_storage = \Drupal::entityTypeManager()->getStorage('node');
        $nodes = $node_storage->loadMultiple($nids);

        foreach ($nodes as $node) {

          $label = [
            $node->getTitle()
          ];

          $results[] = ['value' => $node->getTitle(),'label' => $node->getTitle()];
        }

      }
    }
    return new JsonResponse($results);
  }
  public function handleNewsAutocomplete(Request $request){
    $result = [];
    if (!empty($request->query) && !empty($request->query->all())){
      $paramaters = $request->query->all();
      $referer = $request->headers->get('referer');
      $parts = parse_url($referer);
      if(!empty($parts['query'])){
        parse_str($parts['query'], $query_parts);
      }
      if(!empty($paramaters['q'])){
        $text = $paramaters['q'];
        $query = \Drupal::entityQuery('node')
          ->condition('status', 1)
          ->condition('type','article')
          ->condition('title', $text, 'CONTAINS');
        if(!empty($query_parts['years'])) {
          $year_group = $query->orConditionGroup();
          foreach ($query_parts['years'] as $year) {
            if ($year == 'older') {
              $neweryears = $this->getAcademicYears('article');
              if (!empty($neweryears['older'])) {
                unset($neweryears['older']);
              }
              $terms = \Drupal::entityTypeManager()
                ->getStorage('taxonomy_term')
                ->loadTree('academic_year');
              foreach ($terms as $term) {
                $term = \Drupal::entityTypeManager()
                  ->getStorage('taxonomy_term')
                  ->load($term->tid);
                if (isset($neweryears[$term->getName()])) {
                  continue;
                }
                else {
                  $year_group->condition('field_academic_year.entity.name', $term->getName());
                }
              }
            }
            $year_group->condition('field_academic_year.entity.name', $year);
          }
          $query->condition($year_group);
        }
        if (!empty($query_parts['date_start'] || !empty($query_parts['date_end']))){
          if (!empty($query_parts['date_start'])) {
            $startDate = strtotime('midnight' . $query_parts['date_start']);
          }
          if (!empty($query_parts['date_end'])) {
            $endDate = strtotime('midnight' . $query_parts['date_end'] . '+1 day');
          }
          if (!empty($startDate)) {
            $query->condition('created', $startDate, '>=');
          }
          if (!empty($endDate)) {
            $query->condition('created', $endDate, '<=');
          }
        }
        $nids = $query->accessCheck()->execute();
        $node_storage = \Drupal::entityTypeManager()->getStorage('node');
        $nodes = $node_storage->loadMultiple($nids);

        foreach ($nodes as $node) {

          $label = [
            $node->getTitle()
          ];

          $results[] = ['value' => $node->getTitle(),'label' => $node->getTitle()];
        }

      }
    }
    if (!empty($results)) {
      return new JsonResponse($results);
    }
  }
  public function handleCalendarAutocomplete(Request $request){
    $result = [];
    if (!empty($request->query) && !empty($request->query->all())){
      $paramaters = $request->query->all();
      $referer = $request->headers->get('referer');
      $parts = parse_url($referer);
      if(!empty($parts['query'])){
        parse_str($parts['query'], $query_parts);
      }
      if(!empty($paramaters['q'])){
        $text = $paramaters['q'];
        $query = \Drupal::entityQuery('node')
          ->condition('status', 1)
          ->condition('type','calendar')
          ->condition('title', $text, 'CONTAINS')
          ->condition('body', '%'.$text.'%', 'LIKE')
          ->condition('field_event_type', $paramaters['type']);
        if (!empty($query_parts['date_start'] || !empty($query_parts['date_end']))){
          if (!empty($query_parts['date_start'])) {
            $startDate = strtotime('midnight' . $query_parts['date_start']);
          }
          if (!empty($query_parts['date_end'])) {
            $endDate = strtotime('midnight' . $query_parts['date_end'] . '+1 day');
          }
          if (!empty($startDate)) {
            $query->condition('created', $startDate, '>=');
          }
          if (!empty($endDate)) {
            $query->condition('created', $endDate, '<=');
          }
        }
        $nids = $query->accessCheck()->execute();
        $node_storage = \Drupal::entityTypeManager()->getStorage('node');
        $nodes = $node_storage->loadMultiple($nids);

        foreach ($nodes as $node) {

          $label = [
            $node->getTitle()
          ];

          $results[] = ['value' => $node->getTitle(),'label' => $node->getTitle()];
        }

      }
    }
    if (!empty($results)) {
      return new JsonResponse($results);
    }
  }

  /**
   *
   */
  public function getAcademicYears($type=null) {
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('academic_year');

    if (!empty($terms)) {
      $active_terms = [];
      foreach ($terms as $academic_year) {
        $term_query = \Drupal::database()->select('node__field_academic_year', 'nfy');
        $term_query->fields('nfy');
        $term_query->condition('nfy.field_academic_year_target_id', $academic_year->tid);
        if(isset($type)){
          $term_query->condition('nfy.bundle', $type);
        }
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
    foreach ($active_terms as $key => $term) {
      $term = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->load($key);
      $start = $term->get('field_date_range')->getValue()[0]['value'];
      $end = $term->get('field_date_range')->getValue()[0]['end_value'];
      $term->{'#start_year'} = $start;
      $terms_by_year[$key] = $term;
    }
    usort($terms_by_year, function ($a, $b) {
      if (!empty($a->{'#start_year'}) && !empty($b->{'#start_year'})) {
        $aweight = strtotime($a->{'#start_year'});
        $bweight = strtotime($b->{'#start_year'});
        return $bweight - $aweight;
      }
    });
    $active_terms = [];
    $count = 0;
    foreach ($terms_by_year as $term) {

      if ($count > 4) {
        $active_terms['older'] = t('Older galleries');
        break;
      }
      $active_terms[$term->getName()] = $term->getName();
      $count++;
    }
    if (!empty($active_terms)) {
      return $active_terms;
    }
  }
}
