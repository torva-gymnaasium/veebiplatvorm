<?php

namespace Drupal\harno_migrate\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a confirmation form to confirm deletion of something by id.
 */
class HarnoMigrateDeleteAllForm extends ConfirmFormBase {
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

    $ids = \Drupal::entityQuery('node')->accessCheck(false)->execute();
    $storage_handler = \Drupal::entityTypeManager()->getStorage('node');
    $entities = $storage_handler->loadMultiple($ids);
    $storage_handler->delete($entities);
    $count = count($ids);
    $status_text = 'Kustutati ' . $count .' sisulehte uuel platvormil.';
    $this->logger('harno_migrate')->info($status_text);
    $messenger->addStatus($status_text);

    $ids = \Drupal::entityQuery('menu_link_content')->condition('menu_name', 'main')->accessCheck(false)->execute();
    $storage_handler = \Drupal::entityTypeManager()->getStorage('menu_link_content');
    $entities = $storage_handler->loadMultiple($ids);
    $storage_handler->delete($entities);
    $count = count($ids);
    $status_text = 'Kustutati ' . $count .' menüüpunkti uuel platvormil.';
    $this->logger('harno_migrate')->info($status_text);
    $messenger->addStatus($status_text);

    $ids = \Drupal::entityQuery('paragraph')->accessCheck(false)->execute();
    $storage_handler = \Drupal::entityTypeManager()->getStorage('paragraph');
    $entities = $storage_handler->loadMultiple($ids);
    $storage_handler->delete($entities);
    $count = count($ids);
    $status_text = 'Kustutati ' . $count .' paragrahvi uuel platvormil.';
    $this->logger('harno_migrate')->info($status_text);
    $messenger->addStatus($status_text);

    $ids = \Drupal::entityQuery('taxonomy_term')->accessCheck(false)->execute();
    $storage_handler = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $entities = $storage_handler->loadMultiple($ids);
    $storage_handler->delete($entities);
    $count = count($ids);
    $status_text = 'Kustutati ' . $count .' klassifikatsiooni terminit uuel platvormil.';
    $this->logger('harno_migrate')->info($status_text);
    $messenger->addStatus($status_text);

    $ids = \Drupal::entityQuery('media')->accessCheck(false)->execute();
    $storage_handler = \Drupal::entityTypeManager()->getStorage('media');
    $entities = $storage_handler->loadMultiple($ids);
    $storage_handler->delete($entities);
    $count = count($ids);
    $status_text = 'Kustutati ' . $count .' meedia kirjet uuel platvormil.';
    $this->logger('harno_migrate')->info($status_text);
    $messenger->addStatus($status_text);

    $ids = \Drupal::entityQuery('file')->accessCheck(false)->execute();
    $storage_handler = \Drupal::entityTypeManager()->getStorage('file');
    $entities = $storage_handler->loadMultiple($ids);
    $storage_handler->delete($entities);
    $count = count($ids);
    $status_text = 'Kustutati ' . $count .' faili uuel platvormil.';
    $this->logger('harno_migrate')->info($status_text);
    $messenger->addStatus($status_text);

    \Drupal::configFactory()->getEditable('harno_migrate.settings')
      ->set('content.location', 1)
      ->set('content.gallery', 1)
      ->set('content.worker', 1)
      ->set('content.class', 1)
      ->set('content.page', 1)
      ->set('content.calendar', 1)
      ->set('content.food_menu', 5)
      ->set('content.partner_logo', 1)
      ->set('content.article', 1)
      ->set('taxonomy.positions', 1)
      ->set('taxonomy.training_keywords', 1)
      ->set('taxonomy.media_catalogs', 1)
      ->set('taxonomy.departments', 1)
      ->set('taxonomy.eating_places', 5)
      ->set('taxonomy.event_keywords', 1)
      ->set('taxonomy.food_groups', 5)
      ->set('taxonomy.catering_service_provider', 5)
      ->set('taxonomy.school_hours', 1)
      ->set('taxonomy.academic_year', 1)
      ->set('settings.general', 1)
      ->set('settings.frontpage_quick_links', 1)
      ->set('settings.important_contacts', 1)
      ->set('settings.footer_socialmedia_links', 1)
      ->set('settings.footer_quick_links', 1)
      ->set('settings.footer_free_text_area', 1)
      ->set('settings.footer_copyright', 1)
      ->set('settings.variables', 1)
      ->save();


    $url = Url::fromRoute('harno_migrate.migrate_form');
    $form_state->setRedirectUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() : string {
    return "harno_migrate_confirm_delete_all_form";
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
    return 'Kas olete kindel, et soovite kustutada kogu sisu (sisulehed, paragrahvid, klassifikatsioonid, menüüpunktid, meedia ja failid) uuel platvormil? Kustutatakse ka need sisud, mida ei migreerita. Koos sellega saavad ka migreerimise olekud vaikimisi väärtused.';
  }

}
