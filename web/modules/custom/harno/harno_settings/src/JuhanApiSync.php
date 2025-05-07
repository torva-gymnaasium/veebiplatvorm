<?php

namespace Drupal\harno_settings;

use \Drupal\Core\Config\ConfigFactoryInterface;
use \Drupal\Core\Logger\LoggerChannelFactoryInterface;
use \Drupal\Core\Messenger\MessengerInterface;
use \Drupal\Component\Serialization\Json;
use \Drupal\node\Entity\Node;
use \Drupal\Core\Entity\EntityTypeManager;
use \Drupal\paragraphs\Entity\Paragraph;
use \Drupal\Core\StringTranslation\StringTranslationTrait;
use \Drupal\Core\StringTranslation\TranslationInterface;
use \Drupal\Core\State\StateInterface;
use \GuzzleHttp\ClientInterface;
use \GuzzleHttp\Exception\RequestException;

/**
 * Class DefaultService.
 *
 * @package Drupal\JuhanApiSync
 */

class JuhanApiSync {
  use StringTranslationTrait;
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
     * Constructs an Importer object.
     *
     * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
     *   The factory for configuration objects.
  */

  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client,
                              LoggerChannelFactoryInterface $loggerFactory, MessengerInterface $messenger,
                              TranslationInterface $string_translation, EntityTypeManager $entity_type_manager,
                              StateInterface $state) {
    $this->configFactory = $config_factory;
    $this->httpClient = $http_client;
    $this->logger = $loggerFactory->get('harno_settings');
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
    $this->storage = $entity_type_manager->getStorage('node');
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
  }

  public function cron() {
    $now = \Drupal::time()->getRequestTime();
    if ($this->shouldRun($now)) {
      $this->syncTrainings();
    }
  }

  public function shouldRun($now) {

    $scheduled = '23:00';
    $timezone_name = $this->configFactory->get('system.date')->get('timezone.default');
    $timezone = new \DateTimeZone($timezone_name);

    $timestamp_last = $this->state->get('harno_settings.juhan_api_sync_last_run') ?? 0;
    $last = \DateTime::createFromFormat('U', $timestamp_last)->setTimezone($timezone);
    $next = clone $last;
    $scheduled_time = explode(':', $scheduled);
    $next->setTime($scheduled_time[0], $scheduled_time[1]);
    // If the cron ran on the same calendar day it should have, add one day.
    if ($next->getTimestamp() <= $last->getTimestamp()) {
      $next->modify('+1 day');
    }
    return $next->getTimestamp() <= $now;
  }

  public function syncTrainings($manual = FALSE) {
    $status_text = 'Juhani API sync started at ' . date('H:i:s').'.';
    $this->logger->notice($status_text);
    if($manual) {
      $this->messenger->addStatus($status_text);
    }
    $training_status = [];
    $info = [
      'added' => 0,
      'updated' => 0,
      'not_changed' => 0,
      'skipped' => 0,
      'deleted' => 0,
    ];
    $def_status =  [
      'A' => 'Archived',
      'C' => 'Canceled',
      'D' => 'Deleted',
      'F' => 'Finished',
      'H' => 'Hidden',
      'N' => 'New',
      'P' => 'Published',
      'R' => 'Registration open',
      'X' => 'Unknown',
    ];
    try {
      $request = $this->httpClient->get('https://koolitus.edu.ee/api/trainings', [
        'headers' => [
          'Authorization' => $this->configFactory->get('harno_settings.settings')->get('juhan.api_key')
        ]
      ]);
      if ($request->getStatusCode() != 200) {
        $status_text = 'Juhani API sync failed with status code ' . $request->getStatusCode();
        $this->logger->error($status_text);
        if($manual) {
          $this->messenger->addError($status_text);
        }
      } else {
        $data = $request->getBody()->getContents();
        $juhan_trainings = Json::decode($data);
        $trainings_ids = $this->storage->getQuery()
          ->condition('type', 'calendar')
          ->condition('field_juhan_training', TRUE)
          ->accessCheck(FALSE)
          ->execute();

        $drupal_trainings = $this->storage->loadMultiple($trainings_ids);

        foreach ($juhan_trainings as $t) {
          $node = '';
          if(isset($training_status[$t['status']])) {
            $training_status[$t['status']]++;
          } else {
            $training_status[$t['status']] = 1;
          }
          $found = FALSE;
          foreach ($drupal_trainings as $t_node) {
            if ($t_node->field_juhan_id->getValue()[0]['value'] == $t['id']) {
              $found = TRUE;
              $node = $t_node;
            }
          }
          if ( in_array($t['status'], ['P', 'R', 'F', 'C']) ) {
            if($t['status'] == 'F' AND !$found) {
              $info['skipped']++;
              continue;
            }
            $address = $price = $body = $reg_start_date = $reg_end_date = $start_date =
            $end_date = $image_link = $contact_name = $contact_phone = $contact_email = '';

            $show_end_date = TRUE;
            ######################## Title #############################
            $title = $t['courseDescription']['trainingName'];
            if ($t['status'] == 'C') {
              $title = $this->t('CANCELED') . ': ' .$title;
            }
            $title = substr($title, 0, 255);
            ######################## Address #############################
            if (isset($t['address']['addressDetails']) AND !empty($t['address']['addressDetails'])) {
              $address .= $t['address']['addressDetails'] . ', ';
            }
            if (isset($t['address']['city']) AND !empty($t['address']['city'])) {
              $address .= $t['address']['city'] . ', ';
            }
            if (isset($t['address']['county']) AND !empty($t['address']['county'])) {
              $address .= $t['address']['county'] . ', ';
            }
            if (isset($t['address']['country']) AND !empty($t['address']['country'])) {
              $address .= $t['address']['country'] . ', ';
            }
            if (empty($address)) {
              if (isset($t['institution']['institutionAddress']['addressDetails']) AND !empty($t['institution']['institutionAddress']['addressDetails'])) {
                $address .= $t['institution']['institutionAddress']['addressDetails'] . ', ';
              }
              if (isset($t['institution']['institutionAddress']['city']) AND !empty($t['institution']['institutionAddress']['city'])) {
                $address .= $t['institution']['institutionAddress']['city'] . ', ';
              }
              if (isset($t['institution']['institutionAddress']['county']) AND !empty($t['institution']['institutionAddress']['county'])) {
                $address .= $t['institution']['institutionAddress']['county'] . ', ';
              }
              if (isset($t['institution']['institutionAddress']['country']) AND !empty($t['institution']['institutionAddress']['country'])) {
                $address .= $t['institution']['institutionAddress']['country'] . ', ';
              }
            }
            $address = substr(chop($address, ', '), 0, 255);
            ######################## Price #############################
            if (isset($t['price'])) {
              if (!empty($t['price']) AND $t['price'] >= 0.01) {
                $price = $t['price'];
              } else {
                $price = $this->t('FREE');
              }
            }
            ######################## Body #############################
            if (isset($t['courseDescription']['lead']) AND !empty($t['courseDescription']['lead'])) {
              $body .= '<p>'.$t['courseDescription']['lead'] .'</p>';
            }
            if (isset($t['courseDescription']['targetGroupText']) AND !empty($t['courseDescription']['targetGroupText'])) {
              $body .= '<p><strong>'.$this->t('Target groups').':</strong><br/>' . $t['courseDescription']['targetGroupText'].'</p>';
            }
            if (isset($t['trainers']) AND !empty($t['trainers'])) {
              $body_trainers = '';
              $test_trainers = [];
              foreach ($t['trainers'] as $tr) {
                $name = $tr['firstName']. ' '. $tr['familyName'];
                if (!isset($test_trainers[$name])) {
                  $body_trainers .= $name . '<br/>';
                  $test_trainers[$name] = 1;
                }
              }

              if(count($test_trainers) > 1){
                $body .= '<p><strong>'.$this->t('Trainers').':</strong><br/>';
              } else {
                $body .= '<p><strong>'.$this->t('Trainer').':</strong><br/>';
              }

              $body .= $body_trainers . '</p>';
            }
            if (isset($t['courseDescription']['academicHours']) AND !empty($t['courseDescription']['academicHours'])) {
              $body .= '<p><strong>'.$this->t('Quantity').':</strong> ' . $t['courseDescription']['academicHours'].' '.$this->t('academic hours').'<br/>';

              if (isset($t['courseDescription']['academicHoursAuditorium']) AND !empty($t['courseDescription']['academicHoursAuditorium'])) {
                $body .= $this->t('Auditory work').': ' . $t['courseDescription']['academicHoursAuditorium'].' '.$this->t('academic hours').'<br/>';
              }
              if (isset($t['courseDescription']['academicHoursPractical']) AND !empty($t['courseDescription']['academicHoursPractical'])) {
                $body .= $this->t('Practical work').': ' . $t['courseDescription']['academicHoursPractical'].' '.$this->t('academic hours').'<br/>';
              }
              if (isset($t['courseDescription']['academicHoursIndependent']) AND !empty($t['courseDescription']['academicHoursIndependent'])) {
                $body .= $this->t('Independent work').': ' . $t['courseDescription']['academicHoursIndependent'].' '.$this->t('academic hours').'<br/>';
              }
              $body .= '</p>';
            }

            if ($t['status'] != 'C' AND $t['status'] != 'P') {
              if (isset($t['registrationOpened']) AND !empty($t['registrationOpened'])) {
                $reg_start_date = strtotime($t['registrationOpened']);
                if (isset($t['registrationClosed']) and !empty($t['registrationClosed'])) {
                  $reg_end_date = strtotime($t['registrationClosed']);
                }
                if (isset($reg_end_date) and !empty($reg_end_date)) {
                  if($reg_end_date > time()) {
                    $body .= '<p><strong>' . $this->t('Registration begins') . ': </strong>' . date('d.m.Y', $reg_start_date) . '<br/>';
                    if (isset($t['registrationClosed']) and !empty($t['registrationClosed'])) {
                      $body .= '<strong>' . $this->t('Registration ends') . ': </strong>' . date('d.m.Y', $reg_end_date) . '</p>';
                    }
                    else {
                      $body .= '</p>';
                    }
                  } else {
                    $body .= '<p><strong>' . $this->t('Registration is over!') . '</strong></p>';
                  }
                }
              }
            }
            ######################## Start date #############################
            if (isset($t['startingDate']) AND !empty($t['startingDate'])) {
              $start_date = $t['startingDate'];
            }
            ######################## End date #############################
            if (isset($t['endingDate']) AND !empty($t['endingDate'])) {
              $end_date = $t['endingDate'];
            }
            ######################## Show end date #############################
            if ($start_date == $end_date) {
              $show_end_date = FALSE;
              $end_date = '';
            }
            ######################## Image link #############################
            if (isset($t['courseDescription']['socialMediaPicLink']) AND !empty($t['courseDescription']['socialMediaPicLink'])) {
              $image_link = $t['courseDescription']['socialMediaPicLink'];
            }
            ######################## Contact #############################
            if (isset($t['courseDescription']['projectManager']['firstName']) AND !empty($t['courseDescription']['projectManager']['firstName'])) {
              $contact_name = $t['courseDescription']['projectManager']['firstName'];
            }
            if (isset($t['courseDescription']['projectManager']['familyName']) AND !empty($t['courseDescription']['projectManager']['familyName'])) {
              $contact_name .= ' ' . $t['courseDescription']['projectManager']['familyName'];
            }
            if (isset($t['courseDescription']['projectManager']['phone']) AND !empty($t['courseDescription']['projectManager']['phone'])) {
              $contact_phone = $t['courseDescription']['projectManager']['phone'];
            }
            if (isset($t['courseDescription']['projectManager']['email']) AND !empty($t['courseDescription']['projectManager']['email'])) {
              $contact_email = $t['courseDescription']['projectManager']['email'];
            }
            if ($found) {
              $node->setTitle($title);
            }
            else {
              $node = Node::create([
                'type'        => 'calendar',
                'title'       => $title,
                'langcode'    => 'und',
              ]);
              if(isset($image_link) AND !empty($image_link)) {
                if($this->isFileUrlExists($image_link)){
                  $node->field_juhan_image_link = ["uri" => $image_link, "title" => "", "options" =>[ 'attributes' => ['target' => '_blank']]];
                }
              }
            }
            $node->field_event_type->value = 1; #Koolitus
            $node->field_juhan_training->value = TRUE;
            $node->field_juhan_id->value = $t['id'];
            $node->field_juhan_esf->value = $t['esf'];
            $node->field_juhan_training_url = ["uri" => $t['publicUrl'], "title" => "", "options" =>[ 'attributes' => ['target' => '_blank']]];

            if(isset($address) AND !empty($address)) {
              $node->field_venue->value = $address;
            }
            elseif(isset($node->field_venue->value) AND !empty($node->field_venue->value)) {
              $node->field_venue->value = '';
            }
            if(isset($price) AND !empty($price)) {
              $node->field_price->value = $price;
            }
            elseif(isset($node->field_price->value) AND !empty($node->field_price->value)) {
              $node->field_price->value = '';
            }
            if(isset($body) AND !empty($body)) {
              $node->body->value = $body;
              $node->body->format = 'full_html';
            }
            elseif(isset($node->body->value) AND !empty($node->body->value)) {
              $node->body->value = '';
            }
            $node->field_show_end_date->value = $show_end_date;
            $node->field_all_day->value = TRUE;
            if(isset($start_date) AND !empty($start_date)) {
              $node->field_start_date->value = $start_date;
            }
            elseif(isset($node->field_start_date->value) AND !empty($node->field_start_date->value)) {
              $node->field_start_date->value = '';
            }
            if(isset($end_date) AND !empty($end_date)) {
              $node->field_event_end_date->value = $end_date;
            }
            elseif(isset($node->field_event_end_date->value) AND !empty($node->field_event_end_date->value)) {
              $node->field_event_end_date->value = '';
            }
            if(isset($contact_name) AND !empty($contact_name)) {
              $result = $node->get('field_contact_block')->referencedEntities();
              if (!empty($result)) {
                foreach ($result as $paragraph) {
                  $paragraph->field_contact_title->value = $this->t('Contact person');
                  $paragraph->field_name->value = $contact_name;
                  $paragraph->field_phone[0] = ['value' => $contact_phone];
                  $paragraph->field_email->value = $contact_email;
                  $paragraph->save();
                }
              } else {
                $contact_paragraph = Paragraph::create([
                  'type' => 'contact_block',
                  'field_contact_title' => $this->t('Contact person'),
                  'field_name' => $contact_name,
                  'field_phone' => [$contact_phone],
                  'field_email' => $contact_email,
                ]);
                $contact_paragraph->save();
                $node->field_contact_block = [
                  [
                    'target_id' => $contact_paragraph->id(),
                    'target_revision_id' => $contact_paragraph->getRevisionId(),
                  ]
                ];
              }
            } elseif(isset($node->field_contact_block->target_id) AND !empty($node->field_contact_block->target_id)) {
              unset($node->field_contact_block);
            }

            if ($found) {
              if ($this->isNodeChanged($node)) {
                $node->save();
                $info['updated']++;
              }
              else {
                $info['not_changed']++;
              }
            } else {
              $node->save();
              $info['added']++;
            }
          }
          elseif ($found) {
            $node->delete();
            $info['deleted']++;
          }
          else {
            $info['skipped']++;
          }
        }
      }
    }
    catch (RequestException $e) {
      $status_text = 'Juhani API sync failed with error message: ' . $e->getMessage();
      $this->logger->error($status_text);
      if($manual) {
        $this->messenger->addError($status_text);
      }
    }
    $total_juhan_training = $total_drupal_training = 0;
    $status_text = 'Juhani API training statuses: ';
    foreach($training_status as $key => $item) {
      $status_text .= $key.' ('. $def_status[$key].') = '.$item.', ';
      $total_juhan_training += $item;
    }
    $status_text = chop($status_text, ', '). '. Total ' . $total_juhan_training . ' trainings.';

    $status_text_1 = 'Juhani API Drupal operations performed: ';
    foreach($info as $key => $item) {
      $status_text_1 .= $key.' '.$item.', ';
      $total_drupal_training += $item;
    }
    $status_text_1 = chop($status_text_1, ', '). ' trainings. Total ' . $total_drupal_training . ' trainings.';

    $status_text_2 = 'Juhani API sync ended at ' .date('H:i:s').'.';
    $this->logger->notice($status_text);
    $this->logger->notice($status_text_1);
    $this->logger->notice($status_text_2);
    if($manual) {
      $this->messenger->addStatus($status_text);
      $this->messenger->addStatus($status_text_1);
      $this->messenger->addStatus($status_text_2);
    }
    $now = \Drupal::time()->getRequestTime();
    $this->state->set('harno_settings.juhan_api_sync_last_run', $now);
  }
  /**
   * Check if node has changed after updating fields.
   */
  public function isNodeChanged(Node $node) {
    $original_node = $this->storage->loadUnchanged($node->id());
    $fields = $this->getFields();
    $node_fields = array_intersect_key($node->toArray(), array_flip($fields));
    $original_node_fields = array_intersect_key($original_node->toArray(), array_flip($fields));
    return $node_fields != $original_node_fields;
  }
  /**
   * Return the fields to check against.
   */
  public function getFields() {
    return [
      'field_juhan_esf',
      'field_juhan_training_url',
      'title',
      'body',
      'field_venue',
      'field_price',
      'field_show_end_date',
      'field_start_date',
      'field_event_end_date',
      'field_contact_block',
    ];
  }

  public function isFileUrlExists($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if( $httpCode == 200 ){
      return true;
    } else {
      return false;
    }
  }
}
