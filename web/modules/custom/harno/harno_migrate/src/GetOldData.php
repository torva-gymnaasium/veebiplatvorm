<?php

namespace Drupal\harno_migrate;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\node\Entity\Node;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Database\Connection;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Site\Settings;
use Drupal\Core\File\FileSystemInterface;
use Drupal\media\Entity\Media;
use Drupal\language\ConfigurableLanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\Component\Utility\UrlHelper;
use Drupal\file\Entity\File;
use DOMDocument;
use DOMXPath;
use stdClass;

/**
 * Class DefaultService.
 *
 * @package Drupal\GetOldData
 */

class GetOldData {
  use StringTranslationTrait;

  protected $migrationDatabase;
  protected $database;
  /**
   * Guzzle\Client instance.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;
  /**
   * The config object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $configFactory;
  /**
     * A logger instance.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;
  /**
     * The Messenger service.
     *
     * @var \Drupal\Core\Messenger\MessengerInterface
     */
    protected $messenger;

    protected $storage;

  /**
   * Stores the state storage service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;
  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;
  /**
   * The EntityTypeManager.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;
  /**
     * Constructs an Importer object.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The factory for configuration objects.
  */

  public function __construct(Connection $migration_database, Connection $database, ConfigFactoryInterface $config_factory,
                              ClientInterface $http_client, LoggerChannelFactoryInterface $loggerFactory,
                              MessengerInterface $messenger, TranslationInterface $string_translation,
                              EntityTypeManager $entity_type_manager, StateInterface $state,
                              FileSystemInterface $file_system, ConfigurableLanguageManagerInterface $language_manager) {
    $this->migrationDatabase = $migration_database;
    $this->database = $database;
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('harno_migrate');
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
    $this->storage = $entity_type_manager->getStorage('node');
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
    $this->fileSystem = $file_system;
    $this->languageManager = $language_manager;
  }

  public function startMigrate() {
    $migrate_type = 'all';
    $debug = FALSE;

    $config = $this->configFactory->get('harno_migrate.settings');

    $batch_builder = (new BatchBuilder())
      ->setTitle('Andmete migreerimine vanalt platvormilt...')
      ->setFinishCallback('harno_migrate_import_data_finished')
      ->setInitMessage('Migreerimine algab...')
      ->setProgressMessage('Teostatud @current tegevust. Kokku on vaja teostada @total tegevust.')
      ->setErrorMessage('Migreerimisel tekkis viga!');

    if ($migrate_type == 'taxonomy' OR $migrate_type == 'all') {
      ####################################################### TAXONOMY TYPES ###############################################
      $taxonomy_types_new = $this->getTaxonomyTypesNew();
      $taxonomy_types_old = $this->getTaxonomyTypesOld();

      foreach ($taxonomy_types_new as $key => $type) {
        $migrate_status = $config->get('taxonomy.'.$type);
        if ($migrate_status < 3) {
          $terms = $this->getTermOld($taxonomy_types_old[$key]);
          $batch_builder->addOperation('harno_migrate_import_taxonomy_type_started', [$type]);
          foreach ($terms as $term) {
            $batch_builder->addOperation('harno_migrate_import_taxonomy', [$term, $type, $debug]);
          }
          if (!$debug) {
            $batch_builder->addOperation('harno_migrate_import_taxonomy_type_finished', [$type]);
          }
          //break; //TEMP
        }
      }
    }
    if ($migrate_type == 'node' OR $migrate_type == 'all') {
      ####################################################### NODE TYPES ###############################################
      $count_by_type = [];
      $node_types_new = $this->getNodeTypesNew();
      $node_types_old = $this->getNodeTypesOld();
      # \Drupal::configFactory()->getEditable('harno_migrate.settings')
      #  ->set('content.page', 1)
      #  ->save();
      foreach ($node_types_new as $key => $type) {
        $migrate_status = $config->get('content.' . $type);
        if ($migrate_status < 3) {
          $count_by_type[$node_types_old[$key]] = 0;
          $nodes = $this->getNodeOld($node_types_old[$key]);
          if ($type == 'page') {
            $nodes = array_merge($nodes, $this->getNodeOld('content_page'));
            $nodes = array_merge($nodes, $this->getNodeOld('curriculum'));
          }
          $batch_builder->addOperation('harno_migrate_import_node_type_started', [$type]);
          foreach ($nodes as $node) {
            $count_by_type[$node_types_old[$key]]++;
            #if ($count_by_type[$node_types_old[$key]] >= 1 AND $count_by_type[$node_types_old[$key]] <= 2) { #@TODO TEMP
              $batch_builder->addOperation('harno_migrate_import_node', [
                $node,
                $type,
                $count_by_type[$node_types_old[$key]],
                $debug
              ]);
            #}
          }
          if (!$debug) {
            $batch_builder->addOperation('harno_migrate_import_node_type_finished', [$type]);
          }
          //break; //TEMP
        }
      }
      if (!$debug) {
        $batch_builder->addOperation('harno_migrate_update_node_internal_links');
      }
    }
    if ($migrate_type == 'settings' OR $migrate_type == 'all') {
      ####################################################### SETTINGS ###############################################
      $settings_types_new = $this->getSettingsTypesNew();
      $settings_types_old = $this->getMenuTypesOld();
      $settings_names = $this->getSettingsNamesNew();
      #\Drupal::configFactory()->getEditable('harno_migrate.settings')
      #  ->set('settings.general', 1)
      #  ->save();
      foreach ($settings_types_new as $key => $type) {
        $migrate_status = $config->get('settings.' . $type);
        if ($migrate_status < 3) {
          if ($key < 2) {
            $settings = $this->getMenuItemsOld($settings_types_old[$key]);
          }
          else {
            $settings = $this->getSettingsOld($type);
          }
          $batch_builder->addOperation('harno_migrate_import_settings_started', [
            $type,
            $settings_names[$key]
          ]);
          $batch_builder->addOperation('harno_migrate_import_settings', [
            $settings,
            $type,
            $settings_names[$key],
            $debug
          ]);
          if (!$debug) {
            $batch_builder->addOperation('harno_migrate_import_settings_finished', [
              $type,
              $settings_names[$key]
            ]);
          }
          //break; //TEMP
        }
      }
    }
    batch_set($batch_builder->toArray());
  }

  public function getTaxonomyTypesNew() {
    return ['positions', 'training_keywords', 'media_catalogs', 'departments', 'eating_places', 'event_keywords', 'food_groups', 'catering_service_provider', 'school_hours', 'academic_year'];
  }

  public function getTaxonomyTypesOld() {
    return ['contacts_job_position', 'training_tags', 'media_folders', 'contacts_department', '', 'hitsa_event_tags', 'catering_food_type', 'catering_provider', 'hitsa_hour_times', 'academic_years'];
  }

  public function getNodeTypesNew() {
    return ['location', 'gallery', 'worker', 'class', 'page', 'calendar', 'food_menu', 'partner_logo', 'article'];
  }

  public function getNodeTypesOld() {
    return ['contact_location', 'gallery', 'contact', 'alumnus', 'page', 'event', 'catering', 'logo', 'article'];
  }

  public function getSettingsTypesNew() {
    return ['frontpage_quick_links', 'footer_quick_links', 'general', 'important_contacts', 'footer_socialmedia_links',
      'footer_free_text_area', 'footer_copyright', 'variables'];
  }

  public function getSettingsNamesNew() {
    return ['Avalehe kiirlingid', 'Jaluse kiirlingid', 'Haridusasutuse üldinfo', 'Jaluse olulisemad kontaktid',
      'Jaluse sotsiaalmeedia lingid', 'Jaluse vabatekstiala', 'Kasutusõiguste märkus', 'Muutujad'];
  }

  public function getMenuTypesOld() {
    return ['hitsa-quicklinks-menu', 'hitsa-header-menu'];
  }
  public function getVariableOld($name = 'site_name') {
    $results = $this->migrationDatabase->select('variable', 'v')->fields('v')->condition('v.name', $name)->execute();
    $records = $results->fetchAll();
    foreach($records as $record) {
      return unserialize($record->value);
    }
  }
  public function getVariableLanguageOld($name = 'site_name') {
    $results = $this->migrationDatabase->select('variable_store', 'v')->fields('v')->condition('v.name', $name)->execute();
    $records = $results->fetchAll();
    $return = [];
    foreach($records as $record) {
      $return[$record->realm_key] = $record->value;
    }
    return $return;
  }
  public function getStringTranslationOld($lid) {
    $results = $this->migrationDatabase->select('locales_target', 'l')->fields('l')->condition('l.lid', $lid)->execute();
    return $results->fetchAll();
  }
  public function getLanguagesOld() {
    $results = $this->migrationDatabase->select('languages', 'l')->fields('l')->condition('l.enabled', 1)->execute();
    return $results->fetchAll();
  }
  public function getNodeOld($type) {
    $results = $this->migrationDatabase->select('node', 'n')->fields('n')->condition('n.type', $type)->orderBy('n.nid')->execute();
    return $results->fetchAll();
  }
  public function getNodeOldByNid($nid) {
    $results = $this->migrationDatabase->select('node', 'n')->fields('n')->condition('n.nid', $nid)->orderBy('n.nid')->execute();
    return $results->fetchAll();
  }
  public function getNodeOldByUrlAlias($url_alias) {
    $results = $this->migrationDatabase->select('url_alias', 'u')
      ->fields('u')
      ->condition('u.alias', $url_alias)
      ->orderBy('u.pid', 'DESC')
      ->range(0, 1)
      ->execute();
    return $results->fetchAll();
  }
  public function getUserOld($uid) {
    $results = $this->migrationDatabase->select('users', 'u')->fields('u', ['name'])->condition('u.uid', $uid)->execute();
    return $results->fetchField();
  }
  public function getMenuItemsOld($type) {
    $results = $this->migrationDatabase->select('menu_links', 'm')->fields('m')->condition('m.menu_name', $type)->condition('m.hidden', 0)->orderBy('m.weight')->execute();
    return $results->fetchAll();
  }
  public function getSettingsOld($type) {
    $data = (object)[];
    switch ($type) {
      case 'general':
        $data->general_contact_name = $this->getVariableOld('general_contact_name');
        $data->site_slogan = $this->getVariableOld('site_slogan');
        $data->hitsa_fp_image_fid = $this->getVariableOld('hitsa_fp_image_fid');
        $data->hitsa_site_logo_fid = $this->getVariableOld('hitsa_site_logo_fid');
        $data->theme_hitsa_settings = $this->getVariableOld('theme_hitsa_settings');
        $data->general_contact_address = $this->getVariableOld('general_contact_address');
        $data->general_contact_phone_nr = $this->getVariableOld('general_contact_phone_nr');
        $data->general_contact_email = $this->getVariableOld('general_contact_email');
        break;
      case 'important_contacts':
        $data->important_contact[1]['name'] = $this->getVariableOld('important_contact_name_1');
        $data->important_contact[1]['phone'] = $this->getVariableOld('important_contact_phone_1');
        $data->important_contact[2]['name'] = $this->getVariableOld('important_contact_name_2');
        $data->important_contact[2]['phone'] = $this->getVariableOld('important_contact_phone_2');
        $data->important_contact[3]['name'] = $this->getVariableOld('important_contact_name_3');
        $data->important_contact[3]['phone'] = $this->getVariableOld('important_contact_phone_3');
        $data->important_contact[4]['name'] = $this->getVariableOld('important_contact_name_4');
        $data->important_contact[4]['phone'] = $this->getVariableOld('important_contact_phone_4');
        break;
      case 'footer_socialmedia_links':
        $data->hitsa_fb_link = $this->getVariableOld('hitsa_fb_link');
        break;
      case 'footer_free_text_area':
        $data->footer_text_area_title = $this->getVariableOld('footer_text_area_title');
        $data->footer_text_area = $this->getVariableOld('footer_text_area');
        break;
      case 'footer_copyright':
        $data->footer_copyright_notice = $this->getVariableOld('footer_copyright_notice');
        break;
      case 'variables':
        $data->front_our_stories_title = $this->getVariableLanguageOld('front_our_stories_title');
        $data->juhan_api_key = $this->getVariableOld('juhan_api_key');
        #$data->test = $this->getVariableAllOld();
        break;
    }
    return $data;
  }
  public function getTermOld($type) {
    $query = $this->migrationDatabase->select('taxonomy_term_data', 't')->fields('t');
    $query->join('taxonomy_vocabulary', 'v', 't.vid = v.vid');
    $query->join('taxonomy_term_hierarchy', 'h', 't.tid = h.tid');
    $query->leftJoin('taxonomy_term_data', 'pt', 'pt.tid = h.parent');
    $query->leftJoin('i18n_string', 'i', "t.tid = i.objectid AND i.type = 'term' AND i.property = 'name'");
    $query->fields('v', ['machine_name']);
    $query->fields('h', ['parent']);
    $query->fields('i', ['lid']);
    $query->addField('pt', 'name', 'parent_name');
    $query->condition('v.machine_name', $type);
    $query->orderBy('h.parent');
    $query->orderBy('t.weight');
    $query->orderBy('t.name');
    $results = $query->execute();
    return $results->fetchAll();
  }

  public function getTermNewByName($term_name, $vid) {
    $term = $this->entityTypeManager->getStorage('taxonomy_term')
           ->loadByProperties(['name' => $term_name, 'vid' => $vid]);
    $term = reset($term);
    if (isset($term) AND !empty($term)) {
      return $term->id();
    }
    return FALSE;
  }

  public function nodeCountNew($type) {
    $query = $this->storage->getQuery()->condition('type', $type);
    return $query->count()->accessCheck(false)->execute();
  }
  public function getNodeNewByTitle($title) {
    $query = $this->storage->getQuery()->condition('title', $title);
    return $query->accessCheck(false)->execute();
  }
  public function taxonomyCountNew($vid) {
    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()->condition('vid', $vid);
    return $query->count()->accessCheck(false)->execute();
  }

  public function nodeCountOld($type) {
    $results = $this->migrationDatabase->select('node', 'n')->fields('n')->condition('n.type', $type)->execute();
    $records = $results->fetchAll();
    return count($records);
  }

  public function taxonomyCountOld($vid) {
    $results = $this->migrationDatabase->select('taxonomy_term_data', 'ttd')->fields('ttd')->condition('ttd.vid', $vid)->execute();
    $records = $results->fetchAll();
    return count($records);
  }

  public function menuCountOld($type) {
    $results = $this->migrationDatabase->select('menu_links', 'm')->fields('m')->condition('m.menu_name', $type)->condition('m.hidden', 0)->execute();
    $records = $results->fetchAll();
    return count($records);
  }

  public function nodeTypeNameOld($type) {
    $results = $this->migrationDatabase->select('node_type', 'nt')->fields('nt', ['name'])->condition('nt.type', $type)->execute();
    return $results->fetchField();
  }

  public function taxonomyTypeNameOld($type) {
    $query = $this->migrationDatabase->select('taxonomy_vocabulary', 'tv')->fields('tv', ['vid','name'])->condition('tv.machine_name', $type);
    $result = $query->execute()->fetchAll();
    foreach ($result as $record) {
      return $record;
    }
  }
  public function menuNameOld($type) {
    $results = $this->migrationDatabase->select('menu_custom', 'm')->fields('m', ['title'])->condition('m.menu_name', $type)->execute();
    return $results->fetchField();
  }
  public function getStatusText($status) {
    $status_array = $this->getStatusCodes();
    return $status_array[$status];
  }

  public function getStatusCodes() {
    return [ 1 => 'Ootel', 2 => 'Pooleli', 3 => 'Ebaõnnestunud', 4 => 'Valmis', 5 => 'Ei migreeri', ];
  }
  public function entityOldFindByReferenceData($table, $fields, $reference_field, $reference_value, $old_type, $entity_type = 'node') {
    if (!$this->migrationDatabase->schema()->tableExists($table)) {
      $status_text = "Puudub tabel " . $table . ", jätame vahele ".$old_type." sisutüübi väljad ".print_r($fields,1).".";
      $this->messenger->addWarning($status_text);
      $this->logger->warning($status_text);
      return [];
    }
    $query = $this->migrationDatabase->select($table, 'f')->fields('f', $fields)
      ->condition('f.'.$reference_field, $reference_value)
      ->condition('f.entity_type', $entity_type)
      ->condition('f.bundle', $old_type);
    $query->orderBy('f.delta');
    return $query->execute()->fetchAll();
  }

  public function entityOldTextFieldData($table, $fields, $old_nid, $old_type, $entity_type = 'node') {
    if (!$this->migrationDatabase->schema()->tableExists($table)) {
      $status_text = "Puudub tabel " . $table . ", jätame vahele ".$old_type." sisutüübi väljad ".print_r($fields,1).".";
      $this->messenger->addWarning($status_text);
      $this->logger->warning($status_text);
      return [];
    }
    $query = $this->migrationDatabase->select($table, 'f')->fields('f', $fields)
      ->condition('f.entity_id', $old_nid)
      ->condition('f.entity_type', $entity_type)
      ->condition('f.bundle', $old_type);
    $query->orderBy('f.delta');
    return $query->execute()->fetchAll();
  }

  public function nodeOldTaxonomyFieldData($table, $fields, $join_field, $old_nid, $old_type, $entity_type = 'node' ) {
    if (!$this->migrationDatabase->schema()->tableExists($table)) {
      $status_text = "Puudub tabel " . $table . ", jätame vahele ".$old_type." sisutüübi väljad ".print_r($fields,1).".";
      $this->messenger->addWarning($status_text);
      $this->logger->warning($status_text);
      return [];
    }
    $query = $this->migrationDatabase->select($table, 'f');
    $query->join('taxonomy_term_data', 'ttd', 'f.' . $join_field . ' = ttd.tid');
    $query->leftJoin('i18n_string', 'i', "ttd.tid = i.objectid AND i.type = 'term' AND i.property = 'name'");
    $query->fields('f', $fields)
      ->fields('ttd', ['name', 'weight'])
      ->fields('i', ['lid'])
      ->condition('f.entity_id', $old_nid)
      ->condition('f.entity_type', $entity_type)
      ->condition('f.bundle', $old_type);
    $query->orderBy('f.delta');
    return $query->execute()->fetchAll();
  }

  public function nodeOldFileData($fid) {
    $query = $this->migrationDatabase->select('file_managed', 'f');
    $query->fields('f', ['filename', 'uri', 'status', 'timestamp'])->condition('f.fid', $fid);
    return $query->execute()->fetchAll();
  }

  public function nodeOldFileDataByURI($uri) {
    $query = $this->migrationDatabase->select('file_managed', 'f');
    $query->fields('f', ['fid', 'filename', 'uri', 'status', 'timestamp', 'type'])->condition('f.uri', $uri);
    return $query->execute()->fetchAll();
  }

  public function nodeOldMenuData($path, $menu_name) {
    $query = $this->migrationDatabase->select('menu_links', 'f')->fields('f',  ['mlid', 'plid', 'link_title', 'hidden', 'external', 'weight', 'depth', 'p1', 'p2', 'p3', 'language', 'i18n_tsid'])
      ->condition('f.link_path', $path)
      ->condition('f.router_path', 'node/%')
      ->condition('f.menu_name', $menu_name);
    return $query->execute()->fetchAll();
  }
  public function nodeOldMenuParentData($mlid) {
    $query = $this->migrationDatabase->select('menu_links', 'f')->fields('f',  ['link_title', 'link_path', 'router_path', 'language', 'weight'])
      ->condition('f.mlid', $mlid);
    return $query->execute()->fetchAll();
  }
  public function nodeOldMenuTranslateData($i18n_tsid) {
    $query = $this->migrationDatabase->select('menu_links', 'f')->fields('f',  ['link_title'])
      ->condition('f.i18n_tsid', $i18n_tsid)
      ->condition('f.language', 'et');
    return $query->execute()->fetchAll();
  }

  public function createContactLocationMenuItem() {
    $menu_link_storage = $this->entityTypeManager->getStorage('menu_link_content');
    $menu_items = $menu_link_storage->loadByProperties(['menu_name' => 'main']);
    $config = $this->configFactory->get('harno_settings.settings');
    $langcode = 'et';
    $found = false;
    foreach($menu_items as $item) {
      if($item->get('title')->value == 'Kontakt') {
        $found = true;
        break;
      }
    }
    if (!$found) {
      $menu_link_kontakt = $menu_link_storage->create([
        'title' => 'Kontakt',
        'link' => ['uri' => 'route:<nolink>'],
        'menu_name' => 'main',
        'expanded' => TRUE,
        'weight' => 10,
        'langcode' => $langcode,
      ]);
      $menu_link_kontakt->save();
      $menu_link_kontakt_uuid = $menu_link_kontakt->uuid();
      $node = Node::create([
        'type'        => 'page',
        'title'       => 'Asukohad',
        'langcode'    => $langcode,
      ]);
      $node->save();
      $node_id = $node->id();
      \Drupal::configFactory()->getEditable('harno_settings.settings')
        ->set('location.node', $node_id)
        ->save();

      $menu_link_storage->create([
        'title' => 'Asukohad',
        'link' => ['uri' => 'entity:node/' . $node_id],
        'menu_name' => 'main',
        'parent' => 'menu_link_content:' . $menu_link_kontakt_uuid,
        'expanded' => TRUE,
        'weight' => 0,
        'langcode' => $langcode,
      ])->save();
    }
    $found = false;
    foreach($menu_items as $item) {
      if($item->get('title')->value == 'Kontaktid') {
        $found = true;
        break;
      }
    }
    if (!$found) {
      $menu_link_storage->create([
        'title' => 'Kontaktid',
        'link' => ['uri' => 'internal:/contacts'],
        'menu_name' => 'main',
        'parent' => 'menu_link_content:' . $menu_link_kontakt_uuid,
        'expanded' => TRUE,
        'weight' => 0,
        'langcode' => $langcode,
      ])->save();
    }
    #Loome kohe ära ka ligipääsetavuse sisulehe
    #ET 09.05.22 Ligipääsetavuse leht on juba algses baasis olemas,
    # ei ole vaja seda migratsiooni käigus luua, seetõttu hetkel edasist koodi ei läbi.
    if (1==2) {
      $node = Node::create([
        'type' => 'page',
        'title' => 'Ligipääsetavus',
        'langcode' => $langcode,
      ]);
      $node->body->value = $this->getAccessibilityNodeBody();
      $node->body->format = 'full_html';

      $module_path = $this->fileSystem->realpath(\Drupal::service('module_handler')
        ->getModule('harno_migrate')
        ->getPath());
      $image_path = $module_path . "/images/universal-access-6602642_1280.png";
      $data = file_get_contents($image_path);
      $year_month = date('Y') . '-' . date('m');
      $directory = $this->configFactory->get('system.file')
          ->get('default_scheme') . '://' . $year_month;
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
      $file_repository = \Drupal::service('file.repository');
      $file = $file_repository->writeData($data, $directory . '/universal-access-6602642_1280.png', FileSystemInterface::EXISTS_REPLACE);
      if ($file) {
        $file_id = $file->id();
        $drupal_media = Media::create([
          'bundle' => 'image',
          'langcode' => 'et',
          'uid' => \Drupal::currentUser()->id(),
          'name' => 'Juurdepääsetavuse logo',
          'field_media_image' => [
            'target_id' => $file_id,
            'alt' => 'Sinine inimkujutis musta ringi sees',
            'title' => 'Juurdepääsetavuse logo',
          ],
          'field_catalog' => [
            'target_id' => $this->getTermNewByName('Media Root', 'media_catalogs'),
          ],
        ]);
        if ($drupal_media) {
          $drupal_media->setPublished();
          $drupal_media->save();
          $node->field_one_image[] = ['target_id' => $drupal_media->id()];
        }
      }
      $node->save();
      $node_id = $node->id();
      \Drupal::configFactory()->getEditable('harno_settings.settings')
        ->set('accessibility_statement.node', $node_id)
        ->save();
    }
  }

  public function migrateTerm($old_term, $vid, $debug = FALSE) {
    $old_tid = $old_term->tid;
    $old_type = $old_term->machine_name;
    if(isset($old_term->lid) AND !empty($old_term->lid)) {
      $old_term->translatsions = $this->getStringTranslationOld($old_term->lid);
    }
    if (!$debug) {
      if (isset($old_term->name) and !empty($old_term->name)) {
        $term_create = Term::create([
          'vid' => $vid,
          'name' => $old_term->name,
          'weight' => $old_term->weight
        ]);
        if ($old_term->parent > 0 and isset($old_term->parent_name) and !empty($old_term->parent_name)) {
          $parent_id = $this->getTermNewByName($old_term->parent_name, $vid);
          $term_create->parent = ['target_id' => $parent_id];
        }
        if ($old_type == 'academic_years') {
          $old_term->field_data_academic_year_period = $this->entityOldTextFieldData('field_data_academic_year_period', [
            'academic_year_period_value',
            'academic_year_period_value2'
          ], $old_tid, $old_type, 'taxonomy_term');
          if (isset($old_term->field_data_academic_year_period[0]->academic_year_period_value) and !empty($old_term->field_data_academic_year_period[0]->academic_year_period_value)) {
            $term_create->field_date_range->value = date('Y-m-d', $old_term->field_data_academic_year_period[0]->academic_year_period_value);
            $term_create->field_date_range->end_value = date('Y-m-d', $old_term->field_data_academic_year_period[0]->academic_year_period_value2);
          }
        }
        $term_create->save();
        if (isset($old_term->translatsions) and !empty($old_term->translatsions)) {
          foreach ($old_term->translatsions as $trans) {
            if (!$term_create->hasTranslation($trans->language)) {
              $term_create->addTranslation($trans->language, [
                'name' => $trans->translation,
              ])->save();
            }
          }
        }
        if (isset($term_create) and !empty($term_create)) {
          return $term_create->id();
        }
      }
      else {
        $status_text = 'Term name is missing!' . print_r($old_term,1);
        $this->messenger->addStatus($status_text);
      }
    }
    else {
      $status_text = 'Old term data: ' . print_r($old_term,1);
      $this->messenger->addStatus($status_text);
    }
    return -1;

  }

  public function migrateHourTimesTerm($old_term, $vid, $debug) {
    $old_tid = $old_term->tid;
    $old_type = $old_term->machine_name;
    $term_name = 'Peamaja';

    $term = $this->entityTypeManager->getStorage('taxonomy_term')->loadByProperties(['name' => $term_name, 'vid' => $vid]);
    $term = reset($term);
    if (!$debug) {
      if (isset($term) AND !empty($term)) {
        $term_id = $term->id();
        $term_create = $term;
      } else {
        $term_create = Term::create([
          'vid' => $vid,
          'name' => $term_name,
          'weight' => 0
        ]);
      }
      if($old_term->parent > 0 AND isset($old_term->parent_name) AND !empty($old_term->parent_name)) {
        $parent_id = $this->getTermNewByName($old_term->parent_name, $vid);
        $term_create->parent = ['target_id' => $parent_id];
      }

      $old_term->field_data_field_time_type = $this->entityOldTextFieldData('field_data_field_time_type', ['field_time_type_value'], $old_tid, $old_type, 'taxonomy_term');
      $old_term->field_data_field_hours_times = $this->entityOldTextFieldData('field_data_field_hours_times', ['field_hours_times_value', 'field_hours_times_value2'], $old_tid, $old_type, 'taxonomy_term');
      if(isset($old_term->field_data_field_time_type[0]->field_time_type_value) AND !empty($old_term->field_data_field_time_type[0]->field_time_type_value)) {
        $time = str_replace('0000', '1970', $old_term->field_data_field_hours_times[0]->field_hours_times_value);
        $opening_time = strtotime("$time UTC");
        $time = str_replace('0000', '1970', $old_term->field_data_field_hours_times[0]->field_hours_times_value2);
        $closing_time = strtotime("$time UTC");

        $school_hour_paragraph = Paragraph::create([
          'type' => 'school_hour',
          'field_school_hour_type' => $old_term->field_data_field_time_type[0]->field_time_type_value,
          'field_opening_time' => $opening_time,
          'field_closing_time' => $closing_time,
        ]);
        $school_hour_paragraph->save();

        if (isset($term_id) AND !empty($term_id)) {

          $hours_groups = $term_create->field_school_hours_group->referencedEntities();
          foreach ( $hours_groups as $hours_group ) {
            $hour_days = $hours_group->field_school_hour_day->referencedEntities();
            foreach ( $hour_days as $hour_day ) {
              $hour_day->field_school_hour[] =
                [
                  'target_id' => $school_hour_paragraph->id(),
                  'target_revision_id' => $school_hour_paragraph->getRevisionId(),
                ];
              $hour_day->save();
            }
          }
        }
        else {
          $school_hour_day_paragraph = Paragraph::create([
            'type' => 'school_hour_day',
            'field_school_hour_days' => 'E-R',
          ]);
          $school_hour_day_paragraph->field_school_hour[0] =
          [
            'target_id' => $school_hour_paragraph->id(),
            'target_revision_id' => $school_hour_paragraph->getRevisionId(),
          ];
          $school_hour_day_paragraph->save();

          $school_hours_group_paragraph = Paragraph::create([
            'type' => 'school_hours_group',
            'field_name' => 'Kogu kool',
          ]);

          $school_hours_group_paragraph->field_school_hour_day[0] =
          [
            'target_id' => $school_hour_day_paragraph->id(),
            'target_revision_id' => $school_hour_day_paragraph->getRevisionId(),
          ];
          $school_hours_group_paragraph->save();

          $term_create->field_school_hours_group[0] =
          [
            'target_id' => $school_hours_group_paragraph->id(),
            'target_revision_id' => $school_hours_group_paragraph->getRevisionId(),
          ];
        }
      }
      $term_create->save();
      if (isset($term_create) and !empty($term_create)) {
        return $term_create->id();
      }
    }
    else {
      $status_text = 'Old term data: ' . print_r($old_term,1);
      $this->messenger->addStatus($status_text);
    }
    return -1;

  }

  public function migrateContactLocation($old_node, $count, $debug = FALSE) {
    $old_nid = $old_node->nid;
    $old_type = $old_node->type;
    $old_node->field_data_phone_nr = $this->entityOldTextFieldData('field_data_phone_nr', ['phone_nr_value'], $old_nid, $old_type);
    $old_node->field_data_e_mail = $this->entityOldTextFieldData('field_data_e_mail', ['e_mail_email'], $old_nid, $old_type);
    $old_node->field_data_location_address = $this->entityOldTextFieldData('field_data_location_address', ['location_address_value'], $old_nid, $old_type);
    $old_node->field_data_location_parking = $this->entityOldTextFieldData('field_data_location_parking', ['location_parking_value'], $old_nid, $old_type);
    $old_node->field_data_location_parking_attachment = $this->entityOldTextFieldData('field_data_location_parking_attachment', ['location_parking_attachment_fid '], $old_nid, $old_type);
    $old_node->field_data_location_transport = $this->entityOldTextFieldData('field_data_location_transport', ['location_transport_value'], $old_nid, $old_type);
    $old_node->field_data_field_hitsa_place_tooltip  = $this->entityOldTextFieldData('field_data_field_hitsa_place_tooltip', ['field_hitsa_place_tooltip_value'], $old_nid, $old_type);
    $old_node->field_data_field_hitsa_place_zoom  = $this->entityOldTextFieldData('field_data_field_hitsa_place_zoom', ['field_hitsa_place_zoom_value'], $old_nid, $old_type);
    $old_node->field_data_field_maa_coordinates  = $this->entityOldTextFieldData('field_data_field_maa_coordinates', ['field_maa_coordinates_value'], $old_nid, $old_type);
    $old_node->field_data_field_extra_info_title = $this->entityOldTextFieldData('field_data_field_extra_info_title', ['field_extra_info_title_value'], $old_nid, $old_type);
    $old_node->field_data_field_extra_info = $this->entityOldTextFieldData('field_data_field_extra_info', ['field_extra_info_value'], $old_nid, $old_type);

    $node = FALSE;
    if (!$debug) {
      if (isset($old_node->tnid) and !empty($old_node->tnid) and $old_node->tnid != $old_node->nid) {
        $node = $this->getNodeNewTranslation($old_node);
      }
      if (!$node) {
        $node = Node::create([
          'type' => 'location',
          'title' => $old_node->title,
          'langcode' => $old_node->language,
          'status' => $old_node->status,
          'created' => $old_node->created,
          'changed' => $old_node->changed,
          'uid' => \Drupal::currentUser()->id(),
        ]);
      }
      if(isset($old_node->field_data_phone_nr[0]->phone_nr_value) AND !empty($old_node->field_data_phone_nr[0]->phone_nr_value)) {
        $node->field_phones_3[] = ['value' => $old_node->field_data_phone_nr[0]->phone_nr_value];
      }
      if(isset($old_node->field_data_phone_nr[1]->phone_nr_value) AND !empty($old_node->field_data_phone_nr[1]->phone_nr_value)) {
        $node->field_phones_3[] = ['value' => $old_node->field_data_phone_nr[1]->phone_nr_value];
      }

      if(isset($old_node->field_data_e_mail[0]->e_mail_email) AND !empty($old_node->field_data_e_mail[0]->e_mail_email)) {
        $node->field_email->value = $old_node->field_data_e_mail[0]->e_mail_email;
      }

      if(isset($old_node->field_data_location_address[0]->location_address_value) AND !empty($old_node->field_data_location_address[0]->location_address_value)) {
        $node->field_address->value = $old_node->field_data_location_address[0]->location_address_value;
      }

      if(isset($old_node->field_data_location_parking[0]->location_parking_value) AND !empty($old_node->field_data_location_parking[0]->location_parking_value)) {
        $additional_paragraph = Paragraph::create([
          'type' => 'additional_information',
          'field_title' => 'Parkimine',
          'field_body_text' => [
            'value' => $old_node->field_data_location_parking[0]->location_parking_value,
            'format' => 'full_html',
          ],
        ]);
        $additional_paragraph->save();
        $node->field_additional_information[] =
          [
            'target_id' => $additional_paragraph->id(),
            'target_revision_id' => $additional_paragraph->getRevisionId(),
          ];
      }
      if(isset($old_node->field_data_location_parking_attachment[0]->location_transport_value) AND !empty($old_node->field_data_location_parking_attachment[0]->location_transport_value)) {
        $old_node->field_data_location_parking_attachment[0]->file_managed = $this->nodeOldFileData($old_node->field_data_location_parking_attachment[0]->location_transport_value);
        [
          $media_item_id,
          $new_file_url,
          $old_document_url
        ] = $this->getAndSaveDocument($old_node->field_data_location_parking_attachment[0]);
        if (isset($media_item_id) and !empty($media_item_id)) {
          $file_paragraph = Paragraph::create([
            'type' => 'file'
          ]);
          $file_paragraph->field_file[0] = ['target_id' => $media_item_id];
          $file_paragraph->save();

          $additional_paragraph->field_links_and_files[] =
            [
              'target_id' => $file_paragraph->id(),
              'target_revision_id' => $file_paragraph->getRevisionId(),
            ];
          $additional_paragraph->save();
        }
      }

      if(isset($old_node->field_data_location_transport[0]->location_transport_value) AND !empty($old_node->field_data_location_transport[0]->location_transport_value)) {
        $additional_paragraph = Paragraph::create([
          'type' => 'additional_information',
          'field_title' => 'Transport',
          'field_body_text' => [
            'value' => $old_node->field_data_location_transport[0]->location_transport_value,
            'format' => 'full_html',
          ],
        ]);
        $additional_paragraph->save();
        $node->field_additional_information[] =
          [
            'target_id' => $additional_paragraph->id(),
            'target_revision_id' => $additional_paragraph->getRevisionId(),
          ];
      }
      if(isset($old_node->field_data_field_extra_info_title[0]->field_extra_info_title_value) AND !empty($old_node->field_data_field_extra_info_title[0]->field_extra_info_title_value)) {
        $additional_paragraph = Paragraph::create([
          'type' => 'additional_information',
          'field_title' => $old_node->field_data_field_extra_info_title[0]->field_extra_info_title_value,
          'field_body_text' => [
            'value' => $old_node->field_data_field_extra_info[0]->field_extra_info_value,
            'format' => 'full_html',
          ],
        ]);
        $additional_paragraph->save();
        $node->field_additional_information[] =
          [
            'target_id' => $additional_paragraph->id(),
            'target_revision_id' => $additional_paragraph->getRevisionId(),
          ];
      }
      if(isset($old_node->field_data_field_hitsa_place_tooltip[0]->field_hitsa_place_tooltip_value) AND !empty($old_node->field_data_field_hitsa_place_tooltip[0]->field_hitsa_place_tooltip_value)) {
        $node->field_description->value = $old_node->field_data_field_hitsa_place_tooltip[0]->field_hitsa_place_tooltip_value;
      }

      if(isset($old_node->field_data_field_hitsa_place_zoom[0]->field_hitsa_place_zoom_value) AND !empty($old_node->field_data_field_hitsa_place_zoom[0]->field_hitsa_place_zoom_value)) {
        $node->field_map_scale->value = $old_node->field_data_field_hitsa_place_zoom[0]->field_hitsa_place_zoom_value;
      }
      if(isset($old_node->field_data_field_maa_coordinates[0]->field_maa_coordinates_value) AND !empty($old_node->field_data_field_maa_coordinates[0]->field_maa_coordinates_value)) {
        [$x, $y] = explode(',',$old_node->field_data_field_maa_coordinates[0]->field_maa_coordinates_value );
        $node->field_map_x_coordinate->value = $x;
        $node->field_map_y_coordinate->value = $y;
      }
      $node->save();
      $node_id = $node->id();
      if (isset($node_id) and !empty($node_id)) {
        $menu_link_storage = $this->entityTypeManager->getStorage('menu_link_content');
        $menu_items = $menu_link_storage->loadByProperties(['menu_name' => 'main']);
        foreach($menu_items as $item) {
          if($item->get('title')->value == 'Asukohad') {
            $menu_link_asukohad_uuid = $item->uuid();
            break;
          }
        }
        $menu_link_storage->create([
          'title' => $old_node->title,
          'link' => ['uri' => 'entity:node/' . $node_id],
          'menu_name' => 'main',
          'parent' => 'menu_link_content:' . $menu_link_asukohad_uuid,
          'expanded' => TRUE,
          'weight' => 0,
          'langcode' => $old_node->language,
        ])->save();

        $node->set("path", ["pathauto" => TRUE]);
        $node->save();
        return $node_id;
      }
    }
    else {
      $status_text = 'Old node data: ' . print_r($old_node,1);
      $this->messenger->addStatus($status_text);
    }
    return -1;
  }

  public function migrateGallery($old_node, $count, $debug = FALSE) {
    $old_nid = $old_node->nid;
    $old_type = $old_node->type;
    $old_node->field_data_gallery_author = $this->entityOldTextFieldData('field_data_gallery_author', ['gallery_author_value'], $old_nid, $old_type);
    $old_node->field_data_academic_year = $this->nodeOldTaxonomyFieldData('field_data_academic_year', ['academic_year_tid'], 'academic_year_tid', $old_nid, $old_type);
    $old_node->field_data_gallery_images = $this->entityOldTextFieldData('field_data_gallery_images', ['delta', 'gallery_images_fid'], $old_nid, $old_type);
    $node = FALSE;
    if (!$debug) {
      #Tõlkimine tekitab galerii pildid topelt mõlemas keeles, seega kui on tõlge, me rohkem edasi ei lähegi koodiga.
      if (isset($old_node->tnid) and !empty($old_node->tnid) and $old_node->tnid != $old_node->nid) {
        $node = $this->getNodeNewTranslation($old_node);
        if (isset($node) and !empty($node)) {
          $node->save();
          return $node->id();
        }
      }
      if (!$node) {
        $node = Node::create([
          'type' => 'gallery',
          'title' => $old_node->title,
          'langcode' => $old_node->language,
          'status' => $old_node->status,
          'created' => $old_node->created,
          'changed' => $old_node->changed,
          'uid' => \Drupal::currentUser()->id(),
        ]);
      }
      if(isset($old_node->field_data_gallery_author[0]->gallery_author_value) AND !empty($old_node->field_data_gallery_author[0]->gallery_author_value)) {
        $node->field_description->value = 'Piltide autor: ' . $old_node->field_data_gallery_author[0]->gallery_author_value;
      }
      if(isset($old_node->field_data_academic_year[0]->name) AND !empty($old_node->field_data_academic_year[0]->name)) {
        $node->field_academic_year->target_id = $this->getTermNewByName($old_node->field_data_academic_year[0]->name, 'academic_year');
      }

      foreach($old_node->field_data_gallery_images as $image) {
        $image->file_managed = $this->nodeOldFileData($image->gallery_images_fid);
        $image->field_data_field_file_image_alt_text = $this->entityOldTextFieldData('field_data_field_file_image_alt_text', ['field_file_image_alt_text_value'], $image->gallery_images_fid, 'image', 'file' );
        $image->field_data_field_file_image_title_text = $this->entityOldTextFieldData('field_data_field_file_image_title_text', ['field_file_image_title_text_value'], $image->gallery_images_fid, 'image', 'file' );
        $image->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $image->gallery_images_fid, 'image', 'file');
        #TODO: Kas vajalik?
        $image->field_data_field_image_author = $this->entityOldTextFieldData('field_data_field_image_author', ['field_image_author_value'], $image->gallery_images_fid, 'image', 'file' );
        $image->field_data_field_image_date = $this->entityOldTextFieldData('field_data_field_image_date', ['field_image_date_value'], $image->gallery_images_fid, 'image', 'file' );

        $media_item_id = $this->getAndSaveImage($image);
        if (isset($media_item_id) AND !empty($media_item_id)) {
          $node->field_images[] = ['target_id' => $media_item_id];
        }
      }
      $node->save();
      if (isset($node) and !empty($node)) {
        return $node->id();
      }
    }
    else {
      $status_text = 'Old node data: ' . print_r($old_node,1);
      $this->messenger->addStatus($status_text);
    }
    return -1;
  }

  public function migrateContact($old_node, $count, $debug = FALSE) {
    $old_nid = $old_node->nid;
    $old_type = $old_node->type;
    $old_node->field_data_contact_image = $this->entityOldTextFieldData('field_data_contact_image', ['delta', 'contact_image_fid'], $old_nid, $old_type);
    $old_node->field_data_field_extra_info = $this->entityOldTextFieldData('field_data_field_extra_info', ['field_extra_info_value'], $old_nid, $old_type);
    $old_node->field_data_field_contact_education = $this->entityOldTextFieldData('field_data_field_contact_education', ['field_contact_education_value'], $old_nid, $old_type);
    $old_node->field_data_job_position = $this->nodeOldTaxonomyFieldData('field_data_job_position', ['delta', 'job_position_target_id'], 'job_position_target_id', $old_nid, $old_type);
    $old_node->field_data_phone_nr = $this->entityOldTextFieldData('field_data_phone_nr', ['delta', 'phone_nr_value'], $old_nid, $old_type);
    $old_node->field_data_e_mail = $this->entityOldTextFieldData('field_data_e_mail', ['e_mail_email'], $old_nid, $old_type);
    #@TODO Vaja testida
    $old_node->field_data_contact_cv = $this->entityOldTextFieldData('field_data_contact_cv', ['contact_cv_url', 'contact_cv_title', 'contact_cv_attributes'], $old_nid, $old_type);
    #@TODO Vaja testida
    $old_node->field_data_contact_cv_attachment = $this->entityOldTextFieldData('field_data_contact_cv_attachment', ['contact_cv_attachment_fid'], $old_nid, $old_type);
    $old_node->field_data_reception_times = $this->entityOldTextFieldData('field_data_reception_times', ['delta', 'reception_times_value'], $old_nid, $old_type);
    $old_node->field_data_contact_departments = $this->entityOldTextFieldData('field_data_contact_departments', ['delta', 'contact_departments_value'], $old_nid, $old_type);
    foreach ($old_node->field_data_contact_departments as $department) {
      $department->field_data_job_department = $this->nodeOldTaxonomyFieldData('field_data_job_department', ['job_department_target_id'], 'job_department_target_id', $department->contact_departments_value, 'contacts_department', 'paragraphs_item');
      $department->field_data_department_weight = $this->entityOldTextFieldData('field_data_department_weight', ['department_weight_value'], $department->contact_departments_value, 'contacts_department', 'paragraphs_item');
    }
    $node = FALSE;
    if (!$debug) {
      if (isset($old_node->tnid) and !empty($old_node->tnid) and $old_node->tnid != $old_node->nid) {
        #kontaktid on ainult eesti keelsed
        return -1;
      }
      if (!$node) {
        $node = Node::create([
          'type' => 'worker',
          'title' => $old_node->title,
          'langcode' => $old_node->language,
          'status' => $old_node->status,
          'created' => $old_node->created,
          'changed' => $old_node->changed,
          'uid' => \Drupal::currentUser()->id(),
        ]);
      }
      if (isset($old_node->field_data_job_position[0]->name) and !empty($old_node->field_data_job_position[0]->name)) {
        foreach ($old_node->field_data_job_position as $job_position) {
          if (isset($job_position->name) and !empty($job_position->name)) {
            $node->field_position[] = ['target_id' => $this->getTermNewByName($job_position->name, 'positions')];
          }
        }
      }
      if (isset($old_node->field_data_e_mail[0]->e_mail_email) and !empty($old_node->field_data_e_mail[0]->e_mail_email)) {
        $node->field_email->value = $old_node->field_data_e_mail[0]->e_mail_email;
      }
      if (isset($old_node->field_data_contact_cv[0]->contact_cv_url) and !empty($old_node->field_data_contact_cv[0]->contact_cv_url)) {
        $node->field_link->url = $old_node->field_data_contact_cv[0]->contact_cv_url;
        $node->field_link->title = $old_node->field_data_contact_cv[0]->contact_cv_title;
        #@TODO contact_cv_attributes ?
      }
      if (isset($old_node->field_data_contact_cv_attachment[0]->contact_cv_attachment_fid) and !empty($old_node->field_data_contact_cv_attachment[0]->contact_cv_attachment_fid)) {
        $old_node->field_data_contact_cv_attachment[0]->file_managed = $this->nodeOldFileData($old_node->field_data_contact_cv_attachment[0]->contact_cv_attachment_fid);
        $new_fid = $this->getAndSaveManagedDocument($old_node->field_data_contact_cv_attachment[0]->file_managed->uri, $old_node->field_data_contact_cv_attachment[0]->file_managed->filename,'cv');
        if (isset($new_fid) AND !empty($new_fid)) {
          $node->field_cv->target_id = $new_fid;
          #@TODO file usage tuleks lisada?
        }
      }
      if (isset($old_node->field_data_field_contact_education[0]->field_contact_education_value) and !empty($old_node->field_data_field_contact_education[0]->field_contact_education_value)) {
        $node->field_education->value = $old_node->field_data_field_contact_education[0]->field_contact_education_value;
      }
      if (isset($old_node->field_data_field_extra_info[0]->field_extra_info_value) and !empty($old_node->field_data_field_extra_info[0]->field_extra_info_value)) {
        $node->body->value = $old_node->field_data_field_extra_info[0]->field_extra_info_value;
        $node->body->format = 'full_html';
      }

      if (isset($old_node->field_data_contact_departments[0]->field_data_job_department[0]->name) and !empty($old_node->field_data_contact_departments[0]->field_data_job_department[0]->name)) {
        foreach ($old_node->field_data_contact_departments as $department) {
          if (isset($department->field_data_job_department[0]->name) and !empty($department->field_data_job_department[0]->name)) {
            if (isset($department->field_data_department_weight[0]->department_weight_value) and !empty($department->field_data_department_weight[0]->department_weight_value)) {

            }
            else {
              $department->field_data_department_weight[0] = new \stdClass;
              $department->field_data_department_weight[0]->department_weight_value = 1;
            }
            $additional_paragraph = Paragraph::create([
              'type' => 'department',
              'field_department' => $this->getTermNewByName($department->field_data_job_department[0]->name, 'departments'),
              'field_weight' => $department->field_data_department_weight[0]->department_weight_value,
            ]);
            $additional_paragraph->save();
            $node->field_department[$department->delta] =
              [
                'target_id' => $additional_paragraph->id(),
                'target_revision_id' => $additional_paragraph->getRevisionId(),
              ];
          }
        }
      }
      foreach ($old_node->field_data_contact_image as $image) {
        $image->file_managed = $this->nodeOldFileData($image->contact_image_fid);
        $image->field_data_field_file_image_alt_text = $this->entityOldTextFieldData('field_data_field_file_image_alt_text', ['field_file_image_alt_text_value'], $image->contact_image_fid, 'image', 'file');
        $image->field_data_field_file_image_title_text = $this->entityOldTextFieldData('field_data_field_file_image_title_text', ['field_file_image_title_text_value'], $image->contact_image_fid, 'image', 'file');
        $image->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $image->contact_image_fid, 'image', 'file');

        $media_item_id = $this->getAndSaveImage($image);
        if (isset($media_item_id) and !empty($media_item_id)) {
          $node->field_one_image[] = ['target_id' => $media_item_id];
        }
      }
      if (isset($old_node->field_data_phone_nr[0]->phone_nr_value) and !empty($old_node->field_data_phone_nr[0]->phone_nr_value)) {
        foreach ($old_node->field_data_phone_nr as $phone_nr) {
          $node->field_phone[] = ['value' => $phone_nr->phone_nr_value];
        }
      }
      if (isset($old_node->field_data_reception_times[0]->reception_times_value) and !empty($old_node->field_data_reception_times[0]->reception_times_value)) {
        foreach ($old_node->field_data_reception_times as $reception_time) {
          $node->field_consultation_hours[] = ['value' => $reception_time->reception_times_value];
        }
      }
      $node->save();

      if (isset($node) and !empty($node)) {
        return $node->id();
      }
    }
    else {
      $status_text = 'Old node data: ' . print_r($old_node,1);
      $this->messenger->addStatus($status_text);
    }
    return -1;
  }

  public function migrateAlumnus($old_node, $count, $debug = FALSE) {
    $old_nid = $old_node->nid;
    $old_type = $old_node->type;
    $old_node->field_data_field_order_number = $this->entityOldTextFieldData('field_data_field_order_number', ['field_order_number_value'], $old_nid, $old_type);
    $old_node->field_data_field_person = $this->entityOldTextFieldData('field_data_field_person', ['delta', 'field_person_value'], $old_nid, $old_type);
    foreach ($old_node->field_data_field_person as $person) {
      $person->field_data_field_full_name = $this->entityOldTextFieldData('field_data_field_full_name', ['field_full_name_value'], $person->field_person_value, 'alumnus_person', 'paragraphs_item');
      $person->field_data_field_extra_information = $this->entityOldTextFieldData('field_data_field_extra_information', ['field_extra_information_value'], $person->field_person_value, 'alumnus_person', 'paragraphs_item');
    }
    $node = FALSE;
    if (!$debug) {
      if (isset($old_node->tnid) and !empty($old_node->tnid) and $old_node->tnid != $old_node->nid) {
        $node = $this->getNodeNewTranslation($old_node);
      }
      if (!$node) {
        $node = Node::create([
          'type' => 'class',
          'title' => $old_node->title,
          'langcode' => $old_node->language,
          'status' => $old_node->status,
          'created' => $old_node->created,
          'changed' => $old_node->changed,
          'uid' => \Drupal::currentUser()->id(),
        ]);
      }
      if (isset($old_node->field_data_field_person[0]->field_data_field_full_name[0]->field_full_name_value) and !empty($old_node->field_data_field_person[0]->field_data_field_full_name[0]->field_full_name_value)) {
        $body = '<ol>';
        foreach ($old_node->field_data_field_person as $person) {
          $body .= '<li>';
          if (isset($person->field_data_field_full_name[0]->field_full_name_value) and !empty($person->field_data_field_full_name[0]->field_full_name_value)) {
            $body .=  $person->field_data_field_full_name[0]->field_full_name_value;
          }
          if (isset($person->field_data_field_extra_information[0]->field_extra_information_value) and !empty($person->field_data_field_extra_information[0]->field_extra_information_value)) {
            $body .= ' - ' . $person->field_data_field_extra_information[0]->field_extra_information_value;
          }
          $body .= '</li>';
        }
        $body .= '</ol>';
        $additional_paragraph = Paragraph::create([
          'type' => 'class',
          'field_title' => 'Klass',
          'field_body' => [
            'value' => $body,
            'format' => 'full_html',
          ],
        ]);
        $additional_paragraph->save();
        $node->field_class[0] =
          [
            'target_id' => $additional_paragraph->id(),
            'target_revision_id' => $additional_paragraph->getRevisionId(),
          ];
      }
      if (isset($old_node->field_data_field_order_number[0]->field_order_number_value) and !empty($old_node->field_data_field_order_number[0]->field_order_number_value)) {
        $node->field_weight->value = $old_node->field_data_field_order_number[0]->field_order_number_value;
      } else {
        $node->field_weight->value = $count;
      }
      $node->save();
      if (isset($node) and !empty($node)) {
        return $node->id();
      }
    }
    else {
      $status_text = 'Old node data: ' . print_r($old_node,1);
      $this->messenger->addStatus($status_text);
    }
    return -1;
  }

  public function migratePage($old_node, $count, $debug = FALSE ) {
    $specialitiy_table = '';
    $curriculum_type = 0;
    $old_nid = $old_node->nid;
    $old_type = $old_node->type;
    $old_node->field_data_body = $this->entityOldTextFieldData('field_data_body', ['body_value', 'body_summary'], $old_nid, $old_type);
    $old_node->main_menu = $this->nodeOldMenuData('node/' . $old_nid, 'hitsa-main-menu');
    if ($old_type == 'content_page') {
      $old_node->field_data_cp_type = $this->entityOldTextFieldData('field_data_cp_type', ['cp_type_value'], $old_nid, $old_type);
      $old_node->field_data_cp_service_type = $this->entityOldTextFieldData('field_data_cp_service_type', ['cp_service_type_value'], $old_nid, $old_type);
      $old_node->field_data_field_school_selections = $this->entityOldTextFieldData('field_data_field_school_selections', ['field_school_selections_value'], $old_nid, $old_type);
      $old_node->field_data_cp_display_style = $this->entityOldTextFieldData('field_data_cp_display_style', ['cp_display_style_value'], $old_nid, $old_type);
      $old_node->field_data_article_video = $this->entityOldTextFieldData('field_data_article_video', ['article_video_fid'], $old_nid, $old_type);
      $old_node->field_data_cp_image = $this->entityOldTextFieldData('field_data_cp_image', ['cp_image_fid'], $old_nid, $old_type);
      #@TODO NOT IN USE?
      $old_node->field_data_cp_contacts = $this->entityOldTextFieldData('field_data_cp_contacts', ['cp_contacts_target_id'], $old_nid, $old_type);
      $old_node->field_data_field_attachments = $this->entityOldTextFieldData('field_data_field_attachments', ['field_attachments_fid', 'field_attachments_display'], $old_nid, $old_type);
    }
    elseif ($old_type == 'curriculum') {
      $old_node->field_data_field_school_selections = $this->entityOldTextFieldData('field_data_field_school_selections', ['field_school_selections_value'], $old_nid, $old_type);
      $old_node->field_data_field_field = $this->nodeOldTaxonomyFieldData('field_data_field_field', ['field_field_target_id'], 'field_field_target_id', $old_nid, $old_type);

      if (isset($old_node->field_data_field_field[0]->field_field_target_id) AND !empty($old_node->field_data_field_field[0]->field_field_target_id)) {
        if(isset($old_node->field_data_field_field[0]->lid) AND !empty($old_node->field_data_field_field[0]->lid)) {
          $old_node->field_data_field_field[0]->translatsions = $this->getStringTranslationOld($old_node->field_data_field_field[0]->lid);
        }
        $old_node->field_data_field_field[0]->field_data_field_extra_name = $this->entityOldTextFieldData('field_data_field_extra_name', ['field_extra_name_value'], $old_node->field_data_field_field[0]->field_field_target_id, 'fields', 'taxonomy_term');
      }
      $old_node->field_data_field_curriculum_length = $this->entityOldTextFieldData('field_data_field_curriculum_length', ['field_curriculum_length_value', 'field_curriculum_length_value2'], $old_nid, $old_type);
      $old_node->field_data_field_curriculum_start = $this->entityOldTextFieldData('field_data_field_curriculum_start', ['field_curriculum_start_value'], $old_nid, $old_type);
      $old_node->field_data_field_days_place = $this->entityOldTextFieldData('field_data_field_days_place', ['field_days_place_value'], $old_nid, $old_type);
      $old_node->field_data_field_instructor = $this->entityOldTextFieldData('field_data_field_instructor', ['field_instructor_value'], $old_nid, $old_type);
      $old_node->field_data_field_room = $this->entityOldTextFieldData('field_data_field_room', ['field_room_value'], $old_nid, $old_type);
      $old_node->field_data_field_target_group = $this->entityOldTextFieldData('field_data_field_target_group', ['field_target_group_value'], $old_nid, $old_type);
      $old_node->field_data_field_premise = $this->entityOldTextFieldData('field_data_field_premise', ['field_premise_value'], $old_nid, $old_type);
      $old_node->field_data_field_short_description = $this->entityOldTextFieldData('field_data_field_short_description', ['field_short_description_value'], $old_nid, $old_type);
      $old_node->field_data_field_table_description = $this->entityOldTextFieldData('field_data_field_table_description', ['field_table_description_value'], $old_nid, $old_type);
      #@TODO $field_data_field_subjects_table What to do with that?
      $field_data_field_subjects_table = $this->entityOldTextFieldData('field_data_field_subjects_table', ['field_subjects_table_value'], $old_nid, $old_type);
      $field_data_field_specialitiy_table = $this->entityOldTextFieldData('field_data_field_specialitiy_table', ['field_specialitiy_table_value'], $old_nid, $old_type);
      if (isset($field_data_field_specialitiy_table[0]->field_specialitiy_table_value) AND !empty($field_data_field_specialitiy_table[0]->field_specialitiy_table_value)) {
        $old_node->unserialize_specialitiy_table = unserialize($field_data_field_specialitiy_table[0]->field_specialitiy_table_value);
        if (isset($old_node->unserialize_specialitiy_table['tabledata']['row_1']['col_0']) and !empty($old_node->unserialize_specialitiy_table['tabledata']['row_1']['col_0'])) {
          if (isset($old_node->field_data_field_short_description[0]->field_short_description_value) AND !empty($old_node->field_data_field_short_description[0]->field_short_description_value)) {
            $specialitiy_table .= '<h4>'.$old_node->field_data_field_short_description[0]->field_short_description_value.'</h4>';
          }
          $specialitiy_table .= '<table>';
          if (isset($old_node->field_data_field_table_description[0]->field_table_description_value) AND !empty($old_node->field_data_field_table_description[0]->field_table_description_value)) {
            $specialitiy_table .= '<caption>'.$old_node->field_data_field_table_description[0]->field_table_description_value.'</caption>';
          }

          foreach ($old_node->unserialize_specialitiy_table['tabledata'] as $row_key => $row) {
            if ($row_key == 'row_0') {
              $specialitiy_table .= '<thead>';
              foreach ($row as $key => $header_col) {
                if ($key != 'weight') {
                  $specialitiy_table .= '<th>' . $header_col . '</th>';
                }
              }
              $specialitiy_table .= '</thead><tbody>';
            }
            else {
              $specialitiy_table .= '<tr>';
              foreach ($row as $key => $body_col) {
                if ($key != 'weight') {
                  $specialitiy_table .= '<td>' . $body_col . '</td>';
                }
              }
              $specialitiy_table .= '</tr>';
            }
          }
          $specialitiy_table .= '</tbody></table>';
          $old_node->specialitiy_table = $specialitiy_table;
        }
      }
      $old_node->field_data_field_table_descriptions = $this->entityOldTextFieldData('field_data_field_table_descriptions', ['field_table_descriptions_value'], $old_nid, $old_type);
      $old_node->field_data_field_jobs_pretext = $this->entityOldTextFieldData('field_data_field_jobs_pretext', ['field_jobs_pretext_value'], $old_nid, $old_type);
      #@TODO NOT IN use?
      $old_node->field_data_field_jobs = $this->entityOldTextFieldData('field_data_field_jobs', ['field_jobs_value'], $old_nid, $old_type);
      $old_node->field_data_field_rating = $this->entityOldTextFieldData('field_data_field_rating', ['field_rating_value'], $old_nid, $old_type);
      $old_node->field_data_field_textbooks = $this->entityOldTextFieldData('field_data_field_textbooks', ['field_textbooks_url','field_textbooks_title', 'field_textbooks_attributes'], $old_nid, $old_type);
      $old_node->field_data_field_assessment = $this->entityOldTextFieldData('field_data_field_assessment', ['field_assessment_value'], $old_nid, $old_type);
      $old_node->field_data_field_logo_area = $this->entityOldTextFieldData('field_data_field_logo_area', ['field_logo_area_fid'], $old_nid, $old_type);
      $old_node->field_data_field_pictures = $this->entityOldTextFieldData('field_data_field_pictures', ['field_pictures_fid'], $old_nid, $old_type);
      $old_node->field_data_field_attachments = $this->entityOldTextFieldData('field_data_field_attachments', ['field_attachments_fid', 'field_attachments_display'], $old_nid, $old_type);
      $old_node->field_data_field_tab_weight = $this->entityOldTextFieldData('field_data_field_tab_weight', ['field_tab_weight_value'], $old_nid, $old_type);
    }
    $old_node->field_data_field_contacts = $this->entityOldTextFieldData('field_data_field_contacts', ['field_contacts_value'], $old_nid, $old_type);
    foreach ($old_node->field_data_field_contacts as $contact) {
      $contact->field_data_field_contact_title = $this->entityOldTextFieldData('field_data_field_contact_title', ['field_contact_title_value'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_website = $this->entityOldTextFieldData('field_data_field_website', ['field_website_url', 'field_website_title'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_contact_email = $this->entityOldTextFieldData('field_data_field_contact_email', ['field_contact_email_email'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_phone_number = $this->entityOldTextFieldData('field_data_field_phone_number', ['field_phone_number_value'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_address = $this->entityOldTextFieldData('field_data_field_address', ['field_address_value'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
    }
    if (isset($old_node->field_data_cp_type[0]->cp_type_value) and !empty($old_node->field_data_cp_type[0]->cp_type_value)) {
      #Konsultatsiooniajad tüüpi sisulehte ei migreeri
      if ($old_node->field_data_cp_type[0]->cp_type_value == 'cp_contacts') {
        return 0;
      }
    }
    if (isset($old_node->field_data_field_school_selections[0]->field_school_selections_value) and !empty($old_node->field_data_field_school_selections[0]->field_school_selections_value)) {
      #Toitlustamine tüüpi sisulehte ei migreeri
      if ($old_node->field_data_field_school_selections[0]->field_school_selections_value == 'catering') {
        return 0;
      }
      if($old_type == 'curriculum'){
        switch ($old_node->field_data_field_school_selections[0]->field_school_selections_value) {
          case 'subject-fields':
          case 'specialities':
            $curriculum_type = 1;
            break;
          case 'elective-subjects':
            $curriculum_type = 2;
            break;
          case 'accomodations':
            $curriculum_type = 3;
            break;
        }
      }
    }
    if($old_type == 'curriculum') {
      if (!is_array($old_node->field_data_field_pictures)) {
        $old_node->field_data_field_pictures = [];
      }
    } else {
      if (!is_array($old_node->field_data_cp_image)) {
        $old_node->field_data_cp_image = [];
      }
    }
    if (!is_array($old_node->field_data_field_attachments)) {
      $old_node->field_data_field_attachments = [];
    }
    if(isset($old_node->field_data_body[0]->body_summary) AND !empty($old_node->field_data_body[0]->body_summary)) {
      $old_node->field_data_field_attachments = array_merge($old_node->field_data_field_attachments, $this->searchDocsFromHtml($old_node->field_data_body[0]->body_summary));
      if($old_type == 'curriculum'){
        $old_node->field_data_field_pictures = array_merge($old_node->field_data_field_pictures, $this->searchImagesFromHtml($old_node->field_data_body[0]->body_summary, 'field_pictures_fid'));
      }
      else {
        $old_node->field_data_cp_image = array_merge($old_node->field_data_cp_image, $this->searchImagesFromHtml($old_node->field_data_body[0]->body_summary));
      }
    }
    if(isset($old_node->field_data_body[0]->body_value) AND !empty($old_node->field_data_body[0]->body_value)) {
      $old_node->field_data_field_attachments = array_merge($old_node->field_data_field_attachments, $this->searchDocsFromHtml($old_node->field_data_body[0]->body_value));
      if($old_type == 'curriculum'){
        $old_node->field_data_field_pictures = array_merge($old_node->field_data_field_pictures, $this->searchImagesFromHtml($old_node->field_data_body[0]->body_value, 'field_pictures_fid'));
      }
      else {
        $old_node->field_data_cp_image = array_merge($old_node->field_data_cp_image, $this->searchImagesFromHtml($old_node->field_data_body[0]->body_value));
      }
    }

    if (isset($old_node->field_data_field_subjects_table[0]->field_subjects_table_value) AND !empty($old_node->field_data_field_subjects_table[0]->field_subjects_table_value)) {
      $old_node->unserialize_subjects_table = unserialize($old_node->field_data_field_subjects_table[0]->field_subjects_table_value);
    }

    $node = $translation_node = FALSE;
    if (!$debug) {
      if (isset($old_node->tnid) and !empty($old_node->tnid) and $old_node->tnid != $old_node->nid) {
        $node = $this->getNodeNewTranslation($old_node);
        $translation_node = TRUE;
      }
      if (!$node) {
        $node = Node::create([
          'type' => 'page',
          'title' => $old_node->title,
          'langcode' => $old_node->language,
          'status' => $old_node->status,
          'created' => $old_node->created,
          'changed' => $old_node->changed,
          'uid' => \Drupal::currentUser()->id(),
        ]);
      }
      $body = '';

      if (isset($old_node->field_data_field_contacts[0]->field_contacts_value) and !empty($old_node->field_data_field_contacts[0]->field_contacts_value)) {
        $contact_block_paragraph = Paragraph::create([
          'type' => 'contact_block',
        ]);
        $contact_block_paragraph->save();

        $db_50_50_paragraph = Paragraph::create([
          'type' => 'db_50_50',
        ]);
        $db_50_50_paragraph->field_content_blocks_50_1[] =
          [
            'target_id' => $contact_block_paragraph->id(),
            'target_revision_id' => $contact_block_paragraph->getRevisionId(),
          ];
        $db_50_50_paragraph->save();
        $node->field_distribution_blocks[] =
          [
            'target_id' => $db_50_50_paragraph->id(),
            'target_revision_id' => $db_50_50_paragraph->getRevisionId(),
          ];

        foreach ($old_node->field_data_field_contacts as $contact) {
          $contact_block_paragraph->field_contact_title = 'Kontakt';
          if (isset($contact->field_data_field_contact_title[0]->field_contact_title_value) and !empty($contact->field_data_field_contact_title[0]->field_contact_title_value)) {
            $contact_block_paragraph->field_name = $contact->field_data_field_contact_title[0]->field_contact_title_value;
          }
          if (isset($contact->field_data_field_phone_number[0]->field_phone_number_value) and !empty($contact->field_data_field_phone_number[0]->field_phone_number_value)) {
            $contact_block_paragraph->field_phone = $contact->field_data_field_phone_number[0]->field_phone_number_value;
          }
          if (isset($contact->field_data_field_contact_email[0]->field_contact_email_email) and !empty($contact->field_data_field_contact_email[0]->field_contact_email_email)) {
            $contact_block_paragraph->field_email = $contact->field_data_field_contact_email[0]->field_contact_email_email;
          }
          if (isset($contact->field_data_field_website[0]->field_website_url) and !empty($contact->field_data_field_website[0]->field_website_url)) {
            if(UrlHelper::isValid($contact->field_data_field_website[0]->field_website_url, TRUE)) {
              $contact_block_paragraph->field_link[0] =
                [
                  'uri' => $contact->field_data_field_website[0]->field_website_url,
                  'title' => $contact->field_data_field_website[0]->field_website_title
                ];
            } else {
              $status_text = 'Väline link ei ole korrektne: ' . $contact->field_data_field_website[0]->field_website_url;
              $this->messenger->addStatus($status_text);
              $this->logger->info($status_text);
            }
          }
          if (isset($contact->field_data_field_address[0]->field_address_value) and !empty($contact->field_data_field_address[0]->field_address_value)) {
            $contact_block_paragraph->field_address = $contact->field_data_field_address[0]->field_address_value;
          }
          $contact_block_paragraph->save();
        }
      }

      if ($curriculum_type == 2) {
        if (isset($old_node->field_data_field_curriculum_length[0]->field_curriculum_length_value) and !empty($old_node->field_data_field_curriculum_length[0]->field_curriculum_length_value)) {
          $body .= '<p><strong>Kestus:</strong> ' . date('d.m.Y', $old_node->field_data_field_curriculum_length[0]->field_curriculum_length_value);
          if (isset($old_node->field_data_field_curriculum_length[0]->field_curriculum_length_value2) and !empty($old_node->field_data_field_curriculum_length[0]->field_curriculum_length_value2)) {
            $body .= ' - ' . date('d.m.Y', $old_node->field_data_field_curriculum_length[0]->field_curriculum_length_value2) . '</p>';
          }
          else {
            $body .= '</p>';
          }
        }

        if (isset($old_node->field_data_field_curriculum_start[0]->field_curriculum_start_value) and !empty($old_node->field_data_field_curriculum_start[0]->field_curriculum_start_value)) {
          $body .= '<p><strong>Algusaeg:</strong> ' . date('d.m.Y H:i', $old_node->field_data_field_curriculum_start[0]->field_curriculum_start_value) . '</p>';
        }

        if (isset($old_node->field_data_field_days_place[0]->field_days_place_value) and !empty($old_node->field_data_field_days_place[0]->field_days_place_value)) {
          $body .= '<p><strong>Toimumispäevad:</strong> ' . $old_node->field_data_field_days_place[0]->field_days_place_value . '</p>';
        }

        if (isset($old_node->field_data_field_instructor[0]->field_instructor_value) and !empty($old_node->field_data_field_instructor[0]->field_instructor_value)) {
          $body .= '<p><strong>Koolitaja:</strong> ' . $old_node->field_data_field_instructor[0]->field_instructor_value . '</p>';
        }

        if (isset($old_node->field_data_field_room[0]->field_room_value) and !empty($old_node->field_data_field_room[0]->field_room_value)) {
          $body .= '<p><strong>Ruum:</strong> ' . $old_node->field_data_field_room[0]->field_room_value . '</p>';
        }

        if (isset($old_node->field_data_field_target_group[0]->field_target_group_value) and !empty($old_node->field_data_field_target_group[0]->field_target_group_value)) {
          $body .= '<p><strong>Sihtgrupp:</strong> ' . $old_node->field_data_field_target_group[0]->field_target_group_value . '</p>';
        }

        if (isset($old_node->field_data_field_premise[0]->field_premise_value) and !empty($old_node->field_data_field_premise[0]->field_premise_value)) {
          $body .= '<p><strong>Eeldus:</strong> ' . $old_node->field_data_field_premise[0]->field_premise_value . '</p>';
        }

        if (isset($old_node->field_data_field_rating[0]->field_target_group_value) and !empty($old_node->field_data_field_rating[0]->field_target_group_value)) {
          $body .= '<p><strong>Hinde kujunemine:</strong> ' . $old_node->field_data_field_rating[0]->field_target_group_value . '</p>';
        }

        if (isset($old_node->field_data_field_assessment[0]->field_assessment_value) and !empty($old_node->field_data_field_assessment[0]->field_assessment_value)) {
          $body .= '<p><strong>Hindamine:</strong> ' . $old_node->field_data_field_assessment[0]->field_assessment_value . '</p>';
        }
        if (isset($old_node->field_data_field_textbooks[0]->field_textbooks_url) and !empty($old_node->field_data_field_textbooks[0]->field_textbooks_url)) {
          $link_and_file_block_paragraph = Paragraph::create([
            'type' => 'link_and_file_block',
          ]);
          $link_and_file_block_paragraph->save();
          $db_50_50_paragraph = Paragraph::create([
            'type' => 'db_50_50',
          ]);
          $db_50_50_paragraph->field_content_blocks_50_1[] =
            [
              'target_id' => $link_and_file_block_paragraph->id(),
              'target_revision_id' => $link_and_file_block_paragraph->getRevisionId(),
            ];
          $db_50_50_paragraph->save();
          $node->field_distribution_blocks[] =
            [
              'target_id' => $db_50_50_paragraph->id(),
              'target_revision_id' => $db_50_50_paragraph->getRevisionId(),
            ];
          foreach ($old_node->field_data_field_textbooks as $textbook) {
            if(UrlHelper::isValid($textbook->field_textbooks_url, TRUE)) {
              $link_paragraph = Paragraph::create([
                'type' => 'link'
              ]);
              $link_paragraph->field_link[0] =
                [
                  'uri' => $textbook->field_textbooks_url,
                  'title' => $textbook->field_textbooks_title
                ];
              $link_paragraph->save();

              $link_and_file_block_paragraph->field_links_and_files[] =
                [
                  'target_id' => $link_paragraph->id(),
                  'target_revision_id' => $link_paragraph->getRevisionId(),
                ];
              $link_and_file_block_paragraph->save();
            }
            else {
              $status_text = 'Väline link ei ole korrektne: ' . $textbook->field_textbooks_url;
              $this->messenger->addStatus($status_text);
              $this->logger->info($status_text);
            }
          }
        }
        if (isset($old_node->field_data_body[0]->body_value) and !empty($old_node->field_data_body[0]->body_value)) {
          $body .= '<p><strong>Lühikirjeldus:</strong></p>' .$old_node->field_data_body[0]->body_value;
        }
      } else {
        #$curriculum_type == 2
        if (isset($old_node->field_data_body[0]->body_summary) and !empty($old_node->field_data_body[0]->body_summary)) {
          $body .= str_replace('<p>', '<p class="large">', $old_node->field_data_body[0]->body_summary);
        }
        #erialade tabel
        if (isset($specialitiy_table) and !empty($specialitiy_table)) {
          $body .= $specialitiy_table;
        }
        if (isset($old_node->field_data_body[0]->body_value) and !empty($old_node->field_data_body[0]->body_value)) {
          $body .= $old_node->field_data_body[0]->body_value;
        }
        if (isset($old_node->field_data_field_jobs_pretext[0]->field_jobs_pretext_value) and !empty($old_node->field_data_field_jobs_pretext[0]->field_jobs_pretext_value)) {
          $body .= '<h4> Töökohad tulevikus </h4>' . $old_node->field_data_field_jobs_pretext[0]->field_jobs_pretext_value;
        }
      }
      if (!$translation_node AND isset($old_node->field_data_cp_image[0]->cp_image_fid) and !empty($old_node->field_data_cp_image[0]->cp_image_fid)) {
        $i = 1;
        foreach ($old_node->field_data_cp_image as $image) {
          $image->file_managed = $this->nodeOldFileData($image->cp_image_fid);
          $image->field_data_field_file_image_alt_text = $this->entityOldTextFieldData('field_data_field_file_image_alt_text', ['field_file_image_alt_text_value'], $image->cp_image_fid, 'image', 'file');
          $image->field_data_field_file_image_title_text = $this->entityOldTextFieldData('field_data_field_file_image_title_text', ['field_file_image_title_text_value'], $image->cp_image_fid, 'image', 'file');
          $image->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $image->cp_image_fid, 'image', 'file');

          $media_item_id = $this->getAndSaveImage($image);
          if (isset($media_item_id) and !empty($media_item_id)) {
            if ($i == 1) {
              $node->field_one_image[] = ['target_id' => $media_item_id];
            }
            elseif ($i == 2) {
              $gallery_block_paragraph = Paragraph::create([
                'type' => 'gallery_block',
              ]);
              $gallery_block_paragraph->field_images[] = ['target_id' => $media_item_id];
              $gallery_block_paragraph->save();

              $db_50_50_paragraph = Paragraph::create([
                'type' => 'db_50_50',
              ]);
              $db_50_50_paragraph->field_content_blocks_50_1[] =
                [
                  'target_id' => $gallery_block_paragraph->id(),
                  'target_revision_id' => $gallery_block_paragraph->getRevisionId(),
                ];
              $db_50_50_paragraph->save();
              $node->field_distribution_blocks[] =
                [
                  'target_id' => $db_50_50_paragraph->id(),
                  'target_revision_id' => $db_50_50_paragraph->getRevisionId(),
                ];
            }
            else {
              $gallery_block_paragraph->field_images[] = ['target_id' => $media_item_id];
              $gallery_block_paragraph->save();
            }
            $i++;
          }
        }
      }
      if (!$translation_node AND isset($old_node->field_data_field_pictures[0]->field_pictures_fid) and !empty($old_node->field_data_field_pictures[0]->field_pictures_fid)) {
        $i = 1;
        foreach ($old_node->field_data_field_pictures as $image) {
          $image->file_managed = $this->nodeOldFileData($image->field_pictures_fid);
          $image->field_data_field_file_image_alt_text = $this->entityOldTextFieldData('field_data_field_file_image_alt_text', ['field_file_image_alt_text_value'], $image->field_pictures_fid, 'image', 'file');
          $image->field_data_field_file_image_title_text = $this->entityOldTextFieldData('field_data_field_file_image_title_text', ['field_file_image_title_text_value'], $image->field_pictures_fid, 'image', 'file');
          $image->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $image->field_pictures_fid, 'image', 'file');

          $media_item_id = $this->getAndSaveImage($image);
          if (isset($media_item_id) and !empty($media_item_id)) {
            if ($i == 1) {
              $gallery_block_paragraph = Paragraph::create([
                'type' => 'gallery_block',
              ]);
              $gallery_block_paragraph->field_images[] = ['target_id' => $media_item_id];
              $gallery_block_paragraph->save();

              $db_50_50_paragraph = Paragraph::create([
                'type' => 'db_50_50',
              ]);
              $db_50_50_paragraph->field_content_blocks_50_1[] =
                [
                  'target_id' => $gallery_block_paragraph->id(),
                  'target_revision_id' => $gallery_block_paragraph->getRevisionId(),
                ];
              $db_50_50_paragraph->save();
              $node->field_distribution_blocks[] =
                [
                  'target_id' => $db_50_50_paragraph->id(),
                  'target_revision_id' => $db_50_50_paragraph->getRevisionId(),
                ];
            }
            else {
              $gallery_block_paragraph->field_images[] = ['target_id' => $media_item_id];
              $gallery_block_paragraph->save();
            }
            $i++;
          }
        }
      }
      if (!$translation_node AND isset($old_node->field_data_article_video[0]->article_video_fid) and !empty($old_node->field_data_article_video[0]->article_video_fid)) {
        foreach ($old_node->field_data_article_video as $video) {
          $video->file_managed = $this->nodeOldFileData($video->article_video_fid);
          $video->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $image->article_video_fid, 'video', 'file');

          $media_item_id = $this->getAndSaveVideo($video);
          if (isset($media_item_id) and !empty($media_item_id)) {
            $video_block_paragraph = Paragraph::create([
              'type' => 'video_block',
            ]);
            $video_block_paragraph->field_video[] = ['target_id' => $media_item_id];
            $video_block_paragraph->save();
            if (!isset($db_50_50_paragraph)) {
              $db_50_50_paragraph = Paragraph::create([
                'type' => 'db_50_50',
              ]);
              $db_50_50_paragraph->field_content_blocks_50_1[] =
                [
                  'target_id' => $video_block_paragraph->id(),
                  'target_revision_id' => $video_block_paragraph->getRevisionId(),
                ];
              $db_50_50_paragraph->save();
              $node->field_distribution_blocks[] =
                [
                  'target_id' => $db_50_50_paragraph->id(),
                  'target_revision_id' => $db_50_50_paragraph->getRevisionId(),
                ];
            } else {
              $db_50_50_paragraph->field_content_blocks_50_2[] =
                [
                  'target_id' => $video_block_paragraph->id(),
                  'target_revision_id' => $video_block_paragraph->getRevisionId(),
                ];
              $db_50_50_paragraph->save();
            }
          }
        }
      }
      if (!$translation_node AND isset($old_node->field_data_field_attachments[0]->field_attachments_fid) and !empty($old_node->field_data_field_attachments[0]->field_attachments_fid)) {
        $link_and_file_block_paragraph = Paragraph::create([
          'type' => 'link_and_file_block',
        ]);
        $link_and_file_block_paragraph->save();
        $db_100_paragraph = Paragraph::create([
          'type' => 'db_100',
        ]);
        $db_100_paragraph->field_content_blocks_100[] =
          [
            'target_id' => $link_and_file_block_paragraph->id(),
            'target_revision_id' => $link_and_file_block_paragraph->getRevisionId(),
          ];
        $db_100_paragraph->save();
        $node->field_distribution_blocks[] =
          [
            'target_id' => $db_100_paragraph->id(),
            'target_revision_id' => $db_100_paragraph->getRevisionId(),
          ];
        foreach ($old_node->field_data_field_attachments as $attachment) {
          $attachment->file_managed = $this->nodeOldFileData($attachment->field_attachments_fid);
          $attachment->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $attachment->field_attachments_fid, 'document', 'file');
          [
            $media_item_id,
            $new_file_url,
            $old_document_url,
            $old_document_url_relative
          ] = $this->getAndSaveDocument($attachment);
          if (isset($media_item_id) and !empty($media_item_id)) {
            $file_paragraph = Paragraph::create([
              'type' => 'file'
            ]);
            $file_paragraph->field_file[0] = ['target_id' => $media_item_id];
            $file_paragraph->save();

            $link_and_file_block_paragraph->field_links_and_files[] =
              [
                'target_id' => $file_paragraph->id(),
                'target_revision_id' => $file_paragraph->getRevisionId(),
              ];
            $link_and_file_block_paragraph->save();
            $body = str_replace($old_document_url, $new_file_url, $body); #Kui link dokumendile on https://...
            $body = str_replace($old_document_url_relative, $new_file_url, $body); #Kui link dokumendile on /sites/....
          }
        }
      }
      #if(isset($body) AND !empty($body)) { Lisame ka tühja body, näiteks tõlgetel võib olla see tühi.
        $body = str_replace(array('<h1>', '</h1>'), array('<h2>', '</h2>'), $body); #me ei luba h1 tagi, asendame selle h2-ga
        $body = preg_replace("/<img[^>]+\>/i", " ", $body); #me ei luba pilte, asendame tühikuga
        $node->body->value = $body;
        $node->body->format = 'full_html';
      #}
      $node->save();

      #Lisamine menüüpunkti ainult avaldatud sisudele (muidu on võõrkeeltes palju ebavajalikke linke menüüs)
      if ($old_node->status == 1) {
        if ($old_type == 'curriculum') {
          if (isset($old_node->field_data_field_school_selections[0]->field_school_selections_value) and !empty($old_node->field_data_field_school_selections[0]->field_school_selections_value)) {
            $parent_menu_item = $fake_menu_item_uuid = FALSE;
            $menu_link_storage = $this->entityTypeManager->getStorage('menu_link_content');
            $parent_node = $this->entityOldFindByReferenceData('field_data_field_school_selections', ['entity_id'], 'field_school_selections_value', $old_node->field_data_field_school_selections[0]->field_school_selections_value, 'content_page');
            if (isset($parent_node[0]->entity_id) and !empty($parent_node[0]->entity_id)) {
              $parent_menu_item = $this->nodeOldMenuData('node/' . $parent_node[0]->entity_id, 'hitsa-main-menu');
              $menu_items = $menu_link_storage->loadByProperties(['menu_name' => 'main']);
              foreach($menu_items as $item) {
                if($item->get('title')->value == $parent_menu_item[0]->link_title) {
                  $fake_menu_item_uuid = $item->uuid();
                  break;
                }
              }
            }
            if (isset($old_node->field_data_field_field[0]->name) and !empty($old_node->field_data_field_field[0]->name)) {
              $link_et = $old_node->field_data_field_field[0]->name;
              if($old_node->language != 'et') {
                if (isset($old_node->field_data_field_field[0]->translatsions) and !empty($old_node->field_data_field_field[0]->translatsions)) {
                  foreach ($old_node->field_data_field_field[0]->translatsions as $trans) {
                    if ($trans->language == $old_node->language) {
                      $old_node->field_data_field_field[0]->name = $trans->translation;
                      break;
                    }
                  }
                }
              }
              $link_title = $old_node->field_data_field_field[0]->name;
              if (isset($old_node->field_data_field_field[0]->field_data_field_extra_name[0]->field_extra_name_value) and !empty($old_node->field_data_field_field[0]->field_data_field_extra_name[0]->field_extra_name_value)) {
                foreach ($old_node->field_data_field_field[0]->field_data_field_extra_name as $extra_name) {
                  $link_title .= ', ' . $extra_name->field_extra_name_value;
                }
              }
              if (isset($fake_menu_item_uuid) and !empty($fake_menu_item_uuid)) {
                $found = FALSE;
                $menu_items = $menu_link_storage->loadByProperties(['menu_name' => 'main']);
                if (isset($old_node->tnid) and !empty($old_node->tnid) and $old_node->language != 'et') {
                  foreach ($menu_items as $item) {
                    if ($item->get('title')->value == $link_et) {
                      if (!$item->hasTranslation($old_node->language)) {
                        $menu_item_translation = $item->addTranslation($old_node->language);
                        $menu_item_translation->title = $link_title;
                        $menu_item_translation->save();
                        $found = TRUE;
                        break;
                      }
                    }
                  }
                }

                foreach ($menu_items as $item) {
                  if ($item->get('title')->value == $link_title) {
                    $fake_menu_item_uuid = $item->uuid();
                    $found = TRUE;
                    break;
                  }
                }
                if (!$found) {
                  $menu_link_new = $menu_link_storage->create([
                    'title' => $link_title,
                    'link' => ['uri' => 'route:<nolink>'],
                    'menu_name' => 'main',
                    'parent' => 'menu_link_content:' . $fake_menu_item_uuid,
                    'expanded' => TRUE,
                    'weight' => $old_node->field_data_field_field[0]->weight,
                    'langcode' => $old_node->language,
                  ]);
                  $menu_link_new->save();
                  $fake_menu_item_uuid = $menu_link_new->uuid();
                }
              }
            }
            $fake_menu_item_weight = $count;
            if (isset($old_node->field_data_field_tab_weight[0]->field_tab_weight_value) and !empty($old_node->field_data_field_tab_weight[0]->field_tab_weight_value)) {
              $fake_menu_item_weight = $old_node->field_data_field_tab_weight[0]->field_tab_weight_value;
            }
            if (isset($fake_menu_item_uuid) and !empty($fake_menu_item_uuid)) {
              $found = FALSE;
              if (isset($old_node->tnid) and !empty($old_node->tnid) and $old_node->language != 'et') {
                $i18n_node = $this->getNodeOldByNid($old_node->tnid);
                $menu_items = $menu_link_storage->loadByProperties(['menu_name' => 'main']);
                foreach ($menu_items as $item) {
                  if ($item->get('title')->value == $i18n_node[0]->title) {
                    if (!$item->hasTranslation($old_node->language)) {
                      $menu_item_translation = $item->addTranslation($old_node->language);
                      $menu_item_translation->title = $old_node->title;
                      $menu_item_translation->save();
                      $found = TRUE;
                      break;
                    }
                  }
                }
              }
              if (!$found) {
                $menu_link_new = $menu_link_storage->create([
                  'title' => $old_node->title,
                  'link' => ['uri' => 'entity:node/' . $node->id()],
                  'menu_name' => 'main',
                  'parent' => 'menu_link_content:' . $fake_menu_item_uuid,
                  'expanded' => TRUE,
                  'weight' => $fake_menu_item_weight,
                  'langcode' => $old_node->language,
                ]);
                $menu_link_new->save();
              }
            }
          }
        }
        elseif (isset($old_node->main_menu[0]->mlid) AND !empty($old_node->main_menu[0]->mlid)) {
          #@TODO muudame keelt hiljem, miks see kood siin oli?
          #if ($old_node->main_menu[0]->language == 'und') {
          #  $old_node->main_menu[0]->language = $old_node->language;
          #}
          $this->migrateMenuLink($old_node->main_menu[0], $node->id());
        }
      }
      if (isset($node) and !empty($node)) {
        $node->set("path", ["pathauto" => TRUE]);
        $node->save();
        return $node->id();
      }
    }
    else {
      $status_text = 'Old node data: ' . print_r($old_node,1);
      $this->messenger->addStatus($status_text);
    }
    return -1;
  }

  public function migrateEvent($old_node, $count, $debug = FALSE) {
    $body = '';
    $old_nid = $old_node->nid;
    $old_type = $old_node->type;
    $old_node->field_data_body = $this->entityOldTextFieldData('field_data_body', ['body_value', 'body_summary'], $old_nid, $old_type);
    $old_node->field_data_field_event_type = $this->entityOldTextFieldData('field_data_field_event_type', ['field_event_type_value'], $old_nid, $old_type);
    #@TODO Kas vaja Pole kasutusel?
    $old_node->field_data_field_time_period_dates = $this->entityOldTextFieldData('field_data_field_time_period_dates', ['field_time_period_dates_value', 'field_time_period_dates_value2'], $old_nid, $old_type);
    $old_node->field_data_event_tags = $this->nodeOldTaxonomyFieldData('field_data_event_tags', ['event_tags_tid'], 'event_tags_tid', $old_nid, $old_type);
    $old_node->field_data_locations = $this->nodeOldTaxonomyFieldData('field_data_locations', ['locations_tid'], 'locations_tid', $old_nid, $old_type);
    $old_node->field_data_event_date = $this->entityOldTextFieldData('field_data_event_date', ['event_date_value', 'event_date_value2'], $old_nid, $old_type);
    $old_node->field_data_field_training_cost = $this->entityOldTextFieldData('field_data_field_training_cost', ['field_training_cost_value'], $old_nid, $old_type);
    $old_node->field_data_field_training_tags = $this->nodeOldTaxonomyFieldData('field_data_field_training_tags', ['field_training_tags_target_id'], 'field_training_tags_target_id', $old_nid, $old_type);
    #@TODO Kas vaja Pole kasutusel?
    $old_node->field_data_period = $this->nodeOldTaxonomyFieldData('field_data_period', ['period_tid'], 'period_tid', $old_nid, $old_type);
    $old_node->field_data_field_contacts = $this->entityOldTextFieldData('field_data_field_contacts', ['field_contacts_value'], $old_nid, $old_type);
    foreach ($old_node->field_data_field_contacts as $contact) {
      $contact->field_data_field_contact_title = $this->entityOldTextFieldData('field_data_field_contact_title', ['field_contact_title_value'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_website = $this->entityOldTextFieldData('field_data_field_website', ['field_website_url', 'field_website_title'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_contact_email = $this->entityOldTextFieldData('field_data_field_contact_email', ['field_contact_email_email'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_phone_number = $this->entityOldTextFieldData('field_data_field_phone_number', ['field_phone_number_value'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_address = $this->entityOldTextFieldData('field_data_field_address', ['field_address_value'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
    }
    $old_node->field_data_field_pictures = $this->entityOldTextFieldData('field_data_field_pictures', ['field_pictures_fid'], $old_nid, $old_type);
    $old_node->field_data_field_juhani_id = $this->entityOldTextFieldData('field_data_field_juhani_id', ['field_juhani_id_value'], $old_nid, $old_type);
    $old_node->field_data_field_juhan_url = $this->entityOldTextFieldData('field_data_field_juhan_url', ['field_juhan_url_value'], $old_nid, $old_type);
    $old_node->field_data_field_juhan_koolitus = $this->entityOldTextFieldData('field_data_field_juhan_koolitus', ['field_juhan_koolitus_value'], $old_nid, $old_type);

    $node = FALSE;
    if (!$debug) {
      if (isset($old_node->tnid) and !empty($old_node->tnid) and $old_node->tnid != $old_node->nid) {
        $node = $this->getNodeNewTranslation($old_node);
      }
      if (!$node) {
        $node = Node::create([
          'type' => 'calendar',
          'title' => $old_node->title,
          'langcode' => $old_node->language,
          'status' => $old_node->status,
          'created' => $old_node->created,
          'changed' => $old_node->changed,
          'uid' => \Drupal::currentUser()->id(),
        ]);
      }
      $new_event_types = ['event' => 2, 'training' => 1];
      if (isset($old_node->field_data_field_event_type[0]->field_event_type_value) and !empty($old_node->field_data_field_event_type[0]->field_event_type_value)) {
        foreach ($old_node->field_data_field_event_type as $event_type) {
          $node->field_event_type[] = ['value' => $new_event_types[$event_type->field_event_type_value]];
        }
      }

      if (isset($old_node->field_data_event_tags[0]->name) and !empty($old_node->field_data_event_tags[0]->name)) {
        foreach ($old_node->field_data_event_tags as $event_tag) {
          if (isset($event_tag->name) and !empty($event_tag->name)) {
            $node->field_event_keywords[] = ['target_id' => $this->getTermNewByName($event_tag->name, 'event_keywords')];
          }
        }
      }
      if (isset($old_node->field_data_locations[0]->name) and !empty($old_node->field_data_locations[0]->name)) {
        $node->field_venue->value = $old_node->field_data_locations[0]->name;
      }

      if (isset($old_node->field_data_event_date[0]->event_date_value) and !empty($old_node->field_data_event_date[0]->event_date_value)) {
        $node->field_start_date->value = date('Y-m-d', $old_node->field_data_event_date[0]->event_date_value);
        $converted_time = date('H:i:s', $old_node->field_data_event_date[0]->event_date_value);
        if ($converted_time == '00:00:00') {
          $node->field_all_day->value = TRUE;
        } else {
          $node->field_event_start_time->value = $this->timeToSeconds($converted_time);
        }
        if (isset($old_node->field_data_event_date[0]->event_date_value2) and !empty($old_node->field_data_event_date[0]->event_date_value2)) {
          if ($old_node->field_data_event_date[0]->event_date_value != $old_node->field_data_event_date[0]->event_date_value2 ) {
            $node->field_event_end_date->value = date('Y-m-d', $old_node->field_data_event_date[0]->event_date_value2);
            $node->field_show_end_date->value = TRUE;
            $converted_time2 = date('H:i:s', $old_node->field_data_event_date[0]->event_date_value2);
            if ($converted_time2 == '00:00:00') {

            } else {
              $node->field_event_end_time->value = $this->timeToSeconds($converted_time2);
              $node->field_all_day->value = FALSE;
            }
          }
          else {
            $node->field_show_end_date->value = FALSE;
          }
        }
      }
      if (isset($old_node->field_data_field_training_cost[0]->field_training_cost_value) and !empty($old_node->field_data_field_training_cost[0]->field_training_cost_value)) {
        $node->field_price->value = $old_node->field_data_field_training_cost[0]->field_training_cost_value;
      }
      if (isset($old_node->field_data_field_training_tags[0]->name) and !empty($old_node->field_data_field_training_tags[0]->name)) {
        foreach ($old_node->field_data_field_training_tags as $training_tag) {
          if (isset($training_tag->name) and !empty($training_tag->name)) {
            $node->field_training_keywords[] = ['target_id' => $this->getTermNewByName($training_tag->name, 'training_keywords')];
          }
        }
      }
      if (isset($old_node->field_data_body[0]->body_summary) and !empty($old_node->field_data_body[0]->body_summary)) {
        $body .= str_replace('<p>', '<p class="large">', $old_node->field_data_body[0]->body_summary);
      }
      if (isset($old_node->field_data_body[0]->body_value) and !empty($old_node->field_data_body[0]->body_value)) {
        $body .= $old_node->field_data_body[0]->body_value;
      }
      if(isset($body) AND !empty($body)) {
        $body = str_replace(array('<h1>', '</h1>'), array('<h2>', '</h2>'), $body); #me ei luba h1 tagi, asendame selle h2-ga
        $body = preg_replace("/<img[^>]+\>/i", " ", $body); #me ei luba pilte, asendame tühikuga
        $node->body->value = $body;
        $node->body->format = 'full_html';
      }

      if (isset($old_node->field_data_field_contacts[0]->field_contacts_value) and !empty($old_node->field_data_field_contacts[0]->field_contacts_value)) {
        $contact_block_paragraph = Paragraph::create([
          'type' => 'contact_block',
        ]);
        $contact_block_paragraph->save();

        $node->field_contact_block[] =
          [
            'target_id' => $contact_block_paragraph->id(),
            'target_revision_id' => $contact_block_paragraph->getRevisionId(),
          ];

        foreach ($old_node->field_data_field_contacts as $contact) {
          $contact_block_paragraph->field_contact_title = 'Kontakt';
          if (isset($contact->field_data_field_contact_title[0]->field_contact_title_value) and !empty($contact->field_data_field_contact_title[0]->field_contact_title_value)) {
            $contact_block_paragraph->field_name = $contact->field_data_field_contact_title[0]->field_contact_title_value;
          }
          if (isset($contact->field_data_field_phone_number[0]->field_phone_number_value) and !empty($contact->field_data_field_phone_number[0]->field_phone_number_value)) {
            $contact_block_paragraph->field_phone = $contact->field_data_field_phone_number[0]->field_phone_number_value;
          }
          if (isset($contact->field_data_field_contact_email[0]->field_contact_email_email) and !empty($contact->field_data_field_contact_email[0]->field_contact_email_email)) {
            $contact_block_paragraph->field_email = $contact->field_data_field_contact_email[0]->field_contact_email_email;
          }
          if (isset($contact->field_data_field_website[0]->field_website_url) and !empty($contact->field_data_field_website[0]->field_website_url)) {
            if(UrlHelper::isValid($contact->field_data_field_website[0]->field_website_url, TRUE)) {
              $contact_block_paragraph->field_link[0] =
              [
                'uri' => $contact->field_data_field_website[0]->field_website_url,
                'title' => $contact->field_data_field_website[0]->field_website_title
              ];
            }
            else {
              $status_text = 'Väline link ei ole korrektne: ' . $contact->field_data_field_website[0]->field_website_url;
              $this->messenger->addStatus($status_text);
              $this->logger->info($status_text);
            }
          }
          if (isset($contact->field_data_field_address[0]->field_address_value) and !empty($contact->field_data_field_address[0]->field_address_value)) {
            $contact_block_paragraph->field_address = $contact->field_data_field_address[0]->field_address_value;
          }
          $contact_block_paragraph->save();
        }
      }
      if (isset($old_node->field_data_field_juhani_id[0]->field_juhani_id_value) and !empty($old_node->field_data_field_juhani_id[0]->field_juhani_id_value)) {
        $node->field_juhan_id->value = $old_node->field_data_field_juhani_id[0]->field_juhani_id_value;
      }
      if (isset($old_node->field_data_field_juhan_url[0]->field_juhan_url_value) and !empty($old_node->field_data_field_juhan_url[0]->field_juhan_url_value)) {
        if(UrlHelper::isValid($old_node->field_data_field_juhan_url[0]->field_juhan_url_value, TRUE)) {
          $node->field_juhan_training_url = ["uri" => $old_node->field_data_field_juhan_url[0]->field_juhan_url_value, "title" => "", "options" =>[ 'attributes' => ['target' => '_blank']]];
        }
        else {
          $status_text = 'Väline link ei ole korrektne: ' . $old_node->field_data_field_juhan_url[0]->field_juhan_url_value;
          $this->messenger->addStatus($status_text);
          $this->logger->info($status_text);
        }
      }
      if (isset($old_node->field_data_field_juhan_koolitus[0]->field_juhan_koolitus_value) and !empty($old_node->field_data_field_juhan_koolitus[0]->field_juhan_koolitus_value)) {
        $node->field_juhan_training->value = $old_node->field_data_field_juhan_koolitus[0]->field_juhan_koolitus_value;
      }

      foreach ($old_node->field_data_field_pictures as $image) {
        $image->file_managed = $this->nodeOldFileData($image->field_pictures_fid);
        $image->field_data_field_file_image_alt_text = $this->entityOldTextFieldData('field_data_field_file_image_alt_text', ['field_file_image_alt_text_value'], $image->field_pictures_fid, 'image', 'file');
        $image->field_data_field_file_image_title_text = $this->entityOldTextFieldData('field_data_field_file_image_title_text', ['field_file_image_title_text_value'], $image->field_pictures_fid, 'image', 'file');
        $image->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $image->field_pictures_fid, 'image', 'file');

        $media_item_id = $this->getAndSaveImage($image);
        if (isset($media_item_id) and !empty($media_item_id)) {
          $node->field_one_image[] = ['target_id' => $media_item_id];
        }
      }
      $node->save();
      if (isset($node) and !empty($node)) {
        return $node->id();
      }
    }
    else {
      $status_text = 'Old node data: ' . print_r($old_node,1);
      $this->messenger->addStatus($status_text);
    }
    return -1;
  }

  public function migrateLogo($old_node, $count, $debug = FALSE) {
    $old_nid = $old_node->nid;
    $old_type = $old_node->type;
    $old_node->field_data_logo_type = $this->entityOldTextFieldData('field_data_logo_type', ['logo_type_value'], $old_nid, $old_type);
    $old_node->field_data_banner_image = $this->entityOldTextFieldData('field_data_banner_image', ['banner_image_fid'], $old_nid, $old_type);
    $old_node->field_data_logo_link = $this->entityOldTextFieldData('field_data_logo_link', ['logo_link_url', 'logo_link_title'], $old_nid, $old_type);

    $node = FALSE;
    if (!$debug) {
      if (isset($old_node->tnid) and !empty($old_node->tnid) and $old_node->tnid != $old_node->nid) {
        $node = $this->getNodeNewTranslation($old_node);
      }
      if (!$node) {
        $node = Node::create([
          'type' => 'partner_logo',
          'title' => $old_node->title,
          'langcode' => $old_node->language,
          'status' => $old_node->status,
          'created' => $old_node->created,
          'changed' => $old_node->changed,
          'uid' => \Drupal::currentUser()->id(),
        ]);
      }
      $new_logo_types = ['partner' => 2, 'award' => 1];
      if (isset($old_node->field_data_logo_type[0]->logo_type_value) and !empty($old_node->field_data_logo_type[0]->logo_type_value)) {
        $node->field_partner_logo_type->value = $new_logo_types[$old_node->field_data_logo_type[0]->logo_type_value];
      }
      foreach ($old_node->field_data_banner_image as $image) {
        $image->file_managed = $this->nodeOldFileData($image->banner_image_fid);
        $image->field_data_field_file_image_alt_text = $this->entityOldTextFieldData('field_data_field_file_image_alt_text', ['field_file_image_alt_text_value'], $image->banner_image_fid, 'image', 'file');
        $image->field_data_field_file_image_title_text = $this->entityOldTextFieldData('field_data_field_file_image_title_text', ['field_file_image_title_text_value'], $image->banner_image_fid, 'image', 'file');
        $image->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $image->banner_image_fid, 'image', 'file');

        $media_item_id = $this->getAndSaveImage($image);
        if (isset($media_item_id) and !empty($media_item_id)) {
          $node->field_one_image[] = ['target_id' => $media_item_id];
        }
      }
      if (isset($old_node->field_data_logo_link[0]->logo_link_url) and !empty($old_node->field_data_logo_link[0]->logo_link_url)) {
        if(UrlHelper::isValid($old_node->field_data_logo_link[0]->logo_link_url, TRUE)) {
          $node->field_link = ["uri" => $old_node->field_data_logo_link[0]->logo_link_url, "title" => "", "options" =>[ 'attributes' => ['target' => '_blank']]];
        }
        else {
          $status_text = 'Väline link ei ole korrektne: ' . $old_node->field_data_logo_link[0]->logo_link_url;
          $this->messenger->addStatus($status_text);
          $this->logger->info($status_text);
        }
      }
      $node->field_logo_weight->value = $count;
      $node->save();
      if (isset($node) and !empty($node)) {
        return $node->id();
      }
    }
    else {
      $status_text = 'Old node data: ' . print_r($old_node,1);
      $this->messenger->addStatus($status_text);
    }
    return -1;
  }

  public function migrateArticle($old_node, $count, $debug = FALSE) {
    $body = '';
    $old_nid = $old_node->nid;
    $old_type = $old_node->type;
    $old_node->author = $this->getUserOld($old_node->uid);
    $old_node->field_data_article_type = $this->entityOldTextFieldData('field_data_article_type', ['article_type_value'], $old_nid, $old_type);
    $old_node->field_data_field_author_custom = $this->entityOldTextFieldData('field_data_field_author_custom', ['field_author_custom_value'], $old_nid, $old_type);
    $old_node->field_data_subpage_images = $this->entityOldTextFieldData('field_data_subpage_images', ['subpage_images_fid'], $old_nid, $old_type);
    $old_node->field_data_article_video = $this->entityOldTextFieldData('field_data_article_video', ['article_video_fid'], $old_nid, $old_type);
    $old_node->field_data_academic_year = $this->nodeOldTaxonomyFieldData('field_data_academic_year', ['academic_year_tid'], 'academic_year_tid', $old_nid, $old_type);
    $old_node->field_data_body = $this->entityOldTextFieldData('field_data_body', ['body_value', 'body_summary'], $old_nid, $old_type);
    $old_node->field_data_field_contacts = $this->entityOldTextFieldData('field_data_field_contacts', ['field_contacts_value'], $old_nid, $old_type);
    foreach ($old_node->field_data_field_contacts as $contact) {
      $contact->field_data_field_contact_title = $this->entityOldTextFieldData('field_data_field_contact_title', ['field_contact_title_value'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_website = $this->entityOldTextFieldData('field_data_field_website', ['field_website_url', 'field_website_title'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_contact_email = $this->entityOldTextFieldData('field_data_field_contact_email', ['field_contact_email_email'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_phone_number = $this->entityOldTextFieldData('field_data_field_phone_number', ['field_phone_number_value'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
      $contact->field_data_field_address = $this->entityOldTextFieldData('field_data_field_address', ['field_address_value'], $contact->field_contacts_value, 'contacts', 'paragraphs_item');
    }
    $old_node->field_data_field_attachments = [];
    if (!is_array($old_node->field_data_subpage_images)) {
      $old_node->field_data_subpage_images = [];
    }
    if(isset($old_node->field_data_body[0]->body_summary) AND !empty($old_node->field_data_body[0]->body_summary)) {
      $old_node->field_data_field_attachments = array_merge($old_node->field_data_field_attachments, $this->searchDocsFromHtml($old_node->field_data_body[0]->body_summary));
      $old_node->field_data_subpage_images = array_merge($old_node->field_data_subpage_images, $this->searchImagesFromHtml($old_node->field_data_body[0]->body_summary, 'subpage_images_fid'));
    }
    if(isset($old_node->field_data_body[0]->body_value) AND !empty($old_node->field_data_body[0]->body_value)) {
      $old_node->field_data_field_attachments = array_merge($old_node->field_data_field_attachments, $this->searchDocsFromHtml($old_node->field_data_body[0]->body_value));
      $old_node->field_data_subpage_images = array_merge($old_node->field_data_subpage_images, $this->searchImagesFromHtml($old_node->field_data_body[0]->body_value, 'subpage_images_fid'));
    }

    $node = FALSE;
    if (!$debug) {
      if (isset($old_node->tnid) and !empty($old_node->tnid) and $old_node->tnid != $old_node->nid) {
        $node = $this->getNodeNewTranslation($old_node);
      }
      if (!$node) {
        $node = Node::create([
          'type' => 'article',
          'title' => $old_node->title,
          'langcode' => $old_node->language,
          'status' => $old_node->status,
          'created' => $old_node->created,
          'changed' => $old_node->changed,
          'uid' => \Drupal::currentUser()->id(),
        ]);
      }
      $new_article_types = ['news' => 1, 'our_stories' => 2, 'important' => 1];
      if (isset($old_node->field_data_article_type[0]->article_type_value) and !empty($old_node->field_data_article_type[0]->article_type_value)) {
        $node->field_article_type->value = $new_article_types[$old_node->field_data_article_type[0]->article_type_value];
      }
      if (isset($old_node->field_data_field_author_custom[0]->field_author_custom_value) and !empty($old_node->field_data_field_author_custom[0]->field_author_custom_value)) {
        $node->field_author_name->value = $old_node->field_data_field_author_custom[0]->field_author_custom_value;
      }
      elseif (isset($old_node->author) and !empty($old_node->author)) {
        $node->field_author_name->value = $old_node->author;
      }
      else {
        $node->field_author_name->value = 'Anonüümne';
      }
      if (isset($old_node->field_data_body[0]->body_summary) and !empty($old_node->field_data_body[0]->body_summary)) {
        $body .= '<p class="large">' . $old_node->field_data_body[0]->body_summary .'</p>';
      }
      if (isset($old_node->field_data_body[0]->body_value) and !empty($old_node->field_data_body[0]->body_value)) {
        $body .= $old_node->field_data_body[0]->body_value;
      }
      if (isset($old_node->field_data_subpage_images[0]->subpage_images_fid) and !empty($old_node->field_data_subpage_images[0]->subpage_images_fid)) {
        $i = 1;
        foreach ($old_node->field_data_subpage_images as $image) {
          $image->file_managed = $this->nodeOldFileData($image->subpage_images_fid);
          $image->field_data_field_file_image_alt_text = $this->entityOldTextFieldData('field_data_field_file_image_alt_text', ['field_file_image_alt_text_value'], $image->subpage_images_fid, 'image', 'file');
          $image->field_data_field_file_image_title_text = $this->entityOldTextFieldData('field_data_field_file_image_title_text', ['field_file_image_title_text_value'], $image->subpage_images_fid, 'image', 'file');
          $image->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $image->subpage_images_fid, 'image', 'file');

          $media_item_id = $this->getAndSaveImage($image);
          if (isset($media_item_id) and !empty($media_item_id)) {
            if ($i == 1) {
              $node->field_one_image[] = ['target_id' => $media_item_id];
            }
            elseif ($i == 2) {
              $gallery_block_paragraph = Paragraph::create([
                'type' => 'gallery_block',
              ]);
              $gallery_block_paragraph->field_images[] = ['target_id' => $media_item_id];
              $gallery_block_paragraph->save();

              $db_50_50_paragraph = Paragraph::create([
                'type' => 'db_50_50',
              ]);
              $db_50_50_paragraph->field_content_blocks_50_1[] =
                [
                  'target_id' => $gallery_block_paragraph->id(),
                  'target_revision_id' => $gallery_block_paragraph->getRevisionId(),
                ];
              $db_50_50_paragraph->save();
              $node->field_distribution_blocks[] =
                [
                  'target_id' => $db_50_50_paragraph->id(),
                  'target_revision_id' => $db_50_50_paragraph->getRevisionId(),
                ];
            }
            else {
              $gallery_block_paragraph->field_images[] = ['target_id' => $media_item_id];
              $gallery_block_paragraph->save();
            }
            $i++;
          }
        }
      }
      if (isset($old_node->field_data_article_video[0]->article_video_fid) and !empty($old_node->field_data_article_video[0]->article_video_fid)) {
        foreach ($old_node->field_data_article_video as $video) {
          $video->file_managed = $this->nodeOldFileData($video->article_video_fid);
          $video->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $image->article_video_fid, 'video', 'file');

          $media_item_id = $this->getAndSaveVideo($video);
          if (isset($media_item_id) and !empty($media_item_id)) {
            $video_block_paragraph = Paragraph::create([
              'type' => 'video_block',
            ]);
            $video_block_paragraph->field_video[] = ['target_id' => $media_item_id];
            $video_block_paragraph->save();
            if (!isset($db_50_50_paragraph)) {
              $db_50_50_paragraph = Paragraph::create([
                'type' => 'db_50_50',
              ]);
              $db_50_50_paragraph->field_content_blocks_50_1[] =
                [
                  'target_id' => $video_block_paragraph->id(),
                  'target_revision_id' => $video_block_paragraph->getRevisionId(),
                ];
              $db_50_50_paragraph->save();
              $node->field_distribution_blocks[] =
                [
                  'target_id' => $db_50_50_paragraph->id(),
                  'target_revision_id' => $db_50_50_paragraph->getRevisionId(),
                ];
            } else {
              $db_50_50_paragraph->field_content_blocks_50_2[] =
                [
                  'target_id' => $video_block_paragraph->id(),
                  'target_revision_id' => $video_block_paragraph->getRevisionId(),
                ];
              $db_50_50_paragraph->save();
            }
          }
        }
      }
      if (isset($old_node->field_data_field_attachments[0]->field_attachments_fid) and !empty($old_node->field_data_field_attachments[0]->field_attachments_fid)) {
        $link_and_file_block_paragraph = Paragraph::create([
          'type' => 'link_and_file_block',
        ]);
        $link_and_file_block_paragraph->save();
        $db_100_paragraph = Paragraph::create([
          'type' => 'db_100',
        ]);
        $db_100_paragraph->field_content_blocks_100[] =
          [
            'target_id' => $link_and_file_block_paragraph->id(),
            'target_revision_id' => $link_and_file_block_paragraph->getRevisionId(),
          ];
        $db_100_paragraph->save();
        $node->field_distribution_blocks[] =
          [
            'target_id' => $db_100_paragraph->id(),
            'target_revision_id' => $db_100_paragraph->getRevisionId(),
          ];
        foreach ($old_node->field_data_field_attachments as $attachment) {
          $attachment->file_managed = $this->nodeOldFileData($attachment->field_attachments_fid);
          $attachment->field_data_field_folder = $this->nodeOldTaxonomyFieldData('field_data_field_folder', ['field_folder_tid'], 'field_folder_tid', $attachment->field_attachments_fid, 'document', 'file');
          [
            $media_item_id,
            $new_file_url,
            $old_document_url,
            $old_document_url_relative
          ] = $this->getAndSaveDocument($attachment);
          if (isset($media_item_id) and !empty($media_item_id)) {
            $file_paragraph = Paragraph::create([
              'type' => 'file'
            ]);
            $file_paragraph->field_file[0] = ['target_id' => $media_item_id];
            $file_paragraph->save();

            $link_and_file_block_paragraph->field_links_and_files[] =
              [
                'target_id' => $file_paragraph->id(),
                'target_revision_id' => $file_paragraph->getRevisionId(),
              ];
            $link_and_file_block_paragraph->save();
            $body = str_replace($old_document_url, $new_file_url, $body); #Kui link dokumendile on https://...
            $body = str_replace($old_document_url_relative, $new_file_url, $body); #Kui link dokumendile on /sites/....
          }
        }
      }

      if(isset($old_node->field_data_academic_year[0]->name) AND !empty($old_node->field_data_academic_year[0]->name)) {
        $node->field_academic_year->target_id = $this->getTermNewByName($old_node->field_data_academic_year[0]->name, 'academic_year');
      }
      if (isset($old_node->field_data_field_contacts[0]->field_contacts_value) and !empty($old_node->field_data_field_contacts[0]->field_contacts_value)) {
        $contact_block_paragraph = Paragraph::create([
          'type' => 'contact_block',
        ]);
        $contact_block_paragraph->save();
        if (!isset($db_50_50_paragraph)) {
          $db_50_50_paragraph = Paragraph::create([
            'type' => 'db_50_50',
          ]);
          $db_50_50_paragraph->field_content_blocks_50_1[] =
            [
              'target_id' => $contact_block_paragraph->id(),
              'target_revision_id' => $contact_block_paragraph->getRevisionId(),
            ];
          $db_50_50_paragraph->save();
          $node->field_distribution_blocks[] =
            [
              'target_id' => $db_50_50_paragraph->id(),
              'target_revision_id' => $db_50_50_paragraph->getRevisionId(),
            ];
        } else {
          $db_50_50_paragraph->field_content_blocks_50_1[] =
            [
              'target_id' => $contact_block_paragraph->id(),
              'target_revision_id' => $contact_block_paragraph->getRevisionId(),
            ];
          $db_50_50_paragraph->save();
        }
        foreach ($old_node->field_data_field_contacts as $contact) {
          $contact_block_paragraph->field_contact_title = 'Kontakt';
          if (isset($contact->field_data_field_contact_title[0]->field_contact_title_value) and !empty($contact->field_data_field_contact_title[0]->field_contact_title_value)) {
            $contact_block_paragraph->field_name = $contact->field_data_field_contact_title[0]->field_contact_title_value;
          }
          if (isset($contact->field_data_field_phone_number[0]->field_phone_number_value) and !empty($contact->field_data_field_phone_number[0]->field_phone_number_value)) {
            $contact_block_paragraph->field_phone = $contact->field_data_field_phone_number[0]->field_phone_number_value;
          }
          if (isset($contact->field_data_field_contact_email[0]->field_contact_email_email) and !empty($contact->field_data_field_contact_email[0]->field_contact_email_email)) {
            $contact_block_paragraph->field_email = $contact->field_data_field_contact_email[0]->field_contact_email_email;
          }
          if (isset($contact->field_data_field_website[0]->field_website_url) and !empty($contact->field_data_field_website[0]->field_website_url)) {
            if(UrlHelper::isValid($contact->field_data_field_website[0]->field_website_url, TRUE)) {
              $contact_block_paragraph->field_link[0] =
                [
                  'uri' => $contact->field_data_field_website[0]->field_website_url,
                  'title' => $contact->field_data_field_website[0]->field_website_title
                ];
            }
            else {
              $status_text = 'Väline link ei ole korrektne: ' . $contact->field_data_field_website[0]->field_website_url;
              $this->messenger->addStatus($status_text);
              $this->logger->info($status_text);
            }
          }
          if (isset($contact->field_data_field_address[0]->field_address_value) and !empty($contact->field_data_field_address[0]->field_address_value)) {
            $contact_block_paragraph->field_address = $contact->field_data_field_address[0]->field_address_value;
          }
          $contact_block_paragraph->save();
        }
      }

      if(isset($body) AND !empty($body)) {
        $body = str_replace(array('<h1>', '</h1>'), array('<h2>', '</h2>'), $body); #me ei luba h1 tagi, asendame selle h2-ga
        $body = preg_replace("/<img[^>]+\>/i", " ", $body); #me ei luba pilte, asendame tühikuga
        $node->body->value = $body;
        $node->body->format = 'full_html';
      }
      $node->save();
      if (isset($node) and !empty($node)) {
        return $node->id();
      }
    }
    else {
      $status_text = 'Old node data: ' . print_r($old_node,1);
      $this->messenger->addStatus($status_text);
    }
    return -1;
  }

  public function migrateMenuLink($menu_link, $node_id, $menu_name = 'main') {
    $menu_link_parent_uuid = FALSE;
    $menu_link_storage = $this->entityTypeManager->getStorage('menu_link_content');
    if (isset($menu_link->i18n_tsid) and !empty($menu_link->i18n_tsid) and $menu_link->language != 'et') {
      $i18n_tsid = $this->nodeOldMenuTranslateData($menu_link->i18n_tsid);
      $menu_items = $menu_link_storage->loadByProperties(['menu_name' => $menu_name]);
      foreach ($menu_items as $item) {
        if ($item->get('title')->value == $i18n_tsid[0]->link_title) {
          if (!$item->hasTranslation($menu_link->language)) {
            $menu_item_translation = $item->addTranslation($menu_link->language);
            $menu_item_translation->title = $menu_link->link_title;
            $menu_item_translation->save();
            return;
          }

        }
      }
    }
    if ($menu_link->p1 != $menu_link->mlid) {
      $p1 = $this->nodeOldMenuParentData($menu_link->p1);
      if (isset($p1[0]) and !empty($p1[0])) {
        $menu_link_parent_uuid = $this->migrateMenuAddParent($p1[0], $menu_link_parent_uuid, $menu_name, $menu_link_storage);
        #$status_text = 'p1: ' . print_r($p1[0], 1) . ' menu_link_parent_uuid: ' . $menu_link_parent_uuid;
        #$this->messenger->addStatus($status_text);
      }
    }
    if ($menu_link->p2 > 0 and $menu_link->p2 != $menu_link->mlid) {
      $p2 = $this->nodeOldMenuParentData($menu_link->p2);
      if (isset($p2[0]) and !empty($p2[0]) and $p2[0]->link_path != '<front>') {
        $menu_link_parent_uuid = $this->migrateMenuAddParent($p2[0], $menu_link_parent_uuid, $menu_name, $menu_link_storage);
        #$status_text = 'p2: ' . print_r($p2[0], 1) . 'menu_link_parent_uuid: ' . $menu_link_parent_uuid;
        #$this->messenger->addStatus($status_text);
      }
    }
    if ($menu_link->p3 > 0 and $menu_link->p3 != $menu_link->mlid) {
      $p3 = $this->nodeOldMenuParentData($menu_link->p3);
      if (isset($p3[0]) and !empty($p3[0])) {
        $menu_link_parent_uuid = $this->migrateMenuAddParent($p3[0], $menu_link_parent_uuid, $menu_name, $menu_link_storage);
        #$status_text = 'p3: ' . print_r($p3[0], 1) . 'menu_link_parent_uuid: ' . $menu_link_parent_uuid;
        #$this->messenger->addStatus($status_text);
      }
    }
    if ($menu_link->depth == 1) {
      $menu_items = $menu_link_storage->loadByProperties(['menu_name' => $menu_name]);
      foreach ($menu_items as $item) {
        if ($item->get('title')->value == $menu_link->link_title) {
          return;
        }
      }
    }
    $menu_link_storage->create([
      'title' => $menu_link->link_title,
      'link' => ['uri' => 'entity:node/' . $node_id],
      'menu_name' => $menu_name,
      'parent' => $menu_link_parent_uuid ? 'menu_link_content:' . $menu_link_parent_uuid : NULL,
      'expanded' => TRUE,
      'weight' => $menu_link->weight,
      'langcode' => $menu_link->language,
      'enabled' => $menu_link->hidden ? 0 : 1,
    ])->save();
  }


  public function migrateMenuAddParent($menu_item, $menu_parent, $menu_name, $menu_link_storage) {
    $add_menu_item_to_all_langs = FALSE;
    $orig_menu_item_title = $menu_item->link_title;
    if ($menu_item->language == 'und') {
      $menu_item->language = 'et';
      $add_menu_item_to_all_langs = TRUE;
    }
    #mingil segasel põhjusel on vanal platvormil osad esimese taseme menüüpunktid eesti keeles hoopis inglise keelsed!
    if ($menu_item->language == 'et') {
      switch ($menu_item->link_title) {
        case 'About us':
          $menu_item->link_title = 'Meie koolist';
          break;
        case 'Admission':
          $menu_item->link_title = 'Vastuvõtt';
          break;
        case 'Services':
          $menu_item->link_title = 'Teenused';
          break;
        case 'Studies':
          $menu_item->link_title = 'Õppetöö';
          break;
        case 'Student Life':
          $menu_item->link_title = 'Koolielu';
          break;
        case 'Trainings':
          $menu_item->link_title = 'Koolitused';
          break;
        case 'Training calendar':
          $menu_item->link_title = 'Koolituskalender';
          break;
      }
    }
    $menu_items = $menu_link_storage->loadByProperties(['menu_name' => $menu_name]);
    foreach($menu_items as $item) {
      if($item->get('title')->value == $menu_item->link_title) {
        return $item->uuid();
      }
    }
    $uri = 'route:<nolink>';
    #esimene tase on alati nolink
    if (!$menu_parent) {
      $uri = 'route:<nolink>';
    }
    else {
      switch ($menu_item->link_path) {
        case '<front>':
          $uri = 'route:<nolink>';
          break;
        case 'training-calendar':
          $uri = 'internal:/calendar/training';
          break;
      }
      #@TODO siia mingi loogika, kui seda on vaja?
      #switch ($menu_item->router_path) {
      #  case 'node/%':
      #    $uri = 'route:<nolink>';
      #    break;
      #}
    }
    $menu_link_new = $menu_link_storage->create([
      'title' => $menu_item->link_title,
      'link' => ['uri' => $uri],
      'menu_name' => $menu_name,
      'parent' => $menu_parent ? 'menu_link_content:' . $menu_parent : NULL,
      'expanded' => TRUE,
      'weight' => $menu_item->weight,
      'langcode' => $menu_item->language, #
    ]);
    $menu_link_new->save();

    if ($add_menu_item_to_all_langs) {
      $languages = $this->getLanguagesOld();
      foreach($languages as $lang) {
        if ($lang->language != $menu_item->language) {
          if (!$menu_link_new->hasTranslation($lang->language)) {
            $menu_item_translation = $menu_link_new->addTranslation($lang->language);
            $menu_item_translation->title = $orig_menu_item_title;
            $menu_item_translation->save();
          }
        }
      }
    }

    return $menu_link_new->uuid();
  }


  public function migrateMenuToSettings($menu_items, $type, $debug) {
    $migrate_base_url = Settings::get('migrate_base_url', '');
    $config = $this->configFactory->getEditable('harno_settings.settings');
    $config_translation_en = $this->languageManager->getLanguageConfigOverride('en', 'harno_settings.settings');
    $config_translation_ru = $this->languageManager->getLanguageConfigOverride('ru', 'harno_settings.settings');
    if (!$debug) {
      $j = 0;
      foreach ($menu_items as $id => $item) {
        $url = '';
        $entity = NULL;
        $j = $id + 1;
        if ($j > 8) {
          break; #üle 8 lingi meile ei mahu.
        }

        if (isset($item->external) and $item->external == 1) {
          $url = $item->link_path;
        }
        else {
          $path = $item->link_path;
          if ($path == 'news') {
            $url = $migrate_base_url . Url::fromRoute('harno_pages.news_page')->toString();
          }
          elseif ($path == 'gallery') {
            $url = $migrate_base_url . Url::fromRoute('harno_pages.galleries_page')->toString();
          }
          #@TODO Lisada sisemise lingi viide
          #@TODO Sisemised lingid on lisatud absoluutsete URLidena.
        }
        $config->set($type . '.link_name_' . $j, $item->link_title)
          ->set($type . '.link_entity_' . $j, $entity)
          ->set($type . '.link_url_' . $j, $url)
          ->set($type . '.link_weight_' . $j, $j)
          ->save();

        if ($item->language == 'und') {
          $config_translation_en->set($type . '.link_name_' . $j, $item->link_title)
            ->set($type . '.link_url_' . $j, $url)
            ->save();
          $config_translation_ru->set($type . '.link_name_' . $j, $item->link_title)
            ->set($type . '.link_url_' . $j, $url)
            ->save();
        }
        else {
          $config_translation_en->set($type . '.link_name_' . $j, '')
            ->set($type . '.link_url_' . $j, '')
            ->save();
          $config_translation_ru->set($type . '.link_name_' . $j, '')
            ->set($type . '.link_url_' . $j, '')
            ->save();
        }
      }
      if ($j < 8) {
        for ($i = $j+1; $i <= 8; $i++) {
          $config->set($type . '.link_name_' . $i, '')
            ->set($type . '.link_entity_' . $i, NULL)
            ->set($type . '.link_url_' . $i, '')
            ->set($type . '.link_weight_' . $i, $i)
            ->save();
          $config_translation_en->set($type . '.link_name_' . $i, '')
            ->set($type . '.link_url_' . $i, '')
            ->save();
          $config_translation_ru->set($type . '.link_name_' . $i, '')
            ->set($type . '.link_url_' . $i, '')
            ->save();
        }
      }
    } else {
      $status_text = 'Old menu data: ' . print_r($menu_items, 1);
      $this->messenger->addStatus($status_text);
    }
  }
  public function migrateSettings($settings, $type, $debug) {
    $config = $this->configFactory->getEditable('harno_settings.settings');
    $config_front = $this->configFactory->getEditable('harno_settings.frontpage');
    $config_system = $this->configFactory->getEditable('system.site');
    $config_translation_en = $this->languageManager->getLanguageConfigOverride('en', 'harno_settings.settings');
    $config_translation_ru = $this->languageManager->getLanguageConfigOverride('ru', 'harno_settings.settings');
    if (!$debug) {
      switch ($type) {
        case 'general':
          $config_system->set('name', $settings->general_contact_name)
            ->set('slogan', $settings->site_slogan)
            ->save();
          $config->set('general.address', $settings->general_contact_address)
            ->set('general.phone', $settings->general_contact_phone_nr)
            ->set('general.email', $settings->general_contact_email)
            ->save();
          if (isset($settings->hitsa_site_logo_fid) AND !empty($settings->hitsa_site_logo_fid)) {
            $settings->hitsa_site_logo_file_managed = $this->nodeOldFileData($settings->hitsa_site_logo_fid);
            $new_fid = $this->getAndSaveManagedImage($settings->hitsa_site_logo_file_managed[0]->uri, $settings->hitsa_site_logo_fid->hitsa_site_logo_file_managed->filename,'logo', $config->get('general.logo'));
            if (isset($new_fid) AND !empty($new_fid)) {
              $config->set('general.logo', $new_fid)->save();
            }
          }
          if (isset($settings->theme_hitsa_settings['favicon_path']) AND !empty($settings->theme_hitsa_settings['favicon_path'])) {
            $old_image_name = str_replace('public://', '', $settings->theme_hitsa_settings['favicon_path']);
            $new_fid = $this->getAndSaveManagedImage($settings->theme_hitsa_settings['favicon_path'], $old_image_name, '', $config->get('general.favicon'));
            if (isset($new_fid) AND !empty($new_fid)) {
              $config->set('general.favicon', $new_fid)->save();
            }
          }
          if (isset($settings->hitsa_fp_image_fid) AND !empty($settings->hitsa_fp_image_fid)) {
            $settings->hitsa_fp_image_file_managed = $this->nodeOldFileData($settings->hitsa_fp_image_fid);
            $new_fid = $this->getAndSaveManagedImage($settings->hitsa_fp_image_file_managed[0]->uri, $settings->hitsa_fp_image_file_managed[0]->filename,'frontpage_background', $config_front->get('general.background_image'));
            if (isset($new_fid) AND !empty($new_fid)) {
              $config_front->set('general.background_image', $new_fid)
                ->set('general.background_type', 1)
                ->save();
            }
          }
          break;
        case 'important_contacts':
          for ($i = 1; $i <= 4; $i++) {
            $config->set('important_contacts.name_' . $i, $settings->important_contact[$i]['name'])
              ->set('important_contacts.body_' . $i, $settings->important_contact[$i]['phone'])
              ->set('important_contacts.weight_' . $i, $i)
              ->save();
          }
        case 'footer_socialmedia_links':
          $j = 1;
          if (isset($settings->hitsa_fb_link) AND !empty($settings->hitsa_fb_link)) {
            $config->set('footer_socialmedia_links.link_icon_1', 'mdi-facebook')
              ->set('footer_socialmedia_links.link_name_1', 'Facebook')
              ->set('footer_socialmedia_links.link_url_1', $settings->hitsa_fb_link)
              ->set('footer_socialmedia_links.link_weight_1', 1)
              ->save();
            $j++;
          }
          for ($i = $j; $i <= 9; $i++) {
            $config->set('footer_socialmedia_links.link_icon_'.$i, '')
              ->set('footer_socialmedia_links.link_name_'.$i, '')
              ->set('footer_socialmedia_links.link_url_'.$i, '')
              ->set('footer_socialmedia_links.link_weight_'.$i, $i)
              ->save();
          }
          break;
        case 'footer_free_text_area':
          $config->set('footer_free_text_area.name', $settings->footer_text_area_title)
            ->set('footer_free_text_area.body', strip_tags($settings->footer_text_area['value']))
            ->save();
          break;
        case 'footer_copyright':
          $config->set('footer_copyright.name', $settings->footer_copyright_notice)
            ->save();
          break;
        case 'variables':
          $config->set('news_our_story.name', $settings->front_our_stories_title['et'])
            ->set('juhan.api_key', $settings->juhan_api_key)
            ->save();
          if (isset($settings->front_our_stories_title['en']) AND !empty($settings->front_our_stories_title['en'])) {
            $config_translation_en->set('news_our_story.name', $settings->front_our_stories_title['en'])
              ->save();
          }
          if (isset($settings->front_our_stories_title['ru']) AND !empty($settings->front_our_stories_title['ru'])) {
            $config_translation_ru->set('news_our_story.name', $settings->front_our_stories_title['ru'])
              ->save();
          }
          break;
      }

    } else {
      $status_text = 'Old settings data: ' . print_r($settings, 1);
      $this->messenger->addStatus($status_text);
    }
  }
  public function getNodeNewTranslation($old_node) {
    $node = FALSE;
    $old_tnid = $old_node->tnid;
    $parent_node = $this->getNodeOldByNid($old_tnid);
    if (isset($parent_node[0]->title) and !empty($parent_node[0]->title)) {
      $parent_node_new_ids = $this->getNodeNewByTitle($parent_node[0]->title);
      if (isset($parent_node_new_ids) and !empty($parent_node_new_ids)) {
        foreach ($parent_node_new_ids as $parent_node_new) {
          $node_org = $this->entityTypeManager->getStorage('node')->load($parent_node_new);
          $node_org->setSyncing(TRUE);
          $node_org->setRevisionTranslationAffected(FALSE);
          if (!$node_org->hasTranslation($old_node->language)) {
            $node = $node_org->addTranslation($old_node->language, $node_org->toArray());
            $node->title = $old_node->title;
            $node->status = $old_node->status;
            $node->created = $old_node->created;
            $node->changed = $old_node->changed;
            break;
          }
        }
      }
    }
    return $node;
  }
  public function getMediaImageForFileNew($target_id) {
    $query = $this->database->select('media__field_media_image', 'f')->fields('f', ['entity_id'])
                      ->condition('f.field_media_image_target_id', $target_id);
    $result = $query->execute();
    foreach ($result as $record) {
      return $record;
    }
  }
  public function getMediaDocumentForFileNew($target_id) {
    $query = $this->database->select('media__field_media_document', 'f')->fields('f', ['entity_id'])
      ->condition('f.field_media_document_target_id', $target_id);
    $result = $query->execute();
    foreach ($result as $record) {
      return $record;
    }
  }
  public function getMediaVideoForFileNew($video_value) {
    $query = $this->database->select('media__field_media_oembed_video', 'f')->fields('f', ['entity_id'])
      ->condition('f.field_media_oembed_video_value', $video_value);
    $result = $query->execute();
    foreach ($result as $record) {
      return $record;
    }
  }
  public function getAndSaveImage($image) {
    $migrate_base_url = Settings::get('migrate_base_url', '');
    if (isset($image->file_managed[0]->uri) AND !empty($image->file_managed[0]->uri)) {
      $old_image_uri = $image->file_managed[0]->uri;
      $old_image_name = $image->file_managed[0]->filename;
      $old_image_timestamp = $image->file_managed[0]->timestamp;
      $old_image_status = $image->file_managed[0]->status;
      $old_image_alt = $old_image_title = '';

      if (isset($image->field_data_field_file_image_alt_text[0]->field_file_image_alt_text_value) and !empty($image->field_data_field_file_image_alt_text[0]->field_file_image_alt_text_value)) {
        $old_image_alt = $image->field_data_field_file_image_alt_text[0]->field_file_image_alt_text_value;
      }
      if (isset($image->field_data_field_file_image_title_text[0]->field_file_image_title_text_value) and !empty($image->field_data_field_file_image_title_text[0]->field_file_image_title_text_value)) {
        $old_image_title = $image->field_data_field_file_image_title_text[0]->field_file_image_title_text_value;
      }
      if (isset($image->field_data_field_folder[0]->name) and !empty($image->field_data_field_folder[0]->name)) {
        $old_image_media_folder = $this->getTermNewByName($image->field_data_field_folder[0]->name, 'media_catalogs');
      }
      else {
        $old_image_media_folder = $this->getTermNewByName('Media Root', 'media_catalogs');
      }

      $year_month = date('Y', $old_image_timestamp) . '-' . date('m', $old_image_timestamp);
      $directory = $this->configFactory->get('system.file')
          ->get('default_scheme') . '://' . $year_month;
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      $old_image_url = str_replace('public://', $migrate_base_url . '/sites/' . parse_url($migrate_base_url)['host'] . '/files/', $old_image_uri);

      $path_parts = pathinfo($old_image_uri);
      if (isset($path_parts['basename']) and !empty($path_parts['basename'])) {
        $new_image_uri = $directory . '/' . $path_parts['basename'];
      }
      else {
        $new_image_uri = $directory . '/' . $old_image_name;
      }
      $file = system_retrieve_file($old_image_url, $new_image_uri, TRUE, FileSystemInterface::EXISTS_REPLACE);
      if ($file) {
        $file_id = $file->id();
        $media_check = $this->getMediaImageForFileNew($file_id);
        if (isset($media_check->entity_id) and !empty($media_check->entity_id)) {
          return $media_check->entity_id;
        }

        $drupal_media = Media::create([
          'bundle' => 'image',
          'langcode' => 'et',
          'uid' => \Drupal::currentUser()->id(),
          'name' => $old_image_name,
          'created' => $old_image_timestamp,
          'changed' => $old_image_timestamp,
          'field_media_image' => [
            'target_id' => $file_id,
            'alt' => $old_image_alt,
            'title' => $old_image_title,
          ],
          'field_catalog' => [
            'target_id' => $old_image_media_folder,
          ],
        ]);
        if ($drupal_media) {
          if ($old_image_status) {
            $drupal_media->setPublished();
          }
          else {
            $drupal_media->setUnpublished();
          }
          $drupal_media->save();
          return $drupal_media->id();
        }
      }
    }
  }
  public function getAndSaveVideo($video) {
    if (isset($video->file_managed[0]->uri) AND !empty($video->file_managed[0]->uri)) {
      $old_video_uri = $video->file_managed[0]->uri;
      $old_video_name = $video->file_managed[0]->filename;
      $old_video_timestamp = $video->file_managed[0]->timestamp;
      $old_video_status = $video->file_managed[0]->status;

      if (isset($video->field_data_field_folder[0]->name) and !empty($video->field_data_field_folder[0]->name)) {
        $old_video_media_folder = $this->getTermNewByName($video->field_data_field_folder[0]->name, 'media_catalogs');
      }
      else {
        $old_video_media_folder = $this->getTermNewByName('Media Root', 'media_catalogs');
      }
      $old_document_url = str_replace('youtube://v/', 'https://www.youtube.com/watch?v=', $old_video_uri);
      $media_check = $this->getMediaVideoForFileNew($old_document_url);
      if (isset($media_check->entity_id) and !empty($media_check->entity_id)) {
        return $media_check->entity_id;
      }
      $drupal_media = Media::create([
        'bundle' => 'remote_video',
        'langcode' => 'et',
        'uid' => \Drupal::currentUser()->id(),
        'name' => $old_video_name,
        'created' => $old_video_timestamp,
        'changed' => $old_video_timestamp,
        'field_media_oembed_video' => [
          'value' => $old_document_url,
        ],
        'field_catalog' => [
          'target_id' => $old_video_media_folder,
        ],
      ]);
      if ($drupal_media) {
        if ($old_video_status) {
          $drupal_media->setPublished();
        }
        else {
          $drupal_media->setUnpublished();
        }
        $drupal_media->save();
        return $drupal_media->id();
      }
    }
  }
  public function getAndSaveDocument($document) {
    $migrate_base_url = Settings::get('migrate_base_url', '');
    if (isset($document->file_managed[0]->uri) AND !empty($document->file_managed[0]->uri)) {
      $old_document_uri = $document->file_managed[0]->uri;
      $old_document_name = $document->file_managed[0]->filename;
      $old_document_timestamp = $document->file_managed[0]->timestamp;
      $old_document_status = $document->file_managed[0]->status;

      if (isset($document->field_data_field_folder[0]->name) and !empty($document->field_data_field_folder[0]->name)) {
        $old_document_media_folder = $this->getTermNewByName($document->field_data_field_folder[0]->name, 'media_catalogs');
      }
      else {
        $old_document_media_folder = $this->getTermNewByName('Media Root', 'media_catalogs');
      }

      $year_month = date('Y', $old_document_timestamp) . '-' . date('m', $old_document_timestamp);
      $directory = $this->configFactory->get('system.file')
          ->get('default_scheme') . '://' . $year_month;
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      $old_document_url = str_replace('public://', $migrate_base_url . '/sites/' . parse_url($migrate_base_url)['host'] . '/files/', $old_document_uri);
      $old_document_url_relative = str_replace('public://', '/sites/' . parse_url($migrate_base_url)['host'] . '/files/', $old_document_uri);

      $path_parts = pathinfo($old_document_uri);
      if (isset($path_parts['basename']) and !empty($path_parts['basename'])) {
        $new_document_uri = $directory . '/' . $path_parts['basename'];
      }
      else {
        $new_document_uri = $directory . '/' . $old_document_name;
      }
      $file = system_retrieve_file($old_document_url, $new_document_uri, TRUE, FileSystemInterface::EXISTS_REPLACE);
      if ($file) {
        $file_id = $file->id();
        $media_check = $this->getMediaDocumentForFileNew($file_id);
        if (isset($media_check->entity_id) and !empty($media_check->entity_id)) {
          return [
            $media_check->entity_id,
            $file->createFileUrl(),
            $old_document_url,
            $old_document_url_relative
          ];
        }
        $drupal_media = Media::create([
          'bundle' => 'document',
          'langcode' => 'et',
          'uid' => \Drupal::currentUser()->id(),
          'name' => $old_document_name,
          'created' => $old_document_timestamp,
          'changed' => $old_document_timestamp,
          'field_media_document' => [
            'target_id' => $file->id(),
          ],
          'field_catalog' => [
            'target_id' => $old_document_media_folder,
          ],
        ]);
        if ($drupal_media) {
          if ($old_document_status) {
            $drupal_media->setPublished();
          }
          else {
            $drupal_media->setUnpublished();
          }
          $drupal_media->save();
          return [
            $drupal_media->id(),
            $file->createFileUrl(),
            $old_document_url,
            $old_document_url_relative
          ];
        }
      }
    }
  }
  public function getAndSaveManagedImage($old_image_uri, $old_image_name, $sub_directory, $default_fid) {
    $migrate_base_url = Settings::get('migrate_base_url', '');
    if (isset($old_image_uri) and !empty($old_image_uri)) {
      $directory = $this->configFactory->get('system.file')
          ->get('default_scheme') . '://' . $sub_directory;
      $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

      $old_image_url = str_replace('public://', $migrate_base_url . '/sites/' . parse_url($migrate_base_url)['host'] . '/files/', $old_image_uri);

      $path_parts = pathinfo($old_image_uri);
      if (isset($path_parts['basename']) and !empty($path_parts['basename'])) {
        $new_image_uri = $directory . '/' . $path_parts['basename'];
      }
      else {
        $new_image_uri = $directory . '/' . $old_image_name;
      }
      $file = system_retrieve_file($old_image_url, $new_image_uri, TRUE, FileSystemInterface::EXISTS_REPLACE);
      if ($file) {
        $upload_fid = $file->id();
        #Remove file usage and mark it temporary, if new file uploaded.
        if ((!empty($default_fid) and !$upload_fid) or $default_fid != $upload_fid) {
          $file_default = File::load($default_fid);
          // Set the status flag temporary of the file object.
          if (!empty($file_default) and $file_default->isPermanent()) {
            $file_usage = \Drupal::service('file.usage');
            $file_usage->delete($file_default, 'harno_settings', 'node', 1);
            $file_default->setTemporary();
            $file_default->save();
          }
        }

        if ($file->isTemporary()) {
          $file->setPermanent();
          // Save the file in the database.
          $file->save();
          $file_usage = \Drupal::service('file.usage');
          $file_usage->add($file, 'harno_settings', 'node', 1);
        }
        return $upload_fid;
      }
    }
  }
  public function getAndSaveManagedDocument($old_doc_uri, $old_doc_name, $sub_directory = '') {
    $migrate_base_url = Settings::get('migrate_base_url', '');

    $directory = $this->configFactory->get('system.file')->get('default_scheme') . '://' . $sub_directory;
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    $old_doc_url = str_replace('public://', $migrate_base_url . '/sites/' . parse_url($migrate_base_url)['host'] . '/files/', $old_doc_uri);

    $path_parts = pathinfo($old_doc_uri);
    if (isset($path_parts['basename']) and !empty($path_parts['basename'])) {
      $new_doc_uri = $directory . '/' . $path_parts['basename'];
    } else {
      $new_doc_uri = $directory . '/' . $old_doc_name;
    }
    $file = system_retrieve_file($old_doc_url, $new_doc_uri, TRUE, FileSystemInterface::EXISTS_REPLACE);
    if ($file) {
      $upload_fid = $file->id();

      if ($file->isTemporary()) {
        $file->setPermanent();
        // Save the file in the database.
        $file->save();
      }
      return $upload_fid;
    }
  }
  public function searchDocsFromHtml($html) {
    #otsime sisusiseseid dokumente ja paneme need vastavatesse väljadesse
    $docs = [];
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    $selector = new DOMXPath($doc);
    $result_links = $selector->query('//a');
    foreach($result_links as $rl) {
      $href = $rl->getAttribute('href');
      if (strpos($href, '/files/')) {
        $tmp = explode('/files/', $href);
        if(isset($tmp[1]) AND !empty($tmp[1])) {
          $uri = 'public://' . ltrim($tmp[1], '/');
          $file_managed = $this->nodeOldFileDataByURI($uri);
          if(isset($file_managed[0]->fid) AND !empty($file_managed[0]->fid)) {
            if ($file_managed[0]->type == 'document' OR $file_managed[0]->type == 'audio' OR $file_managed[0]->type == 'pdf' ) {
              $field_data_field_attachment = (object) [];
              $field_data_field_attachment->field_attachments_fid = $file_managed[0]->fid;
              $docs[] = $field_data_field_attachment;
            }
          }
        }
      }
    }
    return $docs;
  }
  public function searchImagesFromHtml($html, $field = 'cp_image_fid') {
    #otsime sisusiseseid pilte ja paneme need vastavatesse väljadesse
    $images = [];
    $doc = new DOMDocument();
    $doc->loadHTML($html);
    $selector = new DOMXPath($doc);
    $result_links = $selector->query('//img');
    foreach($result_links as $rl) {
      $src = $rl->getAttribute('src');
      if (strpos($src, '/files/')) {
        $tmp = explode('/files/', $src);
        if(isset($tmp[1]) AND !empty($tmp[1])) {
          $uri = 'public://' . $tmp[1];
          $file_managed = $this->nodeOldFileDataByURI($uri);
          if(isset($file_managed[0]->fid) AND !empty($file_managed[0]->fid)) {
            if ($file_managed[0]->type == 'image') {
              $field_data_image = (object) [];
              $field_data_image->$field = $file_managed[0]->fid;
              $images[] = $field_data_image;
            }
          }
        }
      }
    }
    return $images;
  }
  public function updateNodeInternalLinks() {
    $ids = $this->storage->getQuery()->accessCheck(false)->execute();
    $storage_handler = $this->entityTypeManager->getStorage('node');
    $nodes = $storage_handler->loadMultiple($ids);
    $i = 0;
    $update_node_count = 0;
    foreach ($nodes as $node) {
      $update_body = FALSE;
      $nid = $node->id();
      if (isset($node->body->value) AND !empty($node->body->value)) {
        $body = $node->body->value;
        $doc = new DOMDocument();
        $doc->loadHTML($body);
        $selector = new DOMXPath($doc);
        $result_links = $selector->query('//a');
        foreach($result_links as $rl) {
          $new_url = '';
          $href = $rl->getAttribute('href');
          $parsed_url = parse_url($href);
          if(isset($parsed_url['path']) AND !empty($parsed_url['path'])) {
            $tmp = explode('/', $parsed_url['path'], 3);
            if (isset($tmp[2]) AND !empty($tmp[2])) {
              $path = str_replace(['%28', '%29', '%2C'], ['(', ')', ','], $tmp[2]);
              $old_node_url_alias = $this->getNodeOldByUrlAlias($path);
              if (isset($old_node_url_alias[0]->source) AND !empty($old_node_url_alias[0]->source)) {
                $tmp = explode('/', $old_node_url_alias[0]->source);
                if ($tmp[0] == 'node' AND is_numeric($tmp[1]) ) {
                  $old_node = $this->getNodeOldByNid($tmp[1]);
                  if (isset($old_node[0]->title) AND !empty($old_node[0]->title)) {
                    $new_node_ids = $this->getNodeNewByTitle($old_node[0]->title);
                    if (isset($new_node_ids) and !empty($new_node_ids)) {
                      foreach ($new_node_ids as $new_node_id) {
                        $new_url = Url::fromRoute('entity.node.canonical', ['node' => $new_node_id])->toString();
                        $body = str_replace($href, $new_url, $body);
                        $update_body = TRUE;
                      }
                    }
                  }
                }
              }
            }
            #$status_text =  '$href: ' . print_r($href, 1) .' $new_url: ' . $new_url . ' $update_body: ' . $update_body;
            #$this->messenger->addStatus($status_text);
          }
        }
        if ($update_body) {
          $node->body->value = $body;
          // If you indicate that you're updating an entity because you're *synchronizing* it
          // (migrating, importing from another site, … whichever reason you have!):
          $node->setSyncing(TRUE); // https://www.drupal.org/node/3250104
          $node->save();
          $update_node_count++;
        }
      }
    }
    $status_text =  'Uuendati sisemisi linke ' . $update_node_count . ' sisulehel.';
    $this->messenger->addStatus($status_text);
  }

  public function timeToSeconds(string $time) {
    $arr = explode(':', $time);
    if (count($arr) == 3) {
      return $arr[0] * 3600 + $arr[1] * 60 + $arr[2];
    } else {
      return $arr[0] * 60 + $arr[1];
    }
  }

  public function getAccessibilityNodeBody () {
    return '<h2>Ligipääsetavus käesoleval veebilehel</h2>
    <p class="large">Teie ees olev koduleht on ehitatud ja koostatud nii, et see vastaks WCAG 2.1 AA ligipääsetavuse suunistele. See tähendab, et on kasutatud teatud tehnilisi vahendeid ja sisu koostamise põhimõtteid, mis aitavad kodulehe sisu tarbida nägemis-, kuulmis-, füüsilise-, kõne-, tunnetusliku-, keele-, õppimis-, ja neuroloogiliste puudustega kasutajatel.</p>
    <p>Lisaks on võimalik info ligipääsetavust parandada kasutades lehel olevaid vaegnägija valikuid ning oma arvutit brauseri- ja operatsioonisüsteemi tasemel seadistades.</p>
    <p><a href="https://mcmw.abilitynet.org.uk/">Põhjalikum samateemaline juhend (inglise keeles)</a></p>
    <h3>Klaviatuuriga navigeerimine</h3>
    <p>Sellel kodulehel on võimalik navigeerida ka ainult klaviatuuri abil. Navigatsioon toimub Tab (tabulaator) klahvi ja noolteklahvide abil. Iga Tab klahvi vajutusega liigub fookus järgmisele aktiveeritavale elemendile. Hetkel aktiivset elementi märgib värvimuutus ja kastike selle ümber. Fookuses oleva lingi aktiveerimiseks tuleb vajutada klaviatuuril klahvi Enter.</p>
    <p>Esimene link, mis sellisel viisil navigeerides aktiivseks muutub, on mõeldud spetsiaalselt klaviatuuriga navigeerijatele: “Liigu edasi põhisisu juurde”. See link jätab vahele päise ja menüü ning viib teid lehe põhisisu juurde. Lehel olevalt kolmandalt “Ligipääsetavus” lingilt avanevad vaegnägijale suunatud valikud ning seal on ka viide käesolevale lehele.</p>
    <h3 id="Ligipääsetavuseavaldusveebilehtedele-Värvidemuutmine">Värvide muutmine</h3>
    <p>Käesoleval veebilehel on võimalik muuta sisu kontrastsust, et lugemist hõlbustada.</p>
    <p>Kõrgkontrastsesse vaatesse sisenemiseks liigu Tab klahviga või hiirega päises oleva lingini "Ligipääsetavus". Avanevates valikutes on võimalik klõpsata "Must-kollane" valikul, misjärel rakendub muutus automaatselt. Taust muutub mustaks, lingid ja tekst kollaseks.</p>
    <h3>Sisu suurendamine</h3>
    <h4 id="Ligipääsetavuseavaldusveebilehtedele-Käesolevveebileht">Käesolev veebileht</h4>
    <p>Sisu suurendamiseks soovitame kasutada lingilt "Ligipääsetavus" avanevaid valikuid. Valikutest saab muuta teksti suurust (keskmine, suur ja ülisuur) ning teksti vahesid (sõnade, lõikude, tähtede vahed). Valikute tegemisel rakenduvad need automaatselt.</p>
    <h4 id="Ligipääsetavuseavaldusveebilehtedele-Veebilehitsejad">Veebilehitsejad</h4>
    <p>Kõikides populaarsetes veebilehitsejates on võimalik lehte suurendada ja vähendada, kui hoida all Ctrl klahvi (OS X operatsioonisüsteemis Cmd klahvi) ja samal vajutada ajal kas + või - klahvi. Teine mugav võimalus on kasutada hiirt: hoides all Ctrl klahvi ja samal ajal liigutades hiire kerimisrulli. Tagasi normaalsuurusesse saab, kui vajutada samaaegselt Ctrl ja 0 klahvile.</p>
    <h4 id="Ligipääsetavuseavaldusveebilehtedele-Veebilehitsejalaiendused">Veebilehitseja laiendused</h4>
    <p>Veebilehitsejate jaoks on olemas suurendamist võimaldavad laiendused, mis täiendavad veebilehitseja olemasolevat funktsionaalsust. Näiteks Firefoxi jaoks <a href="https://addons.mozilla.org/en-US/firefox/addon/zoom-page-we/">“Zoom Page”</a>, mis lubab suurendada nii kogu lehte kui ka ainult teksti; Chrome\'i jaoks <a href="https://chrome.google.com/webstore/detail/auto-zoom/dcicehbfkfjclggmmgpknoolmfagepph">AutoZoom</a>.</p>
    <h3 id="Ligipääsetavuseavaldusveebilehtedele-Ekraanilugejakasutamine">Ekraanilugeja kasutamine</h3>
    <p>Ekraanilugeja on programm, mis üritab arvutiekraanil kujutatavat interpreteerida ja teistes vormides edasi anda - näiteks helidena, audiokommentaarina. Eelkõige on see abivahend vaegnägijate jaoks.</p>
    <p>Valik populaarseid ekraanilugejaid:</p>
    <ul>
      <li>JAWS (Windows) <a href="https://www.freedomscientific.com/">https://www.freedomscientific.com/</a></li>
      <li>VoiceOver (OS X, tasuta, sisseehitatud)</li>
      <li>NVDA (Windows, tasuta) <a href="https://www.nvaccess.org/download/">https://www.nvaccess.org/download/</a></li>
      <li>SystemAccess (Windows) <a href="https://www.serotek.com/systemaccess">https://www.serotek.com/systemaccess</a></li>
    </ul>
    <p>Ekraanilugerite soovituslikud kasutused koos veebilehitsejaga:</p>
    <ul>
      <li>VoiceOver + Safari</li>
      <li>Jaws + Chrome</li>
      <li>NVDA + Firefox</li>
    </ul>
    <h3 id="Ligipääsetavuseavaldusveebilehtedele-Tagasisidejakontaktandmed">Tagasiside ja kontaktandmed</h3>
    <p>Teie kogemused aitavad meil veebisaidi ligipääsetavust veelgi parandada.</p>
    <p>Palun andke teada, kui teil tekib probleeme, vajate juurdepääsu kättesaamatule teabele või peate mõnda funktsiooni eriti kasulikuks. Vastame teile niipea kui võimalik.</p>
    <p>Kontakt: veebiplatvorm@hm.ee</p>';
  }
}
