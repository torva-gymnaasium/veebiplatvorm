<?php

namespace Drupal\harno_blocks\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds the search form for the search block.
 *
 * @internal
 */
class HarnoBlocksSearchAPIForm extends FormBase {

  /**
   * Constructs a new SearchBlockForm.
   *
   */
  public function __construct() {

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'harno_blocks_search_api_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['search_keys'] = [
      '#type' => 'search',
      '#title' => $this->t('Search...'),
      '#require' => TRUE,
      '#size' => 10,
      '#attributes' => ['title' => $this->t('Enter the terms you wish to search for.'), 'placeholder' => $this->t( 'Search' ).'...'],
      '#is_search_header' => TRUE,
      '#prefix' => '<div class="form-item search-input">'
    ];

    $form['submit_search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Search...'),
      '#attributes' => ['class' => ['btn-link', 'btn-search'], 'title' => $this->t('Search...')],
      '#suffix' => '</div>',
      '#no_btn_primary' => TRUE
    ];
    $plugin_id = 'views:general_search';

    $cache_tag = "search_api_autocomplete_search_list:$plugin_id";
    if (!isset($form['#cache']['tags'])
      || !in_array($cache_tag, $form['#cache']['tags'])) {
      $form['#cache']['tags'][] = $cache_tag;
    }
    /** @var \Drupal\search_api_autocomplete\Entity\SearchStorage $search_storage */
    $search_storage = \Drupal::entityTypeManager()
      ->getStorage('search_api_autocomplete_search');
    $search = $search_storage->loadBySearchPlugin($plugin_id);
    if (!$search || !$search->status()) {

    } else {
      //print_r($search);
      $data = [
        'display' => 'general_search',
        'filter' => 'keys',
        'arguments' => [],
      ];
      \Drupal::getContainer()
        ->get('search_api_autocomplete.helper')
        ->alterElement($form['search_keys'], $search, $data);
      $form['#attached']['library'][] = 'search_api_autocomplete/search_api_autocomplete';
    }

    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('view.general_search.general_search', ['keys' => str_replace(' ', '+', $form_state->getValue('search_keys'))]);
  }

}
