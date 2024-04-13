<?php


namespace Drupal\harno_blocks\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\media\Entity\Media;

/**
 * Provides an example block.
 *
 * @Block(
 *   id = "harno_front_page_events_block",
 *   admin_label = @Translation("Events and trainings"),
 *   category = @Translation("harno_blocks")
 * )
 */
class EventsFrontPage  extends  BlockBase{
  public function build() {
    $build = array();

    $build['#cache'] = [
      'tags' => [
        'node_list:calendar',
      ],
    ];
    $block_configuration = $this->getConfiguration();
    $node = \Drupal::routeMatch()->getParameter('node');
    $limit =4;
    $bundle = 'calendar';
    $today = strtotime('today midnight');
    $today = date('Y-m-d',$today);
    $query = \Drupal::entityQuery('node');
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    $query->condition('status', 1);
    $languageConditions = $query->orConditionGroup();
    $languageConditions->condition('langcode',$language);
    $languageConditions->condition('langcode','und');
    $query->condition($languageConditions);
    $query->condition('type', $bundle);
    if (isset($block_configuration['event_type'])){
      if ($block_configuration['event_type']=='events'){
        $event_type = 2;
      }
      elseif($block_configuration['event_type']=='trainings'){
        $event_type = 1;
      }
      if (isset($event_type)) {
        $query->condition('field_event_type',$event_type,'=');
        }
    }
    $nids = $query->accessCheck(true)->execute();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $contents =  $node_storage->loadMultiple($nids);
    $content_data = [];
    $filtered_events = [];
    foreach ($contents as $content){
      if ($content->hasField('field_event_end_date')){
        if (!empty($content->get('field_event_end_date')->value)) {
          if (strtotime($content->get('field_event_end_date')->value) < strtotime($today)) {
            continue;
          }
        }
        else{
          if (strtotime($content->get('field_start_date')->value) < strtotime($today)) {
            continue;
          }
        }
      }
      $filtered_events[$content->id()]=[
        'nid'=>$content->id(),
        'start_date'=>$content->get('field_start_date')->value,
        'start_time'=>$content->hasField('field_event_start_time')?$content->get('field_event_start_time')->value:"",
        'end_date'=> (($content->hasField('field_event_end_date')))? $content->get('field_event_end_date')->value:'',
        'end_time'=> (($content->hasField('field_event_end_time')))? $content->get('field_event_end_time')->value:'',
        'event'=>$content,
      ];
    }
    if (isset($block_configuration['event_type'])){
      if ($block_configuration['event_type'] == 'trainings') {
        uasort($filtered_events, function ($a, $b) {
          $today = strtotime('today midnight');
          $time = $today;
//      $today = date('Y-m-d',$today);
          $astart= strtotime($a['start_date']);
          if (!empty($a['end_date'])){
            $aend = strtotime($a['end_date']);
          }
          else{
            $aend =strtotime($a['start_date']);
          }
          if (!empty($b['end_date'])){
            $bend = strtotime($b['end_date']);
          }
          else{
            $bend =strtotime($b['start_date']);
          }
          $bstart= strtotime($b['start_date']);
          if ($astart==$bstart) {
            if ($astart == $aend && $bstart == $bend) {
              if (isset($a['start_time']) && isset($b['start_time'])) {
                if ($a['start_time'] < $b['start_time']) {
                  return -1;
                } elseif ($a['start_time'] > $b['start_time']) {
                  return 1;
                }
              }
            }
          }
          if ($astart==$today && $bstart!=$today){
            return -1;
          }
          elseif ($bstart==$today && $astart!=$today){
            return 1;
          }
          elseif($astart==$today && $bstart==$today){
            if ($astart==$aend && $bstart !=$bend){
              return -1;
            }
            elseif ($bstart==$bend && $astart!=$aend){
              return 1;
            }

          }
          if ($astart<$bstart){
            if ($astart<$today && $bstart>$today){
              return 1;
            }
            if ($astart<$bstart && $aend>$bstart){
              return 1;
            }
            return -1;
          }
          elseif($bstart<$astart){
            if ($bstart<$today && $astart>$today){
              return -1;
            }
            if ($bstart<$astart && $bend>$astart){
              return -1;
            }
            return 1;
          }

        });
      }
      else {
        uasort($filtered_events, function ($a, $b) {
          $today = strtotime('today midnight');
          $time = $today;
//      $today = date('Y-m-d',$today);
          $astart= strtotime($a['start_date']);
          if (!empty($a['end_date'])){
            $aend = strtotime($a['end_date']);
          }
          else{
            $aend =strtotime($a['start_date']);
          }
          if (!empty($b['end_date'])){
            $bend = strtotime($b['end_date']);
          }
          else{
            $bend =strtotime($b['start_date']);
          }
          $bstart= strtotime($b['start_date']);
          if ($astart==$bstart) {
            if ($astart == $aend && $bstart == $bend) {
              if (isset($a['start_time']) && isset($b['start_time'])) {
                if ($a['start_time'] < $b['start_time']) {
                  return -1;
                } elseif ($a['start_time'] > $b['start_time']) {
                  return 1;
                }
              }
            }
          }
          if ($astart==$today && $bstart!=$today){
            return -1;
          }
          elseif ($bstart==$today && $astart!=$today){
            return 1;
          }
          elseif($astart==$today && $bstart==$today){
            if ($astart==$aend && $bstart !=$bend){
              return -1;
            }
            elseif ($bstart==$bend && $astart!=$aend){
              return 1;
            }

          }
          if ($astart<$bstart){
            if ($astart<$today && $bstart>$today){
              return -1;
            }
            if ($astart<$bstart && $aend>$bstart){
              return 1;
            }
            return -1;
          }
          elseif($bstart<$astart){
            if ($bstart<$today && $astart>$today){
              return 1;
            }
            if ($bstart<$astart && $bend>$astart){
              return -1;
            }
            return 1;
          }

        });
      }
    }
    else{
      uasort($filtered_events, function ($a, $b) {
        $today = strtotime('today midnight');
        $time = $today;
//      $today = date('Y-m-d',$today);
        $astart= strtotime($a['start_date']);
        if (!empty($a['end_date'])){
          $aend = strtotime($a['end_date']);
        }
        else{
          $aend =strtotime($a['start_date']);
        }
        if (!empty($b['end_date'])){
          $bend = strtotime($b['end_date']);
        }
        else{
          $bend =strtotime($b['start_date']);
        }
        $bstart= strtotime($b['start_date']);
        if ($astart==$bstart) {
          if ($astart == $aend && $bstart == $bend) {
            if (isset($a['start_time']) && isset($b['start_time'])) {
              if ($a['start_time'] < $b['start_time']) {
                return -1;
              } elseif ($a['start_time'] > $b['start_time']) {
                return 1;
              }
            }
          }
        }
        if ($astart==$today && $bstart!=$today){
          return -1;
        }
        elseif ($bstart==$today && $astart!=$today){
          return 1;
        }
        elseif($astart==$today && $bstart==$today){
          if ($astart==$aend && $bstart !=$bend){
            return -1;
          }
          elseif ($bstart==$bend && $astart!=$aend){
            return 1;
          }

        }
        if ($astart<$bstart){
          if ($astart<$today && $bstart>$today){
            return -1;
          }
          if ($astart<$bstart && $aend>$bstart){
            return 1;
          }
          return -1;
        }
        elseif($bstart<$astart){
          if ($bstart<$today && $astart>$today){
            return 1;
          }
          if ($bstart<$astart && $bend>$astart){
            return -1;
          }
          return 1;
        }

      });
    }

    if (!empty($filtered_events)){
      $build['#data']=[];
      $i=0;
      foreach ($filtered_events as $nid =>$filtered_event){
        if ($i > 3){
          unset($filtered_events[$nid]);
        }
        $i++;
      }
      foreach ($filtered_events as  $filtered_event){
        if ($filtered_event['event']->hasTranslation($language)){
          $filtered_event['event'] = $filtered_event['event']->getTranslation($language);
        }
        $nid = $filtered_event['nid'];
        $build['#data'][$nid]['url'] = $filtered_event['event']->toUrl()->toString();
        $build['#data'][$nid]['price'] = $filtered_event['event']->get('field_price')->value;
        $build['#data'][$nid]['venue'] = $filtered_event['event']->get('field_venue')->value;
        $build['#data'][$nid]['title'] = $filtered_event['event']->get('title')->value;
        $formatted_time = '';
        if (isset($filtered_event['start_time'])) {
          $start_time = new DrupalDateTime(date('c', $filtered_event['start_time']));
          $start_time->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));

          $formatted_time = $start_time->format("H:i");
        }
        $build['#data'][$nid]['start_date_time'] = $formatted_time;
        if (isset($filtered_event['start_date'])) {
          $build['#data'][$nid]['start_date_day'] = date('d', strtotime($filtered_event['start_date']));
          $build['#data'][$nid]['start_date_year'] = date('Y', strtotime($filtered_event['start_date']));
          $build['#data'][$nid]['start_date_month'] = t(date('M', strtotime($filtered_event['start_date'])),[],['context'=>'Abbreviated month name']);
          $build['#data'][$nid]['sr_start_month'] = t(date('F', strtotime($filtered_event['start_date'])),[],['context'=>'Long month name']);
          $build['#data'][$nid]['sr_start_day'] = date('d',strtotime($filtered_event['start_date']));
          $build['#data'][$nid]['sr_start_year'] = date('Y',strtotime($filtered_event['start_date']));
        }
        if (isset($filtered_event['end_date'])) {
          $build['#data'][$nid]['end_date_month'] = t(date('M', strtotime($filtered_event['end_date'])),[],['context'=>'Abbreviated month name']);
          $build['#data'][$nid]['end_date_day'] = date('d', strtotime($filtered_event['end_date']));
          $build['#data'][$nid]['end_date_year'] = date('Y', strtotime($filtered_event['end_date']));
          $build['#data'][$nid]['sr_end_month'] = t(date('F', strtotime($filtered_event['end_date'])),[],['context'=>'Long month name']);
          $build['#data'][$nid]['sr_end_day'] = date('d',strtotime($filtered_event['end_date']));
          $build['#data'][$nid]['sr_end_year'] = date('Y',strtotime($filtered_event['end_date']));
        }
        $formatted__end_time = '';
        if (isset($filtered_event['end_time'])) {
          $end_time = new DrupalDateTime(date('c', $filtered_event['end_time']));
          $end_time->setTimezone(new \DateTimezone(DateTimeItemInterface::STORAGE_TIMEZONE));

          $formatted__end_time = $end_time->format("H:i");
        }
        $build['#data'][$nid]['end_date_time'] = $formatted__end_time;
      }
    }
    $build['#info'] = [];
    if (!empty($block_configuration['event_type'])){
      if ($block_configuration['event_type']=='all'){
        $build['#info']['title'] = t('Events',array(),['context'=>'Front page calendar']);
        $build['#info']['link'] = Url::fromRoute('harno_pages.calendar')->toString();
        $build['#info']['link_title'] = t('Look at all events',array(),['context'=>'Front page calendar']);
      }
      elseif ($block_configuration['event_type']=='trainings'){
        $build['#info']['title'] = t('Trainings',array(),['context'=>'Front page calendar']);

        $build['#info']['link'] = Url::fromRoute('harno_pages.training')->toString();
        $build['#info']['link_title'] = t('Look at all trainings',array(),['context'=>'Front page calendar']);
      }
      elseif ($block_configuration['event_type']=='events'){
        $build['#info']['title'] = t('Events',array(),['context'=>'Front page calendar']);
        $build['#info']['link'] = Url::fromRoute('harno_pages.calendar')->toString();
        $build['#info']['link_title'] = t('Look at all events',array(),['context'=>'Front page calendar']);
      }
    }
    else {
      $build['#info']['title'] = t('Events',array(),['context'=>'Front page calendar']);
      $build['#info']['link'] = Url::fromRoute('harno_pages.calendar')->toString();
      $build['#info']['link_title'] = t('Look at all events',array(),['context'=>'Front page calendar']);
    }
    if (!empty($block_configuration['col_width'])){
      $build['#info']['width'] = $block_configuration['col_width'];
    }
    if (isset($block_configuration['attributes'])) {
      $build['#attributes'] = $block_configuration['attributes'];
    }

    if (!empty($node)) {

      if (!empty($node->get('nid'))) {
        $build['#info']['nid'] = $node->get('nid')->value;
      }
    }
    else{
      $build['#info']['nid'] = rand(1,19999);
    }
    if (isset($block_configuration['delta'])){
      $build['#info']['delta'] = $block_configuration['delta'];
    }
    if (isset($block_configuration['event_type'])) {
      $build['#info']['event_type'] = $block_configuration['event_type'];
    }
    $build['#theme'] = 'harno-front-events-block';

    if (isset($block_configuration['label'])){
      if ($block_configuration['label_display']=='visible'){
        $build['#info']['label'] = $block_configuration['label'];
        $build['#info']['label_display'] = $block_configuration['label_display'];
      }
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();
    $default = !empty($config['event_type']) ?$config['event_type']:'all';
    $form['event_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Type to display'),
      '#options'=> ['all'=>t('All'),'events'=>t('Events'),'trainings'=>t('Trainings')],
      '#default_value' => $default,
    ];
    return $form;
  }
  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['event_type'] = $values['event_type'];


  }
}
