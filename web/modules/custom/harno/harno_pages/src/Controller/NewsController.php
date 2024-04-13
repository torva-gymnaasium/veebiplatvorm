<?php

namespace Drupal\harno_pages\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class NewsController extends ControllerBase {

  /**
   *
   */
  public function index() {
    $build = [];
    $build['#theme'] = 'news-page';
    $build['#info']['older_tag'] = t('Older news');
    $galleries = $this->getNews();
    $academic_years = $this->getAcademicYears();
    $filter_form = \Drupal::formBuilder()->getForm('Drupal\harno_pages\Form\FilterForm', $academic_years,'news');
    // devel_dump($filter_form);
    //      $build['#academic_years'] = $academic_years;.
    $default_logo = \Drupal::service('config.factory')->get('harno_settings.settings')->get('general');
    $default_logo = $default_logo['logo'];

    $build['#academic_years'] = $filter_form;
    $build['#content'] = $galleries;
    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
    $tag_name= $localconf->get('news_our_story');
    $tag_name = $tag_name['name'];
    $build['#info']['tag_name']  = $tag_name;
    $build['#default_logo'] = $default_logo;
    $build['#pager'] = ['#type' => 'pager','#quantity'=>5];
    $build['#attached']['library'][] = 'harno_pages/harno_pages';
    $build['#cache'] = [
      'conttexts' => ['url.query_args'],
      'tags' => ['node_type:gallery','harno-config'],
    ];
    return $build;
  }

  /**
   *
   */
  public function getNews() {
    $bundle = 'article';
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    $query = \Drupal::entityQuery('node');
    $query->condition('status', 1);
    $query->condition('type', $bundle);
    $query->condition('langcode',$language);
    $query->sort('sticky','DESC');
    $query->sort('field_academic_year.entity.field_date_range', 'DESC');

    $query->sort('created', 'DESC');
    $count = 0;
    $searched = false;
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
        $list = [
          'field_distribution_blocks.entity:paragraph.field_content_blocks_100.entity:paragraph.field_body',

        ];
        $index = \Drupal\search_api\Entity\Index::load('search_index');
        $searches = ['newsSearch','newsSearchMobile'];

        foreach ($searches as $search){
          if (!empty($parameters[$search])) {
            $searched = true;
            $search_query = $index->query();
            $search_query->keys($parameters[$search]);
            $api_conditions = $search_query->createConditionGroup('OR');
            $api_conditions->addCondition('type', 'article');
            $search_query->addConditionGroup($api_conditions);
            $results_search = $search_query->execute();
            $count = $count + $results_search->getResultCount();
            if (!empty($results_search->getResultItems())) {
              $result_group = $query->orConditionGroup();
              foreach ($results_search->getResultItems() as $resultItem) {
                $nid = $resultItem->getOriginalObject()->get('nid')->value;
                $result_group->condition('nid', $nid);
              }
              $query->condition($result_group);
            }
          }
        }


      if(!empty($parameters['article_type'])){

        if($parameters['article_type']!='all') {
          $query->condition('field_article_type',$parameters['article_type']);
        }
//        if (strpos($parameters['article_type'],',')!==FALSE){
//          $art_type = explode(',',$parameters['article_type']);
//          $query->condition('field_article_type',reset($art_type));
//        }
      }
      if(!empty($parameters['article_type_mobile'])){

        if($parameters['article_type_mobile']!='all') {
          $query->condition('field_article_type', $parameters['article_type_mobile']);
        }
//        if (strpos($parameters['article_type_mobile'],',')!==FALSE){
//          $art_type = explode(',',$parameters['article_type_mobile']);
//          $query->condition('field_article_type',reset($art_type));
//        }
      }
      if(empty($parameters['newsSearch']) or empty($parameters['newsSearchMobile'])){
        $query->condition('title','','CONTAINS');
      }
      if (!empty($parameters['date_start'])) {
        $startDate = strtotime('midnight' . $parameters['date_start']);
      }
      if (!empty($parameters['date_end'])) {
        $endDate = strtotime('midnight' . $parameters['date_end'] . '+1 day');
      }
      if (!empty($parameters['date_start_mobile'])) {
        $startDate = strtotime('midnight' . $parameters['date_start_mobile']);
      }
      if (!empty($parameters['date_end_mobile'])) {
        $endDate = strtotime('midnight' . $parameters['date_end_mobile'] . '+1 day');
      }
      if (!empty($startDate)) {
        $query->condition('created', $startDate, '>=');
      }
      if (!empty($endDate)) {
        $query->condition('created', $endDate, '<=');
      }
      if (!empty($parameters['years']) or !empty($parameters['years-mobile'])) {
        if (!empty($parameters['years'])) {
          $years = $parameters['years'];
          if (is_array($years)) {

          } else {
            $years = explode(',', $parameters['years']);
          }
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
    if ($count==0 && $searched){
      /**
       *In case there were no results from content blocks titles
       */
      $query->condition('title','xxxxxxxxxxxx');
    }
    $query->pager(12);
    $entity_ids = $query->accessCheck()->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $nodes = $node_storage->loadMultiple($entity_ids);
    if (!empty($nodes)) {
      usort($nodes, function ($a, $b) {
        $asticky = $a->isSticky();
        if ($asticky) {
          $asticky = 1;
        }
        else {
          $asticky = 0;
        }
        $bsticky = $b->isSticky();
        if ($bsticky) {
          $bsticky = 1;
        }
        else {
          $bsticky = 0;
        }
        return $bsticky - $asticky;
      });
    }

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

    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('academic_year');

    if (!empty($terms)) {
      $active_terms = [];
      foreach ($terms as $academic_year) {
        $term_query = \Drupal::database()->select('node__field_academic_year', 'nfy');
        $term_query->fields('nfy');
        $term_query->condition('nfy.field_academic_year_target_id', $academic_year->tid);
        $term_query->condition('nfy.bundle', 'article');
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
        if ($term->hasTranslation($language)) {
          $term->getTranslation($language);
        }
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
          $active_terms['older'] = t('Older news');
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
