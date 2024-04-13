<?php

namespace Drupal\harno_pages\Form;

use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\InsertCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\harno_pages\Controller\ContactsController;
use Drupal\Core\Entity\Element\EntityAutocomplete;
use Drupal\Core\Ajax\AjaxResponse;

/**
 *
 */
class ContactsFilterForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    // TODO: Implement getFormId() method.
    return 'contacts_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $academic_years = NULL,$type=NULL) {
    // devel_dump($academic_years);
    if (!empty($type)){
      $form['#storage']['type'] = $type;
    }
    if(!empty($academic_years)){
      $form['#attributes']['data-plugin'] = 'filters';
      $form['#attributes']['role'] = 'filter';
      $form['#theme_wrappers'] = ['form-contacts'];
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
        '#id' => 'contacts-topFilter',
      ];
      $form['top']['positions'] = [
        '#title' => t('Choose position'),
        '#id' => 'worker-position',
        '#type' => 'select',
        '#attributes' => [
          'data-disable-refocus' => true,
        ],
        '#placeholder' => ' ',
        '#ajax' => [
          'wrapper' => 'filter-target',
          'event' => 'change',
          'callback' => '::filterResults',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Verifying entry...'),
          ],
        ],
        '#options' => $academic_years['positions'],
      ];
      $form['top']['departments'] = [
        '#title' => t('Choose department'),
        '#id' => 'worker-department',
        '#type' => 'select',
        '#attributes' => [
          'data-disable-refocus' => true,
        ],
        '#placeholder' => ' ',
        '#ajax' => [
          'wrapper' => 'filter-target',
          'event' => 'change',
          'callback' => '::filterResults',
          'progress' => [
            'type' => 'throbber',
            'message' => $this->t('Verifying entry...'),
          ],
        ],
        '#options' => $academic_years['departments'],
      ];
      $form['top']['contactsSearch'] = [
        '#type' => 'textfield',
        '#title' => t('Search contacts'),
        '#attributes' => [
          'alt' => t('Type contact name you are looking for'),
        ],
//          '#autocomplete_route_name' => 'harno_pages.contacts.autocomplete',
        '#ajax' => [
          'wrapper' => 'filter-target',
          'keypress' => TRUE,
          'callback' => '::filterResults',
          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];
      $form['top']['searchbutton'] = [
        '#attributes' => [
          'style' => 'display:none;',
        ],
        '#type' => 'button',
        '#title' => t('Search contacts'),
        '#value' => t('Submit'),
        '#ajax' => [
          'callback' => '::filterResults',
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
          'callback' => '::filterResults',
          'wrapper' => 'filter-target',
          'disable-refocus' => true,
          'keypress'=>TRUE,
        ],
      ];
      $form['bottom'] = [
        '#type' => 'fieldset',
        '#id' => 'contacts-bottomSearch',
      ];
      $form['bottom']['contactsSearchMobile'] = [
        '#type' => 'textfield',
        '#title' => t('Search contacts'),
        '#attributes' => [
          'alt' => t('Type contact name you are looking for'),
        ],
        '#ajax' => [
          'wrapper' => 'filter-target',
          'keypress' => TRUE,
          'callback' => '::filterResults',
//          'event' => 'finishedinput',
          'disable-refocus' => TRUE,
        ],
      ];
      $form['bottom']['searchbutton'] = [
        '#attributes' => [
          'style' => 'display:none;',
        ],
        '#type' => 'button',
        '#title' => t('Search contacts'),
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
        '#attributes' => [
          'style' => 'display:none;',
        ],
        '#ajax' => [
          'callback' => '::filterResults',
          'wrapper' => 'filter-target',
          'disable-refocus' => true,
          'keypress'=>TRUE,
        ],
      ];
      $form['filter'] = [
        '#type' => 'fieldset',
        '#id' => 'contacts-bottomFilter',
      ];
    }
    if(!empty($filter_values = $form_state->getValues())) {
      if (!empty($filter_values['positions'])) {
        $form['top']['positions']['#default_value'] = $filter_values['positions'];
      }
      if (!empty($filter_values['departments'])) {
        $form['top']['departments']['#default_value'] = $filter_values['departments'];
      }
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
  public function validateForm(array &$form, FormStateInterface $form_state)
  {
    parent::validateForm($form, $form_state); // TODO: Change the autogenerated stub
  }

  /**
   *
   */
  public function filterResults(array &$form, FormStateInterface $form_state) {

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
      if (!empty($form_values['years'])) {
        foreach ($form_values['years'] as $year) {
          if (!empty($year)) {
              $parameters['years'][$year] = $year;
          }
        }
      }
      if (isset($form_values['contactsSearch'])) {
        $parameters['contactsSearch'] = $_REQUEST['contactsSearch'];
      }
      if (isset($form_values['contactsSearchMobile'])) {
        $parameters['contactsSearchMobile'] = $_REQUEST['contactsSearchMobile'];
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
        if($filter_values['departments'] == 'all' and $_REQUEST['departments'] != 'all'){
          $filter_values['departments'] = $_REQUEST['departments'];
        }
        $filters['#theme'] = 'active-filters';
        $filters['#content']['departments'] = $filter_values['departments'];
      }
      if(!empty($filter_values['departments_mobile'])){
        if($filter_values['departments'] == 'all' and $_REQUEST['departments_mobile'] != 'all'){
          $filter_values['departments_mobile'] = $_REQUEST['departments_mobile'];
        }
        $filters['#theme'] = 'active-filters';
        $filters['#content']['departments'] = $filter_values['departments_mobile'];
      }
      if(empty($filter_values['positions']) or empty($filter_values['positions_mobile'])){
        if(!empty($_REQUEST['positions'])){
          $filters['#content']['positions'] = $_REQUEST['positions'];
        }
        elseif(!empty($_REQUEST['positions_mobile'])){
          $filters['#content']['departments'] = $_REQUEST['positions_mobile'];
        }
      }
      if(empty($filter_values['departments']) or empty($filter_values['departments_mobile'])){
        if(!empty($_REQUEST['departments'])){
          $filters['#content']['departments'] = $_REQUEST['departments'];
        }
        elseif(!empty($_REQUEST['departments_mobile'])){
          $filters['#content']['departments'] = $_REQUEST['departments_mobile'];
        }
      }
    }
    $build = [];
    $build['#theme'] = $type.'-response';
    $build['#content'] = $contacts;
    $build['#language'] = $language;
    $build['#pager'] = [
      '#type' => 'pager',
      '#parameters' => $parameters,
    ];
//    $build['#attached']['library'][] = 'harno_pages/accordion';
    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#mobile-active-filters', $filters));
//    $response->addCommand(new ReplaceCommand('#filter-update', $form));
    $response->addCommand(new ReplaceCommand('#filter-target',$build));
//    $response->addCommand(new InvokeCommand('.accordion--contacts', 'accordion'));
    $response->addCommand(new InvokeCommand('.mobile-filters', 'filtersModal'));
    $response->addCommand(new InvokeCommand('.form-item', 'movingLabel'));
    $triggering_element = $form_state->getTriggeringElement();
    if (strpos($triggering_element['#id'],'edit-ready')!==false){
      $response->addAttachments(['library'=>'harno_pages/filter_focus']);
//      $response->addAttachments(['library'=>'harno_pages/harno_pages']);
      $response->addCommand(new InvokeCommand('.modal-open','closeModal'));
//      $response->addCommand(new InvokeCommand('form','formFilter'));
    }
    for($x = 0; $x <= $contacts['overall_total']; $x++){
//      $response->addCommand(new InvokeCommand('#contact-modal-'.$x, 'modal'));
    }

    return $response;
  }
}
