<?php

namespace Drupal\harno_migrate\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a confirmation form to confirm deletion of something by id.
 */
class HarnoMigrateConfirmContentDeleteForm extends ConfirmFormBase {
  /**
   * Type of the item to delete.
   *
   * @var string
   */
  protected $type;

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $type = NULL) {
    $this->type = $type;
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $messenger = \Drupal::messenger();

    $ids = \Drupal::entityQuery('node')
          ->condition('type', $this->type)
          ->accessCheck(false)
          ->execute();

    $storage_handler = \Drupal::entityTypeManager()->getStorage("node");
    $entities = $storage_handler->loadMultiple($ids);
    $storage_handler->delete($entities);

    $count = count($ids);
    $node_type_names = node_type_get_names();
    $status_text = 'Kustutati ' . $count .' "'. $node_type_names[$this->type].'" tüüpi sisulehte uuel platvormil.';
    $this->logger('harno_migrate')->info($status_text);
    $messenger->addStatus($status_text);

    \Drupal::configFactory()->getEditable('harno_migrate.settings')
      ->set('content.'.$this->type, 1)
      ->save();
    $url = Url::fromRoute('harno_migrate.migrate_form');
    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "harno_migrate_confirm_content_delete_form";
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('harno_migrate.migrate_form');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    $node_type_names = node_type_get_names();
    return 'Kas olete kindel, et soovite kustutada kõik "'. $node_type_names[$this->type].'" tüüpi sisulehed uuel platvormil?';
  }

}
