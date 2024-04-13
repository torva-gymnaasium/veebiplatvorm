<?php

namespace Drupal\harno_pages\Controller;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\File\FileSystemInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxy;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\File\FileSystem;
use DateTime;
use DateTimeZone;

/**
 * An example controller.
 */
class ICSController extends ControllerBase {

  protected $file;

  protected $entity;

  protected $currentUser;

  public $request;

  /**
   * Constructor.
   */
  public function __construct(EntityStorageInterface $entityStorage, AccountProxy $currentuser, RequestStack $request, FileSystem $fileStorage) {
    $this->entity = $entityStorage;
    $this->currentUser = $currentuser;
    $this->request = $request;
    $this->file = $fileStorage;
  }

  /**
   * Create dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager')->getStorage('node'),
      $container->get('current_user'),
      $container->get('request_stack'),
      $container->get('file_system')
    );

  }

  /**
   * Borrowed from Kent Shelley via 'File Download'.
   *
   * Project https://www.drupal.org/project/file_download.
   *
   */
  public function download($nid) {

    // Load the node.
    $node = \Drupal::entityTypeManager()->getStorage('node')->load($nid);
    if (empty($node)) {
      return FALSE;
    }

    $start_date_field = 'field_start_date';
    $end_date_field = 'field_event_end_date';
    $start_date = date('Y-m-d H:i:s', strtotime($node->{$start_date_field}->value));
    $end_date = date('Y-m-d H:i:s', strtotime($node->{$end_date_field}->value));

    $tz = 'Europe/Tallinn';
    $timestamp = time();

    $start_sate = strtotime($node->get('field_start_date')->value) + $node->get('field_event_start_time')->value;
//    $dt = new DateTime("now"); //first argument "must" be a string
////    $dt = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
//    $dt->setTimestamp($start_sate); //adjust the object to correct timestamp
    $dt = date('Ymd', $start_sate).'';

    if($node->get('field_show_end_date')->value == 1) {
      $end_datetime = strtotime($node->get('field_event_end_date')->value) + $node->get('field_event_end_time')->value + (24*3600);
//      $et = new DateTime("now"); //first argument "must" be a string
////      $et = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
//      $et->setTimestamp($end_datetime); //adjust the object to correct timestamp
      $et = date('Ymd', $end_datetime).'';
    }
    else{
      $end_datetime = strtotime($node->get('field_start_date')->value) + $node->get('field_event_end_time')->value + (24*3600);
//      $et = new DateTime("now"); //first argument "must" be a string
////      $et = new DateTime("now", new DateTimeZone($tz)); //first argument "must" be a string
//      $et->setTimestamp($end_datetime); //adjust the object to correct timestamp
      $et = date('Ymd', $end_datetime).'';
    }

    $all_day = $node->get('field_all_day')->value;
    $ad = TRUE;
    if(!$all_day){
      $ad = FALSE;
      $start_sate = strtotime($node->get('field_start_date')->value) + $node->get('field_event_start_time')->value;
      $dt = date('Ymd\THis', $start_sate).'';
      if($node->get('field_show_end_date')->value == 1) {
        $end_datetime = strtotime($node->get('field_event_end_date')->value) + $node->get('field_event_end_time')->value;
        $et = date('Ymd\THis', $end_datetime).'';
      }
      else{
        $end_datetime = strtotime($node->get('field_start_date')->value) + $node->get('field_event_end_time')->value;
        $et = date('Ymd\THis', $end_datetime).'';
      }
    }
    $title = $node->getTitle();
    $from = $dt;
    $to = $et;
    $price = empty($node->get('field_price')->value) ? '' : $node->get('field_price')->value;
    $url = $node->toUrl()->setAbsolute()->toString();
    if (!empty($node->get('body')->value)) {
      $text = preg_replace("/\r|\n/", "", $node->body->view('full')[0]['#text']);
    }
    $description = $text . '<p><strong>'.t('Link').': </strong>' .$url . '</p><p><strong>'.t('Price').': </strong>' . $price .'</p>';
    $address = $node->get('field_venue')->value;
    $uuid_service = \Drupal::service('uuid');
    $uuid = $uuid_service->generate();

    //Duration
    $date1 = new DateTime($from);
    $date2 = new DateTime($to);
    $interval = $date1->diff($date2);
    $duration = "P".$interval->days.'D';

    $filename = 'cal-' . $nid . '.ics';
    $uri = 'public://' . $filename;
    if(!$all_day){
      $content =
"BEGIN:VCALENDAR
PRODID:-//Microsoft Corporation//Outlook 12.0 MIMEDIR//EN
VERSION:2.0
CALSCALE:GREGORIAN
BEGIN:VEVENT
UID: ".$uuid."
SUMMARY:".$title."
DTSTAMP:".$from."
DTSTART:".$from."
DTEND:".$to."
LOCATION:".$address."
DESCRIPTION: ".$description."
STATUS:CONFIRMED
SEQUENCE:3
X-ALT-DESC;FMTTYPE=text/html:".$description."
X-MICROSOFT-CDO-BUSYSTATUS:OOF
X-MICROSOFT-CDO-IMPORTANCE:1
X-MICROSOFT-DISALLOW-COUNTER:FALSE
X-MS-OLK-ALLOWEXTERNCHECK:TRUE
X-MS-OLK-CONFTYPE:0
X-MICROSOFT-CDO-ALLDAYEVENT:".$ad."
X-MICROSOFT-MSNCALENDAR-ALLDAYEVENT:".$ad."
END:VEVENT
END:VCALENDAR";
    }
    else{
      $content =
"BEGIN:VCALENDAR
PRODID:-//Microsoft Corporation//Outlook 12.0 MIMEDIR//EN
VERSION:2.0
CALSCALE:GREGORIAN
BEGIN:VEVENT
UID: ".$uuid."
SUMMARY:".$title."
DTSTAMP:".$from."
DTSTART:".$from."
DTEND:".$to."
DURATION:".$duration."
LOCATION:".$address."
DESCRIPTION: ".$description."
STATUS:CONFIRMED
SEQUENCE:3
X-ALT-DESC;FMTTYPE=text/html:".$description."
X-MICROSOFT-CDO-BUSYSTATUS:OOF
X-MICROSOFT-CDO-IMPORTANCE:1
X-MICROSOFT-DISALLOW-COUNTER:FALSE
X-MS-OLK-ALLOWEXTERNCHECK:TRUE
X-MS-OLK-CONFTYPE:0
X-MICROSOFT-CDO-ALLDAYEVENT:".$ad."
X-MICROSOFT-MSNCALENDAR-ALLDAYEVENT:".$ad."
END:VEVENT
END:VCALENDAR";
    }
    $file = \Drupal::service('file.repository')->writeData($content,$uri, FileSystemInterface::EXISTS_REPLACE);
    if (empty($file)) {
      return new Response(
        'File generation error, Please contact the System Administrator'
      );
    }
    $mimetype = 'text/calendar';
    $scheme = 'public';
    $parts = explode('://', $uri);
    $file_directory = \Drupal::service('file_system')->realpath(
      $scheme . "://"
    );
    $filepath = $file_directory . '/' . $parts[1];
    $filename = $file->getFilename();

    // File doesn't exist
    // This may occur if the download path is used outside of a formatter
    // and the file path is wrong or file is gone.
    if (!file_exists($filepath)) {
      throw new NotFoundHttpException();
    }

    $headers = [
      'Content-Type' => $mimetype,
      'Content-Disposition' => 'attachment; filename="' . $filename . '"',
      'Content-Length' => $file->getSize(),
      'Content-Transfer-Encoding' => 'binary',
      'Pragma' => 'no-cache',
      'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
      'Expires' => '0',
      'Accept-Ranges' => 'bytes',
    ];

    // \Drupal\Core\EventSubscriber\FinishResponseSubscriber::onRespond()
    // sets response as not cacheable if the Cache-Control header is not
    // already modified. We pass in FALSE for non-private schemes for the
    // $public parameter to make sure we don't change the headers.
    return new BinaryFileResponse($uri, 200, $headers, $scheme !== 'private');
  }

}
