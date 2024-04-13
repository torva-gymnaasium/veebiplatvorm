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
use Drupal\harno_pages\Controller\ContactsController;
use Drupal\harno_pages\Controller\GalleriesController;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\harno_pages\Controller\NewsController;
use Drupal\media_library\Ajax\UpdateSelectionCommand;
use Drupal\taxonomy\Entity\Term;

/**
 *
 */
class MobileFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // TODO: Implement getFormId() method.
    return 'gallery_filter_form_mobile';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $academic_years = NULL,$type=NULL,$calendar_type=null) {

    $route_name = \Drupal::routeMatch()->getRouteName();
    $this_class = new \Drupal\harno_pages\Form\MobileFilterForm;

    if($route_name == 'harno_pages.contacts_page'){
      $academic_years = new \Drupal\harno_pages\Controller\ContactsController;
      $academic_years = $academic_years->getFilters();
      $type = 'contacts';
      $form['#storage']['modal_title'] = 'Contacts filter';
      if (!empty($type)){
        $form['#storage']['type'] = $type;
      }
      if(!empty($academic_years)){
        $form['#attributes']['data-plugin'] = 'filters';
        $form['#attributes']['role'] = 'filter';
        $form['#theme_wrappers'] = ['form-news-mobile'];
//      dpm($form);
        if (!empty($_REQUEST)) {
          if (!empty($_REQUEST['positions'])) {
            $form['#storage']['positions'] = $_REQUEST['positions'];
          }
          if (!empty($_REQUEST['positions_mobile'])) {
            $form['#storage']['positions'] = $_REQUEST['positions_mobile'];
          }
          if (!empty($_REQUEST['departments'])) {
            $form['#storage']['departments'] = $_REQUEST['departments'];
          }
          if (!empty($_REQUEST['departments_mobile'])) {
            $form['#storage']['departments'] = $_REQUEST['departments_mobile'];
          }
        }
        $form['top'] = [
          '#type' => 'fieldset',
          '#id' => 'contacts-topFilter-mobile',
        ];
        $form['top']['positions_mobile'] = [
          '#title' => t('Choose position'),
          '#id' => 'worker-position-mobile',
          '#type' => 'select',
          '#attributes' => [
            'data-disable-refocus' => true,
          ],
          '#placeholder' => ' ',
          '#ajax' => [
            'wrapper' => 'filter-target',
            'event' => 'change',
            'callback' => [$this_class,'filterResultsMobile'],
            'progress' => [
              'type' => 'throbber',
              'message' => $this->t('Verifying entry...'),
            ],
          ],
          '#options' => $academic_years['positions'],
        ];
        $form['top']['departments_mobile'] = [
          '#title' => t('Choose department'),
          '#id' => 'worker-department-mobile',
          '#type' => 'select',
          '#attributes' => [
            'data-disable-refocus' => true,
          ],
          '#placeholder' => ' ',
          '#ajax' => [
            'wrapper' => 'filter-target',
            'event' => 'change',
            'callback' => [$this_class,'filterResultsMobile'],
            'progress' => [
              'type' => 'throbber',
              'message' => $this->t('Verifying entry...'),
            ],
          ],
          '#options' => $academic_years['departments'],
        ];
        $form['top']['contactsSearch_mobile'] = [
          '#type' => 'textfield',
          '#title' => t('Search contacts'),
          '#attributes' => [
            'alt' => t('Type contact name you are looking for'),
          ],
//          '#autocomplete_route_name' => 'harno_pages.contacts.autocomplete',
          '#ajax' => [
            'wrapper' => 'filter-target',
            'keypress' => TRUE,
            'callback' => [$this_class,'filterResultsMobile'],
            'event' => 'finishedinput',
            'disable-refocus' => TRUE,
          ],
        ];
        $form['top']['searchbutton_mobile'] = [
          '#attributes' => [
            'style' => 'display:none;',
          ],
          '#type' => 'button',
          '#title' => t('Search contacts'),
          '#value' => t('Submit'),
          '#ajax' => [
            'callback' => [$this_class,'filterResultsMobile'],
            'wrapper' => 'filter-target',
            'disable-refocus' => true,
            'keypress'=>TRUE,
          ],
        ];
        $form['top']['ready'] = [
          '#type' => 'submit',
          '#title' => t('Ready'),
          '#value' => t('Ready'),
          '#ajax' => [
            'callback' => [$this_class,'filterResultsMobile'],
            'wrapper' => 'filter-target',
            'disable-refocus' => true,
            'keypress'=>TRUE,
          ],
        ];
        $form['filter'] = [
          '#type' => 'fieldset',
          '#id' => 'contacts-bottomFilter',
        ];
//        $form['#attached']['library'][] = 'harno_pages/accordion';
      }
      if (!empty($_REQUEST)) {
        if (!empty($_REQUEST['positions'])) {
          $form['top']['positions_mobile']['#default_value'] = $_REQUEST['positions'];
        }
        if (!empty($_REQUEST['departments'])) {
          $form['top']['departments_mobile']['#default_value'] = $_REQUEST['departments'];
        }
      }

      return $form;
    }

    if ($route_name == 'harno_pages.news_page'){
      $academic_years = new \Drupal\harno_pages\Controller\NewsController;
      $academic_years = $academic_years->getAcademicYears();
      $type = 'news';
      $form['#storage']['modal_title'] = 'News filter';
    }
    if ($route_name == 'harno_pages.galleries_page'){
      $academic_years = new \Drupal\harno_pages\Controller\GalleriesController();
      $academic_years = $academic_years->getAcademicYears();
      $form['#storage']['modal_title'] = 'Galleries filter';
    }
    if($route_name == 'harno_pages.calendar'){
      $form['#storage']['modal_title'] = 'Calendar filter';
      $type = 'calendar';
      $calendar_type = 2;
      $academic_years = new \Drupal\harno_pages\Controller\CalendarController();
      $academic_years = $academic_years->getEventType($calendar_type);
    }
    if($route_name == 'harno_pages.training'){
      $form['#storage']['modal_title'] = 'Training filter';
      $type = 'calendar';
      $calendar_type = 1;
      $academic_years = new \Drupal\harno_pages\Controller\CalendarController();
      $academic_years = $academic_years->getEventType($calendar_type);
    }
//      'harno_pages.contacts_page'

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

        $form['top_filters']['article_type_mobile'] = [
          '#type' => 'radios',
          '#id' => 'article_type_mobile',
          '#attributes' => [
            'checkbox-type' => 'collect'
          ],
          '#ajax' => [
            'wrapper' => 'filter-target',
            'event' => 'change',
            'callback' => [$this_class,'filterResultsMobile'],
          ],
          '#default_value' => 'all',
          '#options' => $articleoptions,
        ];
        $form['top_filters']['years-mobile'] = [
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
            'callback' => [$this_class,'filterResultsMobile'],
          ],
          '#my-id' => 'news-years',
          '#options' => $academic_years,
        ];
      }
      elseif ($type == 'calendar') {
        $form['top_filters'] = [
          '#type' => 'fieldset',
          '#id' => 'event-topFilter',
        ];
        $options = [
          'today' => $this->t('Today'),
          'all' => $this->t('All'),
          'week' => $this->t('Week'),
          'month' => $this->t('Month'),
        ];
        $form['top_filters']['days_mobile'] = [
          '#type' => 'radios',
          '#options' => $options,
          '#default_value' => 'month',
          '#ajax' => [
            'wrapper' => 'filter-target',
            'event' => 'change',
            'keypress' => TRUE,
            'callback' => [$this_class,'filterResultsMobile'],
            'disable-refocus' => TRUE,
          ],
        ];
        $title = t('Choose training type');
        if (\Drupal::routeMatch()->getRouteName() == 'harno_pages.calendar') {
          $title = t('Choose event type');
        }
        $form['top_filters']['event_type_mobile'] = [
          '#title' => $title,
          // '#attributes' => ['name' => 'years'],
          '#id' => 'event_type',
          '#type' => 'checkboxes',
          '#attributes' => [
            'checkbox-type' => 'collect'
          ],
          '#ajax' => [
            'wrapper' => 'filter-target',
            'event' => 'change',
            'callback' => [$this_class,'filterResultsMobile'],
          ],
          '#options' => $academic_years,
        ];
      }
      else {
        $form['years-mobile'] = [
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
            'callback' => [$this_class,'filterResultsMobile'],
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
    }
    $form['bottom']['date_start_mobile'] = [
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
        'callback' => [$this_class,'filterResultsMobile'],
        'disable-refocus' => TRUE,
      ],
    ];
    $form['bottom']['date_end_mobile'] = [
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
        'callback' => [$this_class,'filterResultsMobile'],
        'disable-refocus' => TRUE,
      ],
    ];
    $form['bottom']['searchgroup'] = [
      '#type' => 'fieldset',
      '#id' => 'galleriesSearchGroupMobile',
    ];
    if($type == 'news'){


      $form['#storage']['other_label'] = t('Older news');

      $form['bottom']['searchgroup']['searchbutton'] = [

        '#type' => 'submit',
        '#title' => t('Search'),
        '#value' => t('Submit'),
        '#submit'=>['filterResultsMobile'],
        '#ajax' => [
          'callback' => [$this_class,'filterResultsMobile'],
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
          'callback' => [$this_class,'filterResultsMobile'],
          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];
    }
    elseif($type == 'calendar'){


      $form['bottom']['searchgroup']['searchbutton'] = [

        '#type' => 'submit',
        '#title' => t('Search'),
        '#value' => t('Submit'),
        '#submit'=>['filterResultsMobile'],
        '#ajax' => [
          'callback' => [$this_class,'filterResultsMobile'],
          'wrapper' => 'filter-target',
          'disable-refocus' => true,
          'keypress'=>TRUE,
        ],

      ];
      if($type == 'calendar'){
//        $form['bottom']['searchgroup']['gallerySearch']['#autocomplete_route_name'] = 'harno_pages.calendar.autocomplete';
//        $form['bottom']['searchgroup']['gallerySearch']['#autocomplete_route_parameters'] = ['type' => $calendar_type];
        $form['bottom']['searchgroup']['calendarSearch']['#attributes']['size'] = 20;
      }
      $form['bottom']['searchgroup']['calendarSearchMobile'] = [
        '#type' => 'textfield',
        '#title' => t('Search'),
        '#attributes' => [
          'alt' => t('Type calendar title you are looking for'),
        ],
        '#ajax' => [
          'wrapper' => 'filter-target',
          'keypress' => TRUE,
          'callback' => [$this_class,'filterResultsMobile'],
          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];
    }
    else {

      $form['#storage']['other_label'] = t('Older galleries');
      $form['bottom']['searchgroup']['searchbutton'] = [

        '#type' => 'submit',
        '#title' => t('Search'),
        '#value' => t('Submit'),
        '#submit'=>['filterResultsMobile'],
        '#ajax' => [
          'callback' => [$this_class,'filterResultsMobile'],
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
      $form['bottom']['searchgroup']['gallerySearchMobile'] = [
        '#type' => 'textfield',
        '#title' => t('Search'),
        '#attributes' => [
          'alt' => t('Type gallery title you are looking for'),
        ],
        '#ajax' => [
          'wrapper' => 'filter-target',
          'keypress' => TRUE,
          'callback' => [$this_class,'filterResultsMobile'],
          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];
    }
    $form['bottom']['searchgroup']['searchbutton'] = [
      '#attributes' => [
        'style' => 'display:none;',
      ],
      '#type' => 'button',
      '#title' => t('Search'),
      '#value' => t('Submit'),
      '#ajax' => [
        'callback' => [$this_class,'filterResultsMobile'],
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
        'callback' => [$this_class,'filterResultsMobile'],
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
        'callback' => [$this_class,'filterResultsMobile'],
        'wrapper' => 'filter-target',
        'disable-refocus' => true,
        'keypress'=>TRUE,
      ],
    ];

    if (!empty($_REQUEST)) {
      if (!empty($_REQUEST['years-mobile'])) {
        $form['#storage']['active-years'] = $_REQUEST['years-mobile'];
        if (is_array($_REQUEST['years-mobile'])) {
           $form['top_filters']['years_mobile']['#default_value'] = $_REQUEST['years-mobile'];
        }
        else {
           $form['top_filters']['years_mobile']['#default_value'] = explode('|', $_REQUEST['years-mobile']);
        }
      }
      if (!empty($_REQUEST['date_start_mobile'])) {
        $form['#storage']['dates']['start'] = $_REQUEST['date_start_mobile'];
        $form['bottom']['date_start_mobile']['#default_value'] = $_REQUEST['date_start_mobile'];
      }
      if (!empty($_REQUEST['date_end_mobile'])) {
        $form['#storage']['dates']['end'] = $_REQUEST['date_end_mobile'];
        $form['bottom']['date_end_mobile']['#default_value'] = $_REQUEST['date_end_mobile'];
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
      if (!empty($_REQUEST['calendarSearchMobile'])) {
        $form['bottom']['searchgroup']['calendarSearch']['#default_value'] = $_REQUEST['calendarSearchMobile'];
      }
      if (!empty($_REQUEST['newsSearch'])) {
        $form['bottom']['searchgroup']['newsSearch']['#default_value'] = $_REQUEST['newsSearch'];
      }
      if (!empty($_REQUEST['newsSearchMobile'])) {
        $form['bottom']['searchgroup']['newsSearchMobile']['#default_value'] = $_REQUEST['newsSearchMobile'];
      }
      if (!empty($_REQUEST['article_type_mobile'])) {
        $art_def = '';
        if ($_REQUEST['article_type_mobile']!='all') {
          $art_def = $_REQUEST['article_type_mobile'];
          $form['bottom']['article_type_mobile']['#default_value'] = $art_def;
        }
      }
    }
      $form['#theme_wrappers'] = ['form-news-mobile'];

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
  public function filterResultsMobile(array &$form, FormStateInterface $form_state) {
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
      $calendar = new CalendarController();
      $galleries = $calendar->getEvents($calendar_type,false , $form_state);
    }
    elseif($type == 'contacts'){
      $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
      if (!empty($form['#storage'])) {
        if (!empty($form['#storage']['type'])){
          $type = $form['#storage']['type'];
        }
      }
      $contacts = new ContactsController();
      $contacts = $contacts->getContacts();

      $parameters = [];
      $form_values = $form_state->getUserInput();
      if (!empty($form_values)) {
        if (isset($form_values['contactsSearch'])) {
          $parameters['contactsSearch'] = $_REQUEST['contactsSearch'];
        }
        if (isset($form_values['contactsSearchMobile'])) {
          $parameters['contactsSearchMobile'] = $_REQUEST['contactsSearchMobile'];
        }
        $filter_values = $form_state->getValues();
        if (isset($form_values['positions'])) {
          $parameters['positions'] = $_REQUEST['positions'];
          $filters['#content']['positions'] = $filter_values['positions'];
        }
        if (isset($form_values['departments'])) {
          $parameters['departments'] = $_REQUEST['departments'];
          $filters['#content']['departments'] = $filter_values['departments'];
        }
      }
      $filters = [];
      if(!empty($filter_values = $form_state->getValues())){
        if(!empty($filter_values['positions'])){
          $filters['#theme'] = 'active-filters';
          $filters['#content']['positions'] = $filter_values['positions'];
        }
        if(!empty($filter_values['positions_mobile'])){
          $filters['#theme'] = 'active-filters';
          $filters['#content']['positions'] = $filter_values['positions_mobile'];
        }
        if(!empty($filter_values['departments'])){
          $filters['#theme'] = 'active-filters';
          $filters['#content']['departments'] = $filter_values['departments'];
        }
        if(!empty($filter_values['departments_mobile'])){
          $filters['#theme'] = 'active-filters';
          $filters['#content']['departments'] = $filter_values['departments_mobile'];
        }
      }
      if ($type == 'calendar'){
        $filters['#content']['type'] = 'calendar';
      }
      $build = [];
      $build['#theme'] = $type.'-response';
      $build['#content'] = $contacts;
      $build['#language'] = $language;
      $build['#pager'] = [
        '#type' => 'pager',
        '#parameters' => $parameters,
      ];
//      $build['#attached']['library'][] = 'harno_pages/accordion';
      $response = new AjaxResponse();
      $response->addCommand(new ReplaceCommand('#mobile-active-filters', $filters));
      $response->addCommand(new ReplaceCommand('#filter-target',$build));
      //    $response->addCommand(new ReplaceCommand('#filter-update', $form));
      //      $response->addCommand(new InvokeCommand('.accordion--contacts', 'accordion'));
//      $response->addCommand(new InvokeCommand('.mobile-filters', 'filtersModal'));
      $response->addCommand(new InvokeCommand('.form-item', 'movingLabel'));
      $triggering_element = $form_state->getTriggeringElement();
      if (strpos($triggering_element['#id'],'edit-ready')!==false){
        $response->addAttachments(['library'=>'harno_pages/filter_focus']);
        //      $response->addAttachments(['library'=>'harno_pages/harno_pages']);
        $response->addCommand(new InvokeCommand('.modal-open','closeModal'));
        //      $response->addCommand(new InvokeCommand('form','formFilter'));
      }
      for($x = 0; $x <= $contacts['overall_total']; $x++){
//        $response->addCommand(new InvokeCommand('#contact-modal-'.$x, 'modal'));
      }

      return $response;
    }

    $parameters = [];
    $form_values = $form_state->getUserInput();
    if (!empty($form_values)) {
      if (!empty($form_values['years-mobile'])) {
        $have_years = false;
        foreach ($form_values['years-mobile'] as $year) {
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
      if (!empty($form_values['article_type'])) {
        foreach ($form_values['article_type'] as $art_type) {
          if (!empty($art_type)) {
              $parameters['article_type'][$art_type] = $art_type;
          }
        }
      }
      if (!empty($form_values['article_type_mobile'])) {
        foreach ($form_values['article_type_mobile'] as $art_type) {
          if (!empty($art_type)) {
              $parameters['article_type_mobile'][$art_type] = $art_type;
          }
        }
      }

      if (isset($form_values['date_start'])) {
        $parameters['date_start'] = $_REQUEST['date_start'];
      }
      if (isset($form_values['date_end'])) {
        $parameters['date_end'] = $_REQUEST['date_end'];
      }
      if (isset($form_values['calendarSearch'])) {
        $parameters['calendarSearch'] = $_REQUEST['calendarSearch'];
      }
      if (isset($form_values['calendarSearchMobile'])) {
        $parameters['calendarSearchMobile'] = $_REQUEST['calendarSearchMobile'];
      }
      if (isset($form_values['gallerySearch'])) {
        $parameters['gallerySearch'] = $_REQUEST['gallerySearch'];
      }
      if (isset($form_values['gallerySearchMobile'])) {
        $parameters['gallerySearchMobile'] = $_REQUEST['gallerySearchMobile'];
      }
      if (isset($form_values['newsSearch'])) {
        $parameters['newsSearch'] = $_REQUEST['newsSearch'];
      }
      if (isset($form_values['newsSearchMobile'])) {
        $parameters['newsSearchMobile'] = $_REQUEST['newsSearchMobile'];
      }
      if(!empty($_REQUEST['days'])){
        $_REQUEST['days'] = Xss::filter($_REQUEST['days']);
        $parameters['days'] = $_REQUEST['days'];
      }
      if(!empty($_REQUEST['days_mobile'])){
        $_REQUEST['days_mobile'] = Xss::filter($_REQUEST['days_mobile']);
        $parameters['days_mobile'] = $_REQUEST['days_mobile'];
      }
      if(!empty($_GET)){
        if (!empty($_GET['_wrapper_format'])){
//          $parameters['page']=0;
        }
      }
    }
    if(!empty($filter_values = $form_state->getValues())){
      $filters = [];
      if(!empty($filter_values['years-mobile'])){
        $filters['#theme'] = 'active-filters';
        $filters['#content']['years'] = $filter_values['years-mobile'];

      }
      if(!empty($filter_values['event_type_mobile'])){
        $filters['#theme'] = 'active-filters';
        foreach ($filter_values['event_type_mobile'] as $value){
          if($value != 0) {
            $term_name = Term::load($value);
            $filters['#content']['event_type'][$value] = $term_name->label();
          }
        }
      }
      if ( !empty($filter_values['date_start_mobile'] || !empty($filter_values['date_end_mobile']))) {
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
    if (isset($form_values['days_mobile'])){
      $filters['#content']['days'] = $form_values['days_mobile'];
    }
    $build = [];
    $build['#theme'] = $type.'-response';
    if (isset($form_values['article_type_mobile']) && $form_values['article_type_mobile'] ==2 ){

      $localconf = \Drupal::service('config.factory')->get('harno_settings.settings');
      $tag_name= $localconf->get('news_our_story');
      $tag_name = $tag_name['name'];
      $build['#info']['tag_name']  = $tag_name;
    }
    $build['#content'] = $galleries;
    $build['#pager'] = [
      '#type' => 'pager',
      '#parameters' => $parameters,
      '#quantity' => 5
    ];
    $response = new AjaxResponse();
    if ($type == 'calendar'){
      $filters['#content']['type'] = 'calendar';
    }
    if ($type == 'galleries') {
      $filters['#content']['other_label'] = t('Older galleries');
    }
    if ($type == 'news') {
      $filters['#content']['other_label'] = t('Older news');
    }
//    $response['#attached']['library'][] = 'harno_pages/js/urlparameters.js';
    $response->addCommand(new ReplaceCommand('#mobile-active-filters', $filters));
    $response->addCommand(new ReplaceCommand('#filter-target',$build));
    if($type == 'calendar'){
//      $build['#attached']['library'][] = 'harno_pages/accordion';
//      $response->addCommand(new InvokeCommand('.accordion--events', 'accordion'));
    }
    $triggering_element = $form_state->getTriggeringElement();
    if (strpos($triggering_element['#id'],'edit-ready')!==false){
      $response->addAttachments(['library'=>'harno_pages/filter_focus']);
//      $response->addAttachments(['library'=>'harno_pages/harno_pages']);
      $response->addCommand(new InvokeCommand('.modal-open','closeModal'));
//      $response->addCommand(new InvokeCommand('form','formFilter'));
    }
    if ($type == 'calendar') {
      $html = '
        <div id="filter-target-dates">
          <form class="filters-form">
            <div class="title-block--calendar">';
      if ($form_state->getTriggeringElement()['#name'] == 'days') {
        if ($form_state->getTriggeringElement()['#value'] == 'today') {
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
        if (!empty($filter_values['days_mobile']) and $filter_values['days_mobile']!='all'){
          if ($filter_values['days_mobile'] == 'today') {
            $html.='<h2>'.t('Today').', '.date('d.', strtotime('today')) . t(date('F', strtotime('today')),array(),array('context' => 'Long month name')) .'</h2>';
          }
          elseif($filter_values['days_mobile'] == 'week'){
            $monday = date('d.', strtotime('Monday this week'));
            $sunday = date('d.', strtotime('Sunday this week'));
            $html.='<h2>'.$monday. t(date('F', strtotime('Monday this week')),array(),array('context' => 'Long month name')) .' - '.$sunday . t(date('F', strtotime('Sunday this week')),array(),array('context' => 'Long month name')) .'</h2>';
          }
          elseif($filter_values['days_mobile'] == 'month'){
            $firstDay = date('d.', strtotime('First day of this month'));
            $lastDay = date('d.', strtotime('Last day of this month'));
            $html.='<h2>'.$firstDay. t(date('F', strtotime('First day of this month')),array(),array('context' => 'Long month name')) .' - '.$lastDay . t(date('F', strtotime('Last day of this month')),array(),array('context' => 'Long month name')) .'</h2>';
          }
        }
        else {
          if (empty($filter_values['date_end_mobile'])) {
            $timestamp_start = strtotime($filter_values['date_start_mobile']);

            $html .= '<h2>' . date('d', $timestamp_start) . '.' . t(date('F', $timestamp_start), [], ['context' => 'Long month name']) . ' ' . date('Y', $timestamp_start) . '</h2>';
          } elseif (empty($filter_values['date_start_mobile']) and !empty($filter_values['date_end_mobile'])) {
            $timestamp_end = strtotime($filter_values['date_end_mobile']);
            $html .= '<h2>' . date('d', $timestamp_end) . '.' . t(date('F', $timestamp_end), [], ['context' => 'Long month name']) . ' ' . date('Y', $timestamp_end) . '</h2>';
          } else {
            $timestamp_start = strtotime($filter_values['date_start_mobile']);
            $timestamp_end = strtotime($filter_values['date_end_mobile']);
            $html .= '<h2>';
            $html .= date('d', $timestamp_start) . '.' . t(date('F', $timestamp_start), [], ['context' => 'Long month name']) . ' ' . date('Y', $timestamp_start);
            $html .= ' - ';
            $html .= date('d', $timestamp_end) . '.' . t(date('F', $timestamp_end), [], ['context' => 'Long month name']) . ' ' . date('Y', $timestamp_end);
            $html .= '</h2>';
          }
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
