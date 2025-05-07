<?php
/**
 * Controller for controllingg Galleries.
 */
namespace Drupal\harno_pages\Controller;

use DateTime;
use DateTimeZone;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\field\Entity\FieldConfig;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Plugin\views\argument\Taxonomy;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\calendar_link\CalendarLinkExtension;

/**
 *
 */
class CalendarController extends ControllerBase {

  /**
   *
   */
  public function index() {

    if(\Drupal::routeMatch()->getRouteName() == 'harno_pages.miniCalendar'){
      $type = $_REQUEST['type'];
      $events = $this->getEvents($type, true);
      $build = [];
      $build['events'] = $events;
      return new JsonResponse($build);
    }
    elseif (\Drupal::routeMatch()->getRouteName() == 'harno_pages.calendar') {
      $type = '2';
    }
    else{
      $type = '1';
    }
    $build = [];
    $build['#theme'] = 'calendar-page';
    $events = $this->getEvents($type);
    $build['#type'] = $type;
    $academic_years = $this->getEventType($type);
    $filter_form = \Drupal::formBuilder()->getForm('Drupal\harno_pages\Form\FilterForm', $academic_years, 'calendar',$type);
    $build['#filters'] = $filter_form;
    $build['#content'] = $events;
    $build['#pager'] = ['#type' => 'pager','#quantity'=>10];
    $build['#para'] = $_GET;
    $build['#para']['dates'] = $this->getDates($build['#para']);
    $build['#attached']['library'][] = 'harno_pages/harno_pages';
    $build['#attached']['library'][] = 'harno_pages/calendar_js';
//    $build['#attached']['library'][] = 'harno_pages/mobile-filter';
    $build['#cache'] = [
      'conttexts' => ['url.query_args'],
      'tags' => ['node_type:calendar'],

    ];
    return $build;
  }
  private function getDates($type){
    if (isset($type['days'])){
      switch ($type['days']){
        case 'today':
          $today = date('Y-m-d', strtotime('today'));
          return ['start'=> $today];
          break;

        case 'week':
          $monday = date('Y-m-d', strtotime('Monday this week'));
          $sunday = date('Y-m-d', strtotime('Sunday this week'));
          return ['start'=>$monday,'end'=>$sunday];
          break;

        case 'month':
          $firstDay = date('Y-m-d', strtotime('First day of this month'));
          $lastDay = date('Y-m-d', strtotime('Last day of this month'));

          return ['start'=>$firstDay,'end'=>$lastDay];
          break;
      }
    }
    if (empty($type['days'])){
      $firstDay = strtotime('First day of this month');
      $lastDay = strtotime('Last day of this month');
      $start = date('d',$firstDay).'. '. t(date('F',$firstDay),[],['context'=>'Long month name']);
      $end = date('d',$lastDay).'. '. t(date('F',$lastDay),[],['context'=>'Long month name']);
      return ['start'=>$start,'end'=>$end,'days'=>'month'];
    }
  }
  /**
   *
   */
  public function getEvents($type = null, $dateList = false, $form_state = false) {
    $bundle = 'calendar';
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $keywords = [
      1 => 'training',
      2 => 'event'
    ];
    if($dateList){
      $query = \Drupal::entityQuery('node');
      $query->condition('status', 1);
      $query->condition('type', $bundle);
      $query->condition('field_event_type', $type);
      $query->condition('langcode',[$language, 'und'], 'IN');
      $clone = clone $query;
//      $monday = date('Y-m-d', strtotime('today'));
//      $clone->condition('field_start_date.value', $monday, '=');
      $entity_id = $clone->accessCheck()->execute();
      $node_storage = \Drupal::entityTypeManager()->getStorage('node');
      $nodes = $node_storage->loadMultiple($entity_id);
      $array = [];
      foreach ($nodes as $node){
        $array[] = date('d.m.Y', strtotime($node->field_start_date->value));
      }
      return $array;
    }
    if (!empty($_REQUEST)) {
      if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $parameters = $_GET;
      } else {
        $parameters = $_POST;
      }
      if (!empty($_REQUEST['_wrapper_format'])) {
        if (isset($_REQUEST['page'])) {
          $_REQUEST['page'] = 0;
          if (isset($_REQUEST['page'])) {
            $_REQUEST['page'] = 0;
            $existingQuery = \Drupal::service('request_stack')->getCurrentRequest()->query->all();
            $existingQuery = \Drupal::service('request_stack')->getCurrentRequest()->query->remove('page');
          }
        }
      }
    }
    foreach ($_REQUEST as &$parameter) {
      if (!is_array($parameter)) {
        $parameter = Xss::filter($parameter);
      }
      else {
        foreach ($parameter as &$para) {
          $para = Xss::filter($para);
        }
      }
    }
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $database = \Drupal::service('database');
    $new_query = $database->select('node_field_data', 'nfd');
    $new_query->fields('nfd');
    $new_query->condition('nfd.type', 'calendar');
    $new_query->condition('status', 1);
    $language_or = $new_query->orConditionGroup();
    $language_or->condition('nfd.langcode', $language);
    $language_or->condition('nfd.langcode', 'und');
    $new_query->condition($language_or);
    if (!empty($parameters['calendarSearch'])) {
      $or = $new_query->orConditionGroup()
        ->condition('nb.body_value', '%' . $parameters['calendarSearch'] . '%', 'LIKE')
        ->condition('nfd.title', '%' . $parameters['calendarSearch'] . '%', 'LIKE');
      $new_query->condition($or);
    }
    $new_query->join('node__field_event_type', 'nfet', 'nfd.nid=nfet.entity_id');
    if (!empty($type)) {
      $new_query->condition('nfet.field_event_type_value', $type);
    }
    $get_month = FALSE;
    if (empty($parameters)) {
      $get_month = TRUE;
    }
    if (isset($parameters['date_start']) or isset($parameters['date_end'])) {
      $get_month = FALSE;
    }
    elseif (isset($parameters['days']) or isset($parameters['days_mobile'])){
      $get_month = FALSE;
    }
    else {
      $get_month = TRUE;
    }
//    dump($parameters);
    if (!empty($parameters['event_type']) || !empty($parameters['event_type_mobile'])) {
      $event_types = !empty($parameters['event_type']) ? $parameters['event_type'] : $parameters['event_type_mobile'];
      switch ($type) {
        case '1':
          $or_type = $new_query->orConditionGroup();
          $new_query->join('node__field_training_keywords', 'nftk', 'nfd.nid=nftk.entity_id');
          foreach ($event_types as $key => $event_type) {
            $or_type->condition('nftk.field_training_keywords_target_id', $event_type, '=');
          }
          $new_query->condition($or_type);
          break;

        case '2':
          $or_type = $new_query->orConditionGroup();
          $new_query->join('node__field_event_keywords', 'nfek', 'nfd.nid=nfek.entity_id');
          foreach ($event_types as $key => $event_type) {
            $or_type->condition('nfek.field_event_keywords_target_id', $event_type, '=');
          }
          $new_query->condition($or_type);
          break;

        default:
          # code...
          break;
      }
    }
    if (!empty($parameters['date_start'])|| !empty($parameters['date_start_mobile'])) {
      $date_start = !empty($parameters['date_start']) ? $parameters['date_start'] : $parameters['date_start_mobile'];
      if (!empty($parameters['date_end'])||!empty($parameters['date_end_mobile'])) {
        $date_end = !empty($parameters['date_end']) ? $parameters['date_end'] : $parameters['date_end_mobile'];
        $startDate = date('Y-m-d', strtotime($date_start));
        $endDate = date('Y-m-d', strtotime($date_end));
        $or = $new_query->orConditionGroup()
          ->condition('nfsd.field_start_date_value', $startDate, '>=')
          ->condition('nfeed.field_event_end_date_value', $endDate, '>=');
        $and = $new_query->andConditionGroup()
          ->condition('nfsd.field_start_date_value', $endDate, '<=')
          ->condition('nfsd.field_start_date_value', $startDate, '>=');
        $or->condition($and);
        $new_query->condition($or);
        $new_query->condition('nfsd.field_start_date_value', $endDate, '<=');
      }
      else {
        $startDate = date('Y-m-d', strtotime($date_start));
        $or = $new_query->orConditionGroup()
          ->condition('nfsd.field_start_date_value', $startDate, '>=');
        $and = $new_query->andConditionGroup()
          ->condition('nfsd.field_start_date_value', $startDate, '<=')
          ->condition('nfeed.field_event_end_date_value', $startDate, '>=');
        $or->condition($and);
        $new_query->condition($or);
      }
      unset($parameters['days']);
      \Drupal::request()->query->remove('days');
    }
    else {
      if (!empty($parameters['days']) or !empty($parameters['days_mobile']) or $get_month) {
        if (isset($parameters['days']) || isset($parameters['days_mobile'])) {
          $days = !empty($parameters['days']) ? $parameters['days'] : $parameters['days_mobile'];
        }
        if ($get_month) {
          $days = 'month';
        }
        switch ($days) {
          case 'today':
            $today = date('Y-m-d', strtotime('today'));
            $or = $new_query->orConditionGroup()
              ->condition('nfsd.field_start_date_value', $today, '=');
            $and = $or->andConditionGroup()
              ->condition('nfsd.field_start_date_value', $today, '<=')
              ->condition('nfeed.field_event_end_date_value', $today, '>=');
            $or->condition($and);
            $new_query->condition($or);
            $parameters['date_start'] = $today;
            break;

          case 'week':
            $monday = date('Y-m-d', strtotime('Monday this week'));
            $sunday = date('Y-m-d', strtotime('Sunday this week'));
            // Within this week
            $and = $new_query->andConditionGroup()
              ->condition('nfsd.field_start_date_value', $monday, '>=')
              ->condition('nfeed.field_event_end_date_value', $sunday, '<=');
            $or = $new_query->orConditionGroup();
            $or->condition($and);
            // Before this week and goes after this week start
            $and = $new_query->andConditionGroup();
            $and->condition('nfsd.field_start_date_value', $monday, '<=');
            $and->condition('nfeed.field_event_end_date_value', $monday, '>=');
            $or->condition($and);
            $and = $new_query->andConditionGroup();
            $and->condition('nfsd.field_start_date_value', $sunday, '<=');
            $and->condition('nfsd.field_start_date_value', $monday, '>=');
            $and->condition('nfeed.field_event_end_date_value', 'NULL', 'IS NULL');
            $or->condition($and);
            $new_query->condition($or);
            break;

          case 'month':
            $firstDay = date('Y-m-d', strtotime('First day of this month'));
            $lastDay = date('Y-m-d', strtotime('Last day of this month'));

            $or = $new_query->orConditionGroup();
            //Within this month
            $and = $new_query->andConditionGroup()
              ->condition('nfsd.field_start_date_value', $firstDay, '>=')
              ->condition('nfeed.field_event_end_date_value', $lastDay, '<=');
            $or->condition($and);
            //Starts before and ends after this month start.
            $and = $new_query->andConditionGroup();
            $and->condition('nfsd.field_start_date_value', $firstDay, '<=');
            $and->condition('nfeed.field_event_end_date_value', $firstDay, '>=');
            $or->condition($and);
            $and = $new_query->andConditionGroup();
            $and->condition('nfsd.field_start_date_value', $lastDay, '<=');
            $and->condition('nfsd.field_start_date_value', $firstDay, '>=');
            $and->condition('nfeed.field_event_end_date_value', 'NULL', 'IS NULL');
            $or->condition($and);
            $new_query->condition($or);
            break;
        }
      }
    }
    $new_query->leftJoin('node__field_start_date', 'nfsd', 'nfd.nid = nfsd.entity_id');
    $new_query->leftJoin('node__body', 'nb', 'nfd.nid = nb.entity_id');
    $new_query->leftJoin('node__field_event_end_date', 'nfeed', 'nfd.nid = nfeed.entity_id');
    $new_query->leftJoin('node__field_event_start_time', 'nfest', 'nfd.nid = nfest.entity_id');
    $new_query->fields('nfsd');
    $new_query->fields('nfest');
    $new_query->fields('nfeed');
    $today_date = date('Y-m-d', time());
    $new_query->addExpression(
      'IF(nfsd.field_start_date_value=\'' . $today_date . '\' AND nfeed.field_event_end_date_value is null, IF(nfest.field_event_start_time_value is not null,nfest.field_event_start_time_value,\'0\'), null)', 'todayonly'
    );
    $new_query->addExpression(
      'IF(nfsd.field_start_date_value=\'' . $today_date . '\' AND CAST(\'' . $today_date . '\' AS DATE)<CAST(nfeed.field_event_end_date_value AS DATE), nfd.nid, null)', 'todaymultiple'
    );
    $new_query->addExpression(
      'IF(CAST(nfsd.field_start_date_value AS DATE)>CAST(\'' . $today_date . '\' AS DATE) , nfsd.field_start_date_value, null)', 'aftertoday'
    );
    $new_query->addExpression(
      'IF(CAST(nfsd.field_start_date_value AS DATE)<=CAST(\'' . $today_date . '\' AS DATE) AND CAST(\'' . $today_date . '\' AS DATE)<CAST(nfeed.field_event_end_date_value AS DATE), CAST(nfsd.field_start_date_value AS DATE), CAST(nfsd.field_start_date_value AS DATE))', 'betweendates'
    );
    $new_query->addExpression(
      'IF(CAST(nfeed.field_event_end_date_value AS DATE)<=CAST(\'' . $today_date . '\' AS DATE), CAST(nfsd.field_start_date_value AS DATE), null)', 'endsbeforetoday'
    );
    $new_query->addExpression(
      'IF(ISNULL(nfeed.field_event_end_date_value) AND CAST(nfsd.field_start_date_value AS DATE)<=CAST(\'' . $today_date . '\' AS DATE), CAST(nfsd.field_start_date_value AS DATE), null)', 'endsbeforetodaynoend'
    );
    $new_query->orderBy('endsbeforetoday', 'ASC');
    $new_query->orderBy('todayonly', 'DESC');
    $new_query->orderBy('endsbeforetodaynoend', 'ASC');
    $new_query->orderBy('todaymultiple', 'DESC');
    $new_query->orderBy('betweendates', 'ASC');
    $new_query->orderBy('aftertoday', 'DESC');
    $new_query = $new_query->extend(PagerSelectExtender::class)->limit(10);
    $matches = $new_query->execute()->fetchAll();
    $nid_list = [];
    foreach ($matches as $match) {
      $nid_list[$match->nid] = $match->nid;
    }
    $new_nodes = $node_storage->loadMultiple($nid_list);
//    dump($new_nodes);
    $info['nodes'] = [];
    $info['key'] = $type;
    foreach ($new_nodes as $node) {
//      $node = $node->getTranslation($language);
      $render = \Drupal::entityTypeManager()->getViewBuilder('node')->view($node, 'teaser');
      $info['nodes'][] = $render;
    }
    if(empty($info['nodes'])){
      $info = [];
    }

    return $info;
  }

  /**
   *
   */
  public function getEventType($type = null) {
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if($type == 1){
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('training_keywords');
      $key = 'training';
    }
    else{
      $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadTree('event_keywords');
      $key = 'event';
    }

    if (!empty($terms)) {
      $active_terms = [];
      foreach ($terms as $academic_year) {
        $term = Term::load($academic_year->tid);
        $term_query = \Drupal::database()->select('node__field_'.$key.'_keywords', 'nfy');
        $term_query->fields('nfy');
        $term_query->condition('nfy.field_'.$key.'_keywords_target_id', $academic_year->tid);
        $term_query->condition('nfy.bundle', 'calendar');
        $term_query->range(0, 1);
        $results = $term_query->execute();
        while ($row = $results->fetchAllAssoc('field_'.$key.'_keywords_target_id')) {
          if (!empty($row)) {
            if(!empty($term->hasTranslation($language))){
              $active_terms[$academic_year->tid] = $term->getTranslation($language)->getName();
            }
            break;
          }
        }
      }
    }
    if (!empty($active_terms)) {
      return $active_terms;
    }
  }

  /**
   *
   */
  public function get_calendar_links($nid = null){

    if($nid == null){
      return false;
    }

    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $node_storage->load($nid);
    $tz = 'Europe/Tallinn';
    $timestamp = time();
    $start_sate = strtotime($node->get('field_start_date')->value) + $node->get('field_event_start_time')->value;
    $dt = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
    $dt->setTimestamp($start_sate); //adjust the object to correct timestamp
    if($node->get('field_show_end_date')->value == 1) {
      $end_datetime = strtotime($node->get('field_event_end_date')->value) + $node->get('field_event_end_time')->value;
      $et = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
      $et->setTimestamp($end_datetime); //adjust the object to correct timestamp
    }
    else{
      $end_datetime = strtotime($node->get('field_start_date')->value) + $node->get('field_event_end_time')->value;
      $et = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
      $et->setTimestamp($end_datetime); //adjust the object to correct timestamp
    }

    $calendar_links = \Drupal::service('calendar_link.twig_extension');
    $title = $node->getTitle();
    $from = $dt;
    $to = $et;
    $all_day = $node->get('field_all_day')->value;
    $price = empty($node->get('field_price')->value) ? '' : $node->get('field_price')->value;
    $url = $node->toUrl()->setAbsolute()->toString();
    $description = $node->body->view('full')[0]['#text'] . '</br><strong>'.t('Link').': </strong>' .$url . '</br><strong>'.t('Price').': </strong>' . $price;
    $address = $node->get('field_venue')->value;
    $calendar_links = $calendar_links->calendarLinks($title, $from, $to, $all_day, $description, $address);
    $links = '';

    $links.= '
        <li>
          <a href="/ics/download/'.$node->id().'">
            <i class="mdi mdi-apple" aria-hidden="true"></i>
            Apple iCal</a>
        </li>
      ';
    foreach ($calendar_links as $key => $calendar_link){
      $online = '';
      if($key == 'yahoo' or $key == 'ics'){continue;}
      if($key == 'webOutlook'){
        $class = 'microsoft-outlook';
        $links.= '
        <li>
          <a href="/ics/download/'.$node->id().'">
            <i class="mdi mdi-'.$class.'" aria-hidden="true"></i>
            Outlook</a>
        </li>
        ';
       $links.= '
        <li>
          <a target="_blank" href="'.preg_replace('/^' . preg_quote('https://outlook.live.com', '/') . '/', 'https://outlook.office.com',$calendar_link['url']).'">
            <i class="mdi mdi-'.$class.'" aria-hidden="true"></i>
            '.$calendar_link['type_name'].' (Online - Office 365)</a>
        </li>
        ';

        $links.= '
        <li>
          <a target="_blank" href="'.$calendar_link['url'].'">
            <i class="mdi mdi-'.$class.'" aria-hidden="true"></i>
            '.$calendar_link['type_name'].' (Online - Live)</a>
        </li>
        ';
      }
      else{
        $class = $calendar_link['type_key'];
        $links.= '
        <li>
          <a target="_blank" href="'.$calendar_link['url'].'">
            <i class="mdi mdi-'.$class.'" aria-hidden="true"></i>
            '.$calendar_link['type_name'].'</a>
        </li>
      ';
      }
    }

    if(empty($links)){
      return false;
    }

    return [
      '#children' => '
        <div class="row" id="calendar_modal">
          <div class="col-12 md-12 sm-12">
            <div class="block">
              <div class="modal modal__event-calendar open" data-modal="true">
                <div class="focus-trap" tabindex="0"></div>
                <div class="modal__body">
                  <ul class="calendar-share-items">
                    '.$links.'
                  </ul>
                  <div class="focus-trap" tabindex="0"></div>
                </div><!--/modal__body-->
              </div><!--/modal modal__contact-->
            </div><!--/block-->
          </div><!--/col-12 md-12 sm-12-->
        </div><!--/row-->
      '
    ];

  }
}
