<?php

namespace Drupal\harno_pages\Form;

use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\harno_pages\Controller\CalendarController;
use Drupal\harno_pages\Controller\GalleriesController;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\harno_pages\Controller\NewsController;
use Drupal\media_library\Ajax\UpdateSelectionCommand;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Plugin\views\argument\Taxonomy;

/**
 *
 */
class FilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // TODO: Implement getFormId() method.
    return 'gallery_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $academic_years = NULL,$type=NULL,$calendar_type=null) {
    if (!empty($type)){
      $form['#storage']['type'] = $type;
    }
    if(!empty($calendar_type)){
      $form['#storage']['calendar_type'] = $calendar_type;
    }
    $form['#attributes']['data-plugin'] = 'filters';
    $form['#attributes']['role'] = 'filter';
    if (!empty($academic_years)) {
      if($type=='news'){
        $form['top_filters'] = [
          '#type' => 'fieldset',
          '#id' => 'news-topFilter',
        ];

        $articleoptions = $this->getArticleTypes();

        $form['top_filters']['years'] = [
          '#title' => t('Choose year'),
          // '#attributes' => ['name' => 'years'],
          '#id' => 'gallery-years',
          '#type' => 'checkboxes',
          '#attributes' => [
            'checkbox-type' => 'collect'
          ],
          '#ajax' => [
            'wrapper' => 'filter-target',
            'event' => 'change',
            'callback' => '::filterResults',
          ],
          '#my-id' => 'news-years',
          '#options' => $academic_years,
        ];
        $form['#storage']['other_label'] = t('Older news');
      }
      elseif ($type == 'calendar') {
        $title = t('Choose training type');
        if (\Drupal::routeMatch()->getRouteName() == 'harno_pages.calendar') {
          $title = t('Choose event type');
        }
        $form['event_type'] = [
          '#title' => $title,
          // '#attributes' => ['name' => 'years'],
          '#id' => 'gallery-years',
          '#type' => 'checkboxes',
          '#attributes' => [
            'checkbox-type' => 'collect'
          ],
          '#ajax' => [
            'wrapper' => 'filter-target',
            'event' => 'change',
            'callback' => '::filterResults',
          ],
          '#options' => $academic_years,
        ];
      }
      else {

        $form['#storage']['other_label'] = t('Older galleries');
        $form['years'] = [
          '#title' => t('Choose year'),
          // '#attributes' => ['name' => 'years'],
          '#id' => 'gallery-years',
          '#type' => 'checkboxes',
          '#attributes' => [
            'checkbox-type' => 'collect'
          ],
          '#ajax' => [
            'wrapper' => 'filter-target',
            'event' => 'change',
            'callback' => '::filterResults',
          ],
          '#options' => $academic_years,
        ];
      }
    }
    if ($type=='news'){
      $form['bottom'] = [
        '#type' => 'fieldset',
        '#id' => 'news-bottomFilter',
      ];
    }
    else{
      $form['bottom'] = [
        '#type' => 'fieldset',
        '#id' => 'galleries-bottomFilter',
      ];
    }
    if ($type=='news'){
      $articleoptions = $this->getArticleTypes();
//      dump($articleoptions);
//      dump($articleoptions);
      $form['bottom']['article_type'] = [
        '#type' => 'radios',
        '#id' => 'article_type',
        '#ajax' => [
          'wrapper' => 'filter-target',
          'event' => 'change',
          'callback' => '::filterResults',
        ],
        '#attributes'=>[
          'checkbox-type'=>'news-type'
        ],
        '#default_value' => 'all',
        '#options' => $articleoptions,
      ];
    }
    if($type == 'calendar'){
      $options = [
        'today' => $this->t('Today'),
        'all' => $this->t('All'),
        'week' => $this->t('Week'),
        'month' => $this->t('Month'),
      ];
      $form['bottom']['days'] = [
        '#type' => 'radios',
        '#options' => $options,
        '#default_value' => 'month',
        '#ajax' => [
          'wrapper' => 'filter-target',
          'event' => 'change',
          'keypress' => TRUE,
          'callback' => '::filterResults',
          'disable-refocus' => TRUE,
        ],
      ];
    }
    $form['bottom']['date_start'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'alt' => t('Filter starting from'),
        'data-placeholder' => t('dd.mm.yyyy')
      ],
      '#title' => t('Show from'),
      '#ajax' => [
        'wrapper' => 'filter-target',
        'event' => 'change',
        'keypress' => TRUE,
        'callback' => '::filterResults',
        'disable-refocus' => TRUE,
      ],
    ];
    $form['bottom']['date_end'] = [
      '#type' => 'textfield',
      '#attributes' => [
        'alt' => t('Filter ending with'),
        'data-placeholder' => t('dd.mm.yyyy')
      ],
      '#title' => t('Show to'),
      '#ajax' => [
        'wrapper' => 'filter-target',
        'event' => 'change',
        'keypress' => TRUE,
        'callback' => '::filterResults',
        'disable-refocus' => TRUE,
      ],
    ];
    $form['bottom']['searchgroup'] = [
      '#type' => 'fieldset',
      '#id' => 'galleriesSearchGroup',
    ];
    if($type == 'news'){

      $form['bottom']['searchgroup']['newsSearch'] = [
        '#type' => 'textfield',
        '#title' => t('Search'),
        '#attributes' => [
          'alt' => t('Type news title you are looking for'),
        ],
        '#ajax' => [
          'wrapper' => 'filter-target',
          'keypress' => TRUE,
          'callback' => '::filterResults',
          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];

      $form['bottom']['searchgroup']['searchbutton'] = [

        '#type' => 'submit',
        '#title' => t('Search'),
        '#value' => t('Submit'),
        '#submit'=>['filterResults'],
        '#ajax' => [
          'callback' => '::filterResults',
          'wrapper' => 'filter-target',
          'disable-refocus' => true,
          'keypress'=>TRUE,
        ],

      ];
      $form['bottom']['searchgroup']['newsSearchMobile'] = [
        '#type' => 'textfield',
        '#title' => t('Search'),
        '#attributes' => [
          'alt' => t('Type news title you are looking for'),
        ],
        '#ajax' => [
          'wrapper' => 'filter-target',
          'keypress' => TRUE,
          'callback' => '::filterResults',
          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];
    }
    elseif($type == 'calendar'){

      $form['bottom']['searchgroup']['calendarSearch'] = [
        '#type' => 'textfield',
        '#tree'=>true,
        '#title' => t('Search'),
        '#attributes' => [
          'alt' => t('Type calendar title you are looking for'),
        ],
        '#ajax' => [
          'wrapper' => 'filter-target',
          'keypress' => TRUE,
          'callback' => '::filterResults',
          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];
      $form['bottom']['searchgroup']['calendarSearchMobile'] = [
        '#type' => 'textfield',
        '#tree'=>true,
        '#title' => t('Search'),
        '#attributes' => [
          'alt' => t('Type calendar title you are looking for'),
        ],
        '#ajax' => [
          'wrapper' => 'filter-target',
          'keypress' => TRUE,
          'callback' => '::filterResults',
          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];

      $form['bottom']['searchgroup']['searchbutton'] = [

        '#type' => 'submit',
        '#title' => t('Search'),
        '#value' => t('Submit'),
        '#submit'=>['filterResults'],
        '#ajax' => [
          'callback' => '::filterResults',
          'wrapper' => 'filter-target',
          'disable-refocus' => true,
          'keypress'=>TRUE,
        ],

      ];
      if($type == 'calendar'){
//        $form['bottom']['searchgroup']['gallerySearch']['#autocomplete_route_name'] = 'harno_pages.calendar.autocomplete';
//        $form['bottom']['searchgroup']['gallerySearch']['#autocomplete_route_parameters'] = ['type' => $calendar_type];
        $form['bottom']['searchgroup']['gallerySearch']['#attributes']['size'] = 20;
      }

    }
    else {
      $form['bottom']['searchgroup']['gallerySearch'] = [
        '#type' => 'textfield',
        '#tree'=>true,
        '#title' => t('Search'),
        '#attributes' => [
          'alt' => t('Type gallery title you are looking for'),
        ],
        '#ajax' => [
          'wrapper' => 'filter-target',
          'keypress' => TRUE,
          'callback' => '::filterResults',
          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];

      $form['bottom']['searchgroup']['searchbutton'] = [

        '#type' => 'submit',
        '#title' => t('Search'),
        '#value' => t('Submit'),
        '#submit'=>['filterResults'],
        '#ajax' => [
          'callback' => '::filterResults',
          'wrapper' => 'filter-target',
          'disable-refocus' => true,
          'keypress'=>TRUE,
        ],

      ];
      if($type == 'calendar'){
//        $form['bottom']['searchgroup']['gallerySearch']['#autocomplete_route_name'] = 'harno_pages.calendar.autocomplete';
//        $form['bottom']['searchgroup']['gallerySearch']['#autocomplete_route_parameters'] = ['type' => $calendar_type];
        $form['bottom']['searchgroup']['gallerySearch']['#attributes']['size'] = 20;
      }
    }
    $form['bottom']['searchgroup']['searchbutton'] = [
      '#attributes' => [
        'style' => 'display:none;',
      ],
      '#type' => 'button',
      '#title' => t('Search'),
      '#value' => t('Submit'),
      '#ajax' => [
        'callback' => '::filterResults',
        'wrapper' => 'filter-target',
        'disable-refocus' => true,
        'keypress'=>TRUE,
      ],

    ];
    $form['bottom']['searchgroup']['searchbuttonmobile'] = [
      '#attributes' => [
        'style' => 'display:none;',
      ],
      '#type' => 'button',
      '#title' => t('Search'),
      '#value' => t('Submit'),
      '#ajax' => [
        'callback' => '::filterResults',
        'wrapper' => 'filter-target',
        'disable-refocus' => true,
        'keypress'=>TRUE,
      ],
    ];
    $form['bottom']['ready'] = [
      '#type' => 'submit',
      '#title' => t('Ready'),
      '#value' => t('Ready'),
      '#ajax' => [
        'callback' => '::filterResults',
        'wrapper' => 'filter-target',
        'disable-refocus' => true,
        'keypress'=>TRUE,
      ],
    ];
    if (!empty($_REQUEST)) {
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
      if (!empty($_REQUEST['years'])) {
        $form['#storage']['active-years'] = $_REQUEST['years'];
        if (is_array($_REQUEST['years'])) {
           $form['top_filters']['years']['#default_value'] = $_REQUEST['years'];
        }
        else {
           $form['top_filters']['years']['#default_value'] = explode('|', $_REQUEST['years']);
        }
      }
      if (!empty($_REQUEST['event_type'])){
        $form['#storage']['event_type'] = $_REQUEST['event_type'];
        foreach ($form['#storage']['event_type'] as $event_tid){
          if($event_tid != 0) {
            $term_name = Term::load($event_tid);
            $form['#storage']['event_type'][$event_tid] = $term_name->label();
          }
        }
      }
      if (!empty($_REQUEST['days'])){
        $_REQUEST['says'] = strip_tags($_REQUEST['days']);
        if ($_REQUEST['days']!=='all') {
          $form['#storage']['days'] = $_REQUEST['days'];
        }
      }
      if (!empty($_REQUEST['date_start'])) {
        $form['#storage']['dates']['start'] = $_REQUEST['date_start'];
        $form['bottom']['date_start']['#default_value'] = $_REQUEST['date_start'];
      }
      if (!empty($_REQUEST['date_end'])) {
        $form['#storage']['dates']['end'] = $_REQUEST['date_end'];
        $form['bottom']['date_end']['#default_value'] = $_REQUEST['date_end'];
      }
      if (!empty($_REQUEST['date_start_mobile'])) {
        $form['#storage']['dates']['start'] = $_REQUEST['date_start_mobile'];
        $form['bottom']['date_start']['#default_value'] = $_REQUEST['date_start_mobile'];
      }
      if (!empty($_REQUEST['date_end'])) {
        $form['#storage']['dates']['end'] = $_REQUEST['date_end'];
        $form['bottom']['date_end']['#default_value'] = $_REQUEST['date_end'];
      }
      if (!empty($_REQUEST['date_end_mobile'])) {
        $form['#storage']['dates']['end'] = $_REQUEST['date_end_mobile'];
        $form['bottom']['date_end']['#default_value'] = $_REQUEST['date_end_mobile'];
      }
      if (!empty($_REQUEST['gallerySearch'])) {
        $form['bottom']['searchgroup']['gallerySearch']['#default_value'] = $_REQUEST['gallerySearch'];
      }
      if (!empty($_REQUEST['gallerySearchMobile'])) {
        $form['bottom']['searchgroup']['gallerySearch']['#default_value'] = $_REQUEST['gallerySearchMobile'];
      }
      if (!empty($_REQUEST['calendarSearch'])) {
        $form['bottom']['searchgroup']['calendarSearch']['#default_value'] = $_REQUEST['calendarSearch'];
      }
      if (!empty($_REQUEST['gallerySearchMobile'])) {
        $form['bottom']['searchgroup']['calendarSearch']['#default_value'] = $_REQUEST['calendarSearchMobile'];
      }
      if (!empty($_REQUEST['newsSearch'])) {
        $form['bottom']['searchgroup']['newsSearch']['#default_value'] = $_REQUEST['newsSearch'];
      }
      if (!empty($_REQUEST['newsSearchMobile'])) {
        $form['bottom']['searchgroup']['newsSearchMobile']['#default_value'] = $_REQUEST['newsSearchMobile'];
      }
      if (!empty($_REQUEST['article_type'])) {
        $art_def = '';
        if ($_REQUEST['article_type']!='all') {
          $art_def = $_REQUEST['article_type'];
          $form['bottom']['article_type']['#default_value'] = $art_def;
        }
      }
    }

    if ($type == 'news'){
      $form['#theme_wrappers'] = ['form-news'];
    }
    elseif($type == 'calendar'){
      $form['#theme_wrappers'] = ['form-calendar'];
    }
    else {
      $form['#theme_wrappers'] = ['form-galleries'];
    }
//    dump($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $article_id = EntityAutocomplete::extractEntityIdFromAutocompleteInput($form_state->getValue('article'));
    $form_state->setRebuild(TRUE);
  }
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state); // TODO: Change the autogenerated stub
  }

  /**
   *
   */
  public function filterResults(array &$form, FormStateInterface $form_state) {
    unset($form['pager']);
    if(!empty($_GET)){
      if (!empty($_GET['_wrapper_format'])){
        unset($_GET['page']);
//        unset($_GET['ajax_form']);
//        unset($_GET['_wrapper_format']);
        unset($_REQUEST['page']);
        unset($GLOBALS['_REQUEST']['page']);
        unset($GLOBALS['_REQUEST']['page']);
//        unset($GLOBALS['request']->query->parameters["page"]);
        $existingQuery = \Drupal::service('request_stack')->getCurrentRequest()->query->all();
        $existingQuery = \Drupal::service('request_stack')->getCurrentRequest()->query->remove('page');

        $existingQuery = \Drupal::service('request_stack')->getCurrentRequest()->query->get('page');



      }
    }

    if (!empty($form['#storage']))
    {
      if (!empty($form['#storage']['type'])){
        $type = $form['#storage']['type'];
      }
      if(!empty($form['#storage']['calendar_type'])){
        $calendar_type = $form['#storage']['calendar_type'];
      }
    }
    if(!isset($type)){
      $type = 'galleries';
    }
    if($type == 'news'){
      $galleries = new NewsController();
      $galleries = $galleries->getNews();
    }
    elseif ($type=='galleries') {
      $galleries = new GalleriesController();
      $galleries = $galleries->getGalleries();
    }
    elseif ($type=='calendar') {
      $galleries = new CalendarController();
      $galleries = $galleries->getEvents($calendar_type,false , $form_state);
    }
    $parameters = [];
    $form_values = $form_state->getUserInput();
    if (!empty($form_values)) {
      if (!empty($form_values['years'])) {
        $have_years = false;
        foreach ($form_values['years'] as $year) {
          if (!empty($year)) {
            $have_years = true;
              $parameters['years'][$year] = $year;
          }
        }
        if (!$have_years){
          $parameters['years'] = null;
        }
      }
      if (!empty($form_values['event_type'])) {
        $have_years = false;
        foreach ($form_values['event_type'] as $year) {
          if (!empty($year)) {
            $have_years = true;
              $parameters['event_type'][$year] = $year;

          }
        }
        if (!$have_years){
          $parameters['event_type'] = null;
        }
      }

      if ( isset($form_values['article_type']) && is_array($form_values['article_type'])) {
        foreach ($form_values['article_type'] as $art_type) {
          if (!empty($art_type)) {
              $parameters['article_type'][$art_type] = $art_type;

          }
        }
      }
      if (!empty($form_values['article_type_mobile'])&& is_array($form_values['article_type_mobile'])) {
        foreach ($form_values['article_type_mobile'] as $art_type) {
          if (!empty($art_type)) {
              $parameters['article_type_mobile'][$art_type] = $art_type;
            }
          }
        }

      if (!empty($form_values['days']) && is_array($form_values['days'])) {
        foreach ($form_values['days'] as $art_type) {
          if (!empty($art_type)) {
              $parameters['days'][$art_type] = $art_type;
          }
        }
      }
      if (!empty($form_values['days_mobile'])  && is_array($form_values['days_mobile'])) {
        foreach ($form_values['days_mobile'] as $art_type) {
          if (!empty($art_type)) {
              $parameters['days_mobile'][$art_type] = $art_type;
          }
        }
      }
      if(!empty($_REQUEST['days'])){

        $_REQUEST['says'] = strip_tags($_REQUEST['days']);
        $parameters['days_mobile'] = $_REQUEST['days'];
      }
      if (isset($form_values['date_start'])) {
        $parameters['date_start'] = $_REQUEST['date_start'];
      }
      if (isset($form_values['date_end'])) {
        $parameters['date_end'] = $_REQUEST['date_end'];
      }
      if (isset($form_values['date_start'])) {
        $parameters['date_start_mobile'] = $_REQUEST['date_start'];
      }
      if (isset($form_values['date_end'])) {
        $parameters['date_end'] = $_REQUEST['date_end'];
      }
      if (isset($form_values['date_end'])) {
        $parameters['date_end_mobile'] = $_REQUEST['date_end'];
      }
      if (isset($form_values['gallerySearch'])) {
        $parameters['gallerySearch'] = $_REQUEST['gallerySearch'];
      }
      if (isset($form_values['gallerySearchMobile'])) {
        $parameters['gallerySearchMobile'] = $_REQUEST['gallerySearchMobile'];
      }
      if (isset($form_values['calendarSearch'])) {
        $parameters['calendarSearch'] = $_REQUEST['calendarSearch'];
      }
      if (isset($form_values['calendarSearchMobile'])) {
        $parameters['calendarSearchMobile'] = $_REQUEST['calendarSearchMobile'];
      }
      if (isset($form_values['newsSearch'])) {
        $parameters['newsSearch'] = $_REQUEST['newsSearch'];
      }
      if (isset($form_values['newsSearchMobile'])) {
        $parameters['newsSearchMobile'] = $_REQUEST['newsSearchMobile'];
      }
      if(!empty($_GET)){
        if (!empty($_GET['_wrapper_format'])){
//          $parameters['page']=0;
        }
      }
    }
    if(!empty($filter_values = $form_state->getValues())){
      $filters = [];
      if(!empty($filter_values['years'])){
        $filters['#theme'] = 'active-filters';
        $filters['#content']['years'] = $filter_values['years'];

      }
      if(!empty($filter_values['years-mobile'])){
        $filters['#theme'] = 'active-filters';
        $filters['#content']['years'] = $filter_values['years-mobile'];

      }
      if(!empty($filter_values['event_type'])){
        $filters['#theme'] = 'active-filters';
        $filters['#content']['event_type'] = $filter_values['event_type'];
      }
      if (!empty($filter_values['date_start']||!empty($filter_values['date_end']))){
        if(!empty($filter_values['date_start'])){

          $filters['#theme'] = 'active-filters';
          $filters['dates']['start'] = $filter_values['date_start'];
        }
        if(!empty($filter_values['date_end'])){

          $filters['#theme'] = 'active-filters';
          $filters['dates']['end'] = $filter_values['date_end'];
        }
      }
      if ((isset($filter_values['date_start_mobile']) && !empty($filter_values['date_start_mobile']))||(isset($filter_values['date_end_mobile']) && !empty($filter_values['date_end_mobile']))){
        if(!empty($filter_values['date_start_mobile'])){

          $filters['#theme'] = 'active-filters';
          $filters['dates']['start'] = $filter_values['date_start_mobile'];
        }
        if(!empty($filter_values['date_end_mobile'])){

          $filters['#theme'] = 'active-filters';
          $filters['dates']['end'] = $filter_values['date_end_mobile'];
        }
      }
      if(!empty($filter_values['positions'])){
        $filters['#theme'] = 'active-filters';
        $filters['#content']['positions'] = $filter_values['positions'];
      }
    }
    $build = [];
    $build['#theme'] = $type.'-response';
    $build['#content'] = $galleries;
    $build['#pager'] = [
      '#type' => 'pager',
      '#parameters' => $parameters,
      '#quantity' => 5
    ];
    $response = new AjaxResponse();
    if($type == 'calendar'){
      $build['#attached']['library'][] = 'harno_pages/accordion';
      $response->addCommand(new InvokeCommand('.accordion--events', 'accordion'));
    }

    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
    $tag_name= $localconf->get('news_our_story');
    $tag_name = $tag_name['name'];

    $default_logo = \Drupal::service('config.factory')->get('harno_settings.settings')->get('general');
    $default_logo = $default_logo['logo'];
    $build['#default_logo'] = $default_logo;
    $build['#info']['tag_name']  = $tag_name;
//    $response['#attached']['library'][] = 'harno_pages/js/urlparameters.js';
    if(!empty($filter_values['event_type'])){
      $filters['#theme'] = 'active-filters';
      foreach ($filter_values['event_type'] as $value){
        if($value != 0) {
          $term_name = Term::load($value);
          $filters['#content']['event_type'][$value] = $term_name->label();
        }
      }
    }
    if ($type == 'calendar'){
      $filters['#content']['type'] = 'calendar';
    }
    $response->addCommand(new ReplaceCommand('#mobile-active-filters', $filters));
    $response->addCommand(new ReplaceCommand('#filter-target',$build));
    $triggering_element = $form_state->getTriggeringElement();
    if (strpos($triggering_element['#id'],'edit-ready')!==false){
      $response->addAttachments(['library'=>'harno_pages/filter_focus']);
//      $response->addAttachments(['library'=>'harno_pages/harno_pages']);
      $response->addCommand(new InvokeCommand('.modal-open','closeModal'));
//      $response->addCommand(new InvokeCommand('form','formFilter'));
    }
    if($type == 'calendar'){
      $html = '
        <div id="filter-target-dates">
          <form class="filters-form">
            <div class="title-block--calendar">';
      if($form_state->getTriggeringElement()['#name'] == 'days'){
        if($form_state->getTriggeringElement()['#value'] == 'today'){
          $html.='<h2>'.t('Today').', '.date('d.', strtotime('today')) . t(date('F', strtotime('today')),array(),array('context' => 'Long month name')) .'</h2>';
        }
        elseif($form_state->getTriggeringElement()['#value'] == 'week'){
          $monday = date('d.', strtotime('Monday this week'));
          $sunday = date('d.', strtotime('Sunday this week'));
          $html.='<h2>'.$monday. t(date('F', strtotime('Monday this week')),array(),array('context' => 'Long month name')) .' - '.$sunday . t(date('F', strtotime('Sunday this week')),array(),array('context' => 'Long month name')) .'</h2>';
        }
        elseif($form_state->getTriggeringElement()['#value'] == 'month'){
          $firstDay = date('d.', strtotime('First day of this month'));
          $lastDay = date('d.', strtotime('Last day of this month'));
          $html.='<h2>'.$firstDay. t(date('F', strtotime('First day of this month')),array(),array('context' => 'Long month name')) .' - '.$lastDay . t(date('F', strtotime('Last day of this month')),array(),array('context' => 'Long month name')) .'</h2>';

        }
      }
      else {
//        dump($filter_values);
        if(empty($filter_values['date_end'])&&!empty($filter_values['date_start'])){
          $html .= '<h2>' . date('d',strtotime($filter_values['date_start'])) .'.'.t(date('F',strtotime($filter_values['date_start'])),[],['context'=>'Long month name']).' ' .date('Y',strtotime($filter_values['date_start'])) . '</h2>';
        }
        elseif(empty($filter_values['date_start']) and !empty($filter_values['date_end'])){
          $html .= '<h2>' . date('d',strtotime($filter_values['date_end'])) .'.'.t(date('F',strtotime($filter_values['date_end'])),[],['context'=>'Long month name']).' ' .date('Y',strtotime($filter_values['date_end'])) . '</h2>';
        }
        elseif (empty($filter_values['date_start']) && empty($form_values['date_end'])){
          $html.='<h2>'.t('Today').', '.date('d.', strtotime('today')) . t(date('F', strtotime('today')),array(),array('context' => 'Long month name')) .'</h2>';
        }
        else{
          $html .= '<h2>' . date('d',strtotime($filter_values['date_start'])) .'.'.t(date('F',strtotime($filter_values['date_start'])),[],['context'=>'Long month name']).' ' .date('Y',strtotime($filter_values['date_start'])). ' - ' .  date('d',strtotime($filter_values['date_end'])) .'.'.t(date('F',strtotime($filter_values['date_end'])),[],['context'=>'Long month name']).' ' .date('Y',strtotime($filter_values['date_end'])) . '</h2>';
        }
      }
      $html.='
            </div>
          </form>
        </div>';
      $response->addCommand(new ReplaceCommand('#filter-target-dates',$html));
    }
//      $response->addCommand(new InvokeCommand(NULL, 'filterFocus', [$form_state->getTriggeringElement()]));

    //    $response->addCommand(new UpdateSelectionCommand());

    return $response;
  }
  public function getArticleTypes(){
    $entityManager = \Drupal::service('entity_field.manager');
    $fields = $entityManager->getFieldStorageDefinitions('node', 'article');
    $outoptions = [];
    $outoptions['all']= t('All');
    $options = options_allowed_values($fields['field_article_type']);

    $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
    $tag_name= $localconf->get('news_our_story');
    $tag_name = $tag_name['name'];
    foreach ($options as $key => $option){
      if ($key == 1) {
        $option = t('News',[],['context'=>'news page']);
      }
      $outoptions[strval($key)] = $option;
      if ($key==2){
        $outoptions[strval($key)] = $tag_name;
      }
    }
    return $outoptions;
  }

}
