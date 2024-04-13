<?php

namespace Drupal\harno_settings\Form;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;

/**
 * Class HarnoSettingsForm.
 */
class HarnoColorsForm extends ConfigFormBase {

  /**
   * HarnoSettingsForm constructor.
   *
   * @param ConfigFactoryInterface     $config_factory
   */
  public function __construct (
    ConfigFactoryInterface $config_factory
  ) {
    parent::__construct($config_factory);
  }
  /**
   * @param ContainerInterface $container
   *
   * @return ConfigFormBase|HarnoSettingsForm
   */
  public static function create (ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'harno_settings.colors',
    ];
  }
  public function getFormId() {
    return 'harno_colors_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('harno_settings.colors');


    $form['color_main'] = array(
      '#type' => 'color',
      '#title' => 'Põhitoon',
      '#default_value' => $config->get('color.main'),
      '#required' => TRUE,
      '#description' => 'Valitud värvikood on: <strong id="edit-color-main-code">' . $config->get('color.main'). '</strong>',
    );
    $form['color_lighter'] = array(
      '#type' => 'color',
      '#title' => '10% toon',
      '#default_value' => $config->get('color.lighter'),
      '#attributes' => array('readonly' => 'readonly'),
      '#required' => TRUE,
      '#description' => 'Automaatselt genereeritud värvikood on: <strong id="edit-color-lighter-code">' . $config->get('color.lighter'). '</strong>',
    );

    $form['color_additional'] = array(
      '#type' => 'color',
      '#title' => 'Lisatoon',
      '#required' => TRUE,
      '#default_value' => $config->get('color.additional'),
      '#description' => 'Valitud värvikood on: <strong id="edit-color-additional-code">' . $config->get('color.additional') . '</strong><br/><a href="https://webaim.org/resources/contrastchecker/" target="_blank">WCAG kontrastsuse kontroll</a>',
    );
    $form['#attached']['library'][] = 'harno_settings/color_calculate';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    #$messenger = \Drupal::messenger();
    #$messenger->addMessage('main_color: ' . $form_state->getValue('main_color'), $messenger::TYPE_WARNING);
    $this->config('harno_settings.colors')
      ->set('color.main', $form_state->getValue('color_main'))
      ->set('color.lighter', $form_state->getValue('color_lighter'))
      ->set('color.additional', $form_state->getValue('color_additional'))
      ->save();
    $database = \Drupal::service('database');
    $num_deleted = $database->delete('config')
      ->condition('name', 'harno_settings.colors')
      ->condition('collection', 'language.en')
      ->execute();
    Cache::invalidateTags(['harno-config']);
  }
}
