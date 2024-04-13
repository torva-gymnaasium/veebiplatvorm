<?php

namespace Drupal\harno_migrate\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Site\Settings;

/**
 * Class HarnoMigrateForm.
 */
class HarnoMigrateForm extends FormBase {

  public function getFormId() {
    return 'harno_migrate_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $messenger = \Drupal::messenger();
    $migration_get_old_data = \Drupal::service('harno_migrate.get_old_data');
    $migrate_base_url = Settings::get('migrate_base_url', '');
    $config = $this->config('harno_migrate.settings');

    ########################################### ANDMEBAASI ÜHENDUSE KONTROLLIMINE ##########################################
    try {
      $site_name = $migration_get_old_data->getVariableOld('site_name');

      if (empty($site_name) ) {
        $status_text = 'Välises andmebaasis puudub vajalik info. Kontrollida, kas tegemist on eelmise versiooni veebiplatvormi andmebaasiga.';
        $this->logger('harno_migrate')->error($status_text);
        $messenger->addError($status_text);
        return $form;
      }
    }
    catch (\Exception $e) {
      $status_text = $this->t('Failed to connect to your database server. The server reports the following message: %error.<ul><li>Is the database server running?</li><li>Does the database exist, and have you entered the correct database name?</li><li>Have you entered the correct username and password?</li><li>Have you entered the correct database hostname?</li></ul>', ['%error' => $e->getMessage()]);
      $this->logger('harno_migrate')->error($status_text);
      $messenger->addError($status_text);
      return $form;
    }

    $url = Url::fromUri($migrate_base_url, ['attributes' =>['target' => '_blank']]);
    $link_old = Link::fromTextAndUrl($migrate_base_url, $url)->toString();

    $form['site_name'] = [
      '#markup' => '<h2> Migreeritav kool: ' . $site_name . '</h2>',
    ];
    $form['site_url'] = [
      '#markup' => '<h4>' . $link_old . '</h4>',
    ];
    $form['migrate'] = [
      '#type' => 'submit',
      '#value' => 'Alusta migreerimist',
      '#attributes' => ['class' => ['button--primary']]
    ];

    ########################################### KLASSIFIKATSIOONIDE KONTROLLIMINE PRAEGUSEL LEHEL ##########################################
    $taxonomy_types_new = $migration_get_old_data->getTaxonomyTypesNew();
    $taxonomy_types_old = $migration_get_old_data->getTaxonomyTypesOld();

    $header = ['Klassifikatsioon uuel platvormil','Kokku uuel platvormil', 'Klassifikatsioon vanal platvormil', 'Kokku vanal platvormil' ,  'Migreerimise olek', 'Kustuta uuel platvormil'];
    $rows = [];
    $total_count_new = $total_count_old = $total_types = $total_migrated_types = 0;
    foreach ($taxonomy_types_new as $key => $type) {
      $count_new = $migration_get_old_data->taxonomyCountNew($type);

      $url = Url::fromRoute('harno_migrate.delete_taxonomy', ['type' => $type], []);
      $delete_link = Link::fromTextAndUrl('Kustuta uuel platvormil', $url)->toString();

      $migrate_status = $config->get('taxonomy.'.$type);

      $vocab = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->load($type);
      $name_old = '';
      $count_old = 0;
      if (isset($taxonomy_types_old[$key]) AND !empty($taxonomy_types_old[$key])) {
        $name_old_result = $migration_get_old_data->taxonomyTypeNameOld($taxonomy_types_old[$key]);
        if (isset($name_old_result) and !empty($name_old_result)) {
          $vid_old = $name_old_result->vid;
          $name_old = $name_old_result->name;
          $count_old = $migration_get_old_data->taxonomyCountOld($vid_old);
        }
      }
      #if ($count_old == 0) {
        #if($migrate_status < 4) {
        #  $migrate_status = 4;
        #  \Drupal::configFactory()->getEditable('harno_migrate.settings')
        #    ->set('taxonomy.' . $type, $migrate_status)
        #    ->save();
        # }
      #}
      if (isset($migrate_status) AND !empty($migrate_status)) {
        $migrate_status_text = $migration_get_old_data->getStatusText($migrate_status);
      }
      else {
        $status_text = 'Puudub klassifikatsiooni "'.$type.'" migreerimise olek.';
        $this->logger('harno_migrate')->error($status_text);
        $messenger->addError($status_text);
        $migrate_status_text = 'Puudub';
        $migrate_status = 0;
      }
      if($migrate_status > 3) {
        $total_migrated_types++;
      }
      if($migrate_status == 5 ) {
        $delete_link = '';
      }
      $url = Url::fromRoute('entity.taxonomy_vocabulary.overview_form', ['taxonomy_vocabulary' => $type], []);
      $list_link_new = Link::fromTextAndUrl($vocab->label(), $url)->toString();

      $url = Url::fromUri($migrate_base_url . '/et/admin/structure/taxonomy/'. $taxonomy_types_old[$key], ['attributes' =>['target' => '_blank']]);
      $list_link_old = Link::fromTextAndUrl($name_old, $url)->toString();

      $rows[] = [$list_link_new, $count_new, $list_link_old, $count_old, $migrate_status_text, $delete_link];
      $total_count_new += $count_new;
      $total_count_old += $count_old;
      $total_types++;
    }
    $rows[] = [new FormattableMarkup('<strong> @text </strong>',['@text' => 'Kokku:']),
      new FormattableMarkup('<strong> @text </strong>',['@text' => $total_count_new]),
      '',
      new FormattableMarkup('<strong> @text </strong>',['@text' => $total_count_old]),
      new FormattableMarkup('<strong> @text </strong>',['@text' => $total_migrated_types.'/'.$total_types]),
      '',
    ];
    $form['taxonomy_table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#caption' => 'Uue ja vana platvormi klassifikatsioonide jaotus'
    ];

    ########################################### SISUDE KONTROLLIMINE PRAEGUSEL LEHEL ##########################################
    $node_types_new = $migration_get_old_data->getNodeTypesNew();
    $node_types_old = $migration_get_old_data->getNodeTypesOld();
    $node_type_names = node_type_get_names();
    $header = ['Sisutüüp uuel platvormil', 'Kokku uuel platvormil', 'Sisutüüp vanal platvormil', 'Kokku vanal platvormil',  'Migreerimise olek', 'Kustuta uuel platvormil'];
    $rows = [];
    #\Drupal::configFactory()->getEditable('harno_migrate.settings')
    #  ->set('content.article', 1)
    #  ->save();
    $total_count_new = $total_count_old = $total_types = $total_migrated_types = 0;
    foreach ($node_types_new as $key => $type) {
      $count_new = $migration_get_old_data->nodeCountNew($type);

      $url = Url::fromRoute('harno_migrate.delete_content', ['type' => $type], []);
      $delete_link = Link::fromTextAndUrl('Kustuta uuel platvormil', $url)->toString();

      $migrate_status = $config->get('content.'.$type);

      $count_old = $migration_get_old_data->nodeCountOld($node_types_old[$key]);
      $name_old = $migration_get_old_data->nodeTypeNameOld($node_types_old[$key]);
      if ($type == 'page') {
        $count_old += $migration_get_old_data->nodeCountOld('content_page');
        $name_old = $name_old . ' / ' . $migration_get_old_data->nodeTypeNameOld('content_page');
        $count_old += $migration_get_old_data->nodeCountOld('curriculum');
        $name_old = $name_old .' / ' . $migration_get_old_data->nodeTypeNameOld('curriculum');
      }
      #if($count_old == 0) {
      #  if($migrate_status < 4) {
      #    $migrate_status = 4;
      #    \Drupal::configFactory()->getEditable('harno_migrate.settings')
      #      ->set('content.' . $type, $migrate_status)
      #      ->save();
      #  }
      #}
      if (isset($migrate_status) AND !empty($migrate_status)) {
        $migrate_status_text = $migration_get_old_data->getStatusText($migrate_status);
      }
      else {
        $status_text = 'Puudub sisutüübi "'.$type.'" migreerimise olek.';
        $this->logger('harno_migrate')->error($status_text);
        $messenger->addError($status_text);
        $migrate_status_text = 'Puudub';
        $migrate_status = 0;
      }
      if($migrate_status > 3) {
        $total_migrated_types++;
      }
      if($migrate_status == 5 ) {
        $delete_link = '';
      }
      $url = Url::fromRoute('view.content.page_1', [], ['query' => ['type' => $type]]);
      $list_link_new = Link::fromTextAndUrl($node_type_names[$type], $url)->toString();

      $url = Url::fromUri($migrate_base_url . '/et/admin/content', ['query' => ['type' => $node_types_old[$key]], 'attributes' =>['target' => '_blank']]);
      $list_link_old = Link::fromTextAndUrl($name_old, $url)->toString();

      $rows[] = [$list_link_new, $count_new, $list_link_old, $count_old, $migrate_status_text, $delete_link];
      $total_count_new += $count_new;
      $total_count_old += $count_old;
      $total_types++;
    }

    $rows[] = [new FormattableMarkup('<strong> @text </strong>',['@text' => 'Kokku:']),
               new FormattableMarkup('<strong> @text </strong>',['@text' => $total_count_new]),
               '',
               new FormattableMarkup('<strong> @text </strong>',['@text' => $total_count_old]),
               new FormattableMarkup('<strong> @text </strong>',['@text' => $total_migrated_types.'/'.$total_types]),
               '',
             ];
    $form['content_table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#caption' => 'Uue ja vana platvormi sisulehtede jaotus'
    ];
    ########################################### MENÜÜDE/SEADISTUSTE KONTROLLIMINE PRAEGUSEL LEHEL ##########################################
    $settings_types_new = $migration_get_old_data->getSettingsTypesNew();
    $menu_types_old = $migration_get_old_data->getMenuTypesOld();
    $settings_names = $migration_get_old_data->getSettingsNamesNew();
    $settings_links_fragments = ['edit-frontpage-quick-links', 'edit-footer-quick-links', 'edit-general', 'edit-important-contacts',
      'edit-footer-socialmedia-links', 'edit-footer-free-text-area', 'edit-footer-copyright', 'edit-variables'];
    $settings_count_old = [0, 0, 8, 4, 1, 2, 1, 2];
    $settings_names_old = ['', '', 'Üldkontakt', 'Olulised kontaktid', 'Facebook link', 'Jaluse tekstiala', 'Kasutusõiguste märkus', 'Meie lood pealkiri esilehel'  ];

    $header = ['Seadistus uuel platvormil', 'Menüü/seadistus vanal platvormil', 'Kokku vanal platvormil', 'Migreerimise olek'];
    $rows = [];
    $total_count_old = $total_types = $total_migrated_types = 0;
    foreach ($settings_types_new as $key => $type) {
      $migrate_status = $config->get('settings.'.$type);
      if($key < 2) {
        $count_old = $migration_get_old_data->menuCountOld($menu_types_old[$key]);
        $name_old = $migration_get_old_data->menuNameOld($menu_types_old[$key]);

        $url = Url::fromUri($migrate_base_url . '/et/admin/structure/menu/manage/' . $menu_types_old[$key], ['attributes' => ['target' => '_blank']]);
        $list_link_old = Link::fromTextAndUrl($name_old, $url)->toString();

      } else {
        $count_old = $settings_count_old[$key];
        $name_old = $settings_names_old[$key];
        $url = Url::fromUri($migrate_base_url . '/et/admin/hitsa/variables/header_footer', ['attributes' => ['target' => '_blank']]);
        $list_link_old = Link::fromTextAndUrl($name_old, $url)->toString();
      }
      #if($count_old == 0) {
      #  if($migrate_status < 4) {
      #    $migrate_status = 4;
      #    \Drupal::configFactory()->getEditable('harno_migrate.settings')
      #      ->set('settings.' . $type, $migrate_status)
      #      ->save();
      #  }
      #}
      if (isset($migrate_status) AND !empty($migrate_status)) {
        $migrate_status_text = $migration_get_old_data->getStatusText($migrate_status);
      }
      else {
        $status_text = 'Puudub seadistuse "'.$type.'" migreerimise olek.';
        $this->logger('harno_migrate')->error($status_text);
        $messenger->addError($status_text);
        $migrate_status_text = 'Puudub';
        $migrate_status = 0;
      }
      if($migrate_status > 3) {
        $total_migrated_types++;
      }

      $url = Url::fromRoute('harno_settings.settings_form', [], ['fragment' => $settings_links_fragments[$key]]);
      $list_link_new = Link::fromTextAndUrl($settings_names[$key], $url)->toString();

      $rows[] = [$list_link_new, $list_link_old, $count_old, $migrate_status_text];
      $total_count_old += $count_old;
      $total_types++;
    }
    $rows[] = [new FormattableMarkup('<strong> @text </strong>',['@text' => 'Kokku:']),
      '',
      new FormattableMarkup('<strong> @text </strong>',['@text' => $total_count_old]),
      new FormattableMarkup('<strong> @text </strong>',['@text' => $total_migrated_types.'/'.$total_types]),
    ];

    $form['settings_table'] = [
      '#theme' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#caption' => 'Uue ja vana platvormi menüüde ja seadistuste jaotus'
    ];
    $form['delete_all'] = [
      '#type' => 'submit',
      '#value' => 'Kustuta kogu sisu uuel platvormil',
      '#submit' => array([$this, 'submitDeleteAll']),
    ];
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
    \Drupal::service('harno_migrate.get_old_data')->startMigrate();
  }

  /**
   * {@inheritdoc}
   */
  public function submitDeleteAll(array &$form, FormStateInterface $form_state) {
    $url = Url::fromRoute('harno_migrate.delete_all');
    $form_state->setRedirectUrl($url);
  }
}
