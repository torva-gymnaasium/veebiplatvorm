<?php

namespace Drupal\harno_settings\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\taxonomy\Entity\Term;
use Drupal\Component\Utility\Environment;
use Drupal\Component\Utility\UrlHelper;

/**
 * Class HarnoWorkersImportForm
 */
class HarnoWorkersImportForm extends FormBase {

  protected $columns = ['Töötaja identifikaator', 'Nimi (nii ees- kui ka perekonnanimi)', 'Osakond ja töötaja järjekorra number osakonnas',
    'Ametikoht', 'Telefon', 'E-post', 'Lisainformatsioon', 'Vastuvõtuaeg', 'Haridus', 'CV veebilingina'];
  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'harno_workers_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $validators = [
      'file_validate_extensions' => ['csv'],
      'file_validate_size' => [Environment::getUploadMaxSize()],
    ];
    $form['csv_file'] = [
      '#type' => 'file',
      '#title' => $this->t('CSV File'),
      '#description' => [
        '#theme' => 'file_upload_help',
        '#description' => 'Andmeväljade eraldajana kasutatakse tabulatsiooni märki. CSV fail peab sisaldama veerge kindlas järjekorras: ' . implode(", ",$this->columns) .
        ". Mitme väärtuse sisestamiseks tuleb need eraldada püstkriipsuga (|). Osakonna ja järjekorra numbri eraldajaks on semikoolon. Näiteks: Juhtkond;1|Õpetajad;2.",
        '#upload_validators' => $validators,
      ],
      '#upload_validators' => $validators,
    ];
    $form['save'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#attributes' => ['class' => ['button--primary']]
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
    $messenger = \Drupal::messenger();
    $insert = 0;
    $update = 0;
    $validators = ['file_validate_extensions' => ['csv']];
    $file = file_save_upload('csv_file', $validators, FALSE, 0);

    if (!$file) {
      $messenger->addError('Faili üleslaadimine ebaõnnestus!');
      return;
    }
    $importer = new HarnoCsvImporter($file->getFileUri(),false, "\t");
    $data = $importer->get();
    foreach ($data as $i => $row) {
      $row_index = $i + 1;
      $identifier = $name = $departments = $positions = $phones = $email = $body = $consultation_hours = $education = $link = '';
      if ( !empty($row[0]) ) {
        $identifier = $this->trimText($row[0]);
      }
      if ( !empty($row[1]) ) {
        $name = $this->trimText($row[1]);
      }
      if ( !empty($row[2]) ) {
        $departments = $this->trimText($row[2]);
      }
      if ( !empty($row[3]) ) {
        $positions = $this->trimText($row[3]);
      }
      if ( !empty($row[4]) ) {
        $phones = explode('|', $row[4]);
        foreach ($phones as $j => &$p) {
          $p = $this->trimText($p);
        }
      }
      if ( !empty($row[5]) ) {
        $email = $this->trimText($row[5]);
      }
      if ( !empty($row[6]) ) {
        $body = $this->trimText($row[6]);
      }
      if ( !empty($row[7]) ) {
        $consultation_hours = explode('|', $row[7]);
        foreach ($consultation_hours as $j => &$c) {
          $c = $this->trimText($c);
        }
      }
      if ( !empty($row[8]) ) {
        $education = $this->trimText($row[8]);
      }
      if ( !empty($row[9]) ) {
        $link = $this->trimText($row[9]);
      }
      #$messenger->addStatus('Row ' . $row_index . ': ' . print_r($row,1));

      if ( empty($identifier) ) {
        $messenger->addMessage('Puudub väärtus veerus "'.$this->columns[0].'" real ' . $row_index .'.', 'warning');
        continue;
      }
      if ( empty($name) ) {
        $messenger->addMessage('Puudub väärtus veerus "'.$this->columns[1].'" real ' . $row_index .'.', 'warning');
        continue;
      }
      if ( empty($departments) ) {
        $messenger->addMessage('Puudub väärtus veerus "'.$this->columns[2].'" real ' . $row_index .'.', 'warning');
        continue;
      }
      if ( empty($positions) ) {
        $messenger->addMessage('Puudub väärtus veerus "'.$this->columns[3].'" real ' . $row_index .'.', 'warning');
        continue;
      }
      if ( !empty($email) AND !\Drupal::service('email.validator')->isValid($email)) {
        $messenger->addMessage('E-posti aadress veerus "'.$this->columns[5].'" real ' . $row_index .' ei ole korrektne.', 'warning');
        continue;
      }
      if ( !empty($link) AND !UrlHelper::isValid($link, TRUE)) {
        $messenger->addMessage('CV veebiaadress veerus "'.$this->columns[9].'" real ' . $row_index .' ei ole korrektne.', 'warning');
        continue;
      }
      ############################### Osakonnad #############################

      $row_departments = explode ('|', $departments);
      $departments_array = [];

      foreach ($row_departments as $j => $rd) {
        [$department_name, $department_jrk] = explode (';', $rd);
        $department_name = $this->trimText ($department_name);
        $department_jrk = (int) $this->trimText ($department_jrk);

        $departments_term_query = \Drupal::entityQuery('taxonomy_term');
        $departments_term_query->condition('vid', "departments");
        $departments_term_query->condition('name', "$department_name");
        $department_id = $departments_term_query-> accessCheck(false)->execute();

        if (empty($department_id)) {
          $department_create = Term::create([
            'vid' => 'departments',
            'name' => $department_name
          ]);
          $department_create->save();
          $department_id = [$department_create->id()];
        }
        $departments_array[$j][0] = reset($department_id);
        $departments_array[$j][1] = $department_jrk;
        #$messenger->addStatus('$departments_array: ' . print_r($departments_array,1));

      }

      ############################### Ametikohad #############################

      $row_positions = explode ('|', $positions);
      $positions_array = [];

      foreach ($row_positions as $j => $rp) {
        $position_name = $this->trimText ($rp);

        $positions_term_query = \Drupal::entityQuery('taxonomy_term');
        $positions_term_query->condition('vid', "positions");
        $positions_term_query->condition('name', "$position_name");
        $position_id = $positions_term_query->accessCheck(false)->execute();

        if (empty($position_id)) {
          $position_create = Term::create([
            'vid' => 'positions',
            'name' => $position_name
          ]);
          $position_create->save();
          $position_id = [$position_create->id()];
        }
        $positions_array[$j] = reset($position_id);
        #$messenger->addStatus('$positions_array: ' . print_r($positions_array,1));
      }

      try {
        if (empty($departments_array) || empty($positions_array)) {
          $messenger->addMessage('Puudub osakonna või ametikoha viide süsteemis real ' . $row_index .'.', 'warning');
        } else {

          $node_query = \Drupal::entityQuery('node');
          $node_query->condition('type', "worker");
          $node_query->condition('field_identifier', "$identifier");
          $node_id = $node_query->accessCheck(false)->execute();

          if (empty($node_id)) {
            $node = Node::create(
              [
                'type' => 'worker',
                'title' => $name,
                'field_identifier' => $identifier
              ]
            );
            $node->save();
            foreach ($positions_array as $job_position) {
              $node->field_position[] = ['target_id' => $job_position];
            }
            foreach ($departments_array as $d) {
              $paragraph = Paragraph::create([
                'type' => 'department',
                'field_department' => $d[0],
                'field_weight' => $d[1],
              ]);
              $paragraph->save();
              $node->field_department[] = [
                'target_id' => $paragraph->id(),
                'target_revision_id' => $paragraph->getRevisionId(),
              ];
            }
            if (!empty($body)) {
              $node->body = [
                'value' => $body,
                'format' => 'full_html'
              ];
            }
            if (!empty($phones)) {
              $node->field_phone = $phones;
            }
            if (!empty($email)) {
              $node->field_email = $email;
            }
            if (!empty($consultation_hours)) {
              $node->field_consultation_hours = $consultation_hours;
            }
            if (!empty($education)) {
              $node->field_education = $education;
            }
            if (!empty($link)) {
              $node->field_link = [
                'uri' => $link,
                'title' => 'CV',
                'options' => [
                  'attributes' => [
                    'target' => '_blank',
                  ],
                ],
              ];
            }
            $node->save();
            $insert++;
          }
          else {
            #$messenger->addStatus('$node_id ' . print_r($node_id,1) .'.');
            $node = \Drupal::entityTypeManager()->getStorage('node')->load(reset($node_id));
            $node->setTitle($name);
            unset($node->field_position);
            foreach ($positions_array as $job_position) {
              $node->field_position[] = ['target_id' => $job_position];
            }

            $node_departments = $node->get('field_department')->referencedEntities();
            foreach ($departments_array as $key_da => $d) {
              if (isset($node_departments[$key_da]) AND !empty($node_departments[$key_da])) {
                $paragraph = $node_departments[$key_da];
                $paragraph->set('field_department', $d[0]);
                $paragraph->set('field_weight', $d[1]);
                $paragraph->save();
              }
              else {
                $paragraph = Paragraph::create([
                  'type' => 'department',
                  'field_department' => $d[0],
                  'field_weight' => $d[1],
                ]);
                $paragraph->save();
                $node->field_department[] = [
                  'target_id' => $paragraph->id(),
                  'target_revision_id' => $paragraph->getRevisionId(),
                ];
              }
            }
            if (count($departments_array) < count($node_departments)) {
              foreach ($node_departments as $key_da => $nd) {
                if ($key_da > count($departments_array) - 1) {
                  unset($node->field_department[$key_da]);
                  $paragraph = $node_departments[$key_da];
                  $paragraph->delete();
                }
              }
            }
            if (!empty($body)) {
              $node->set('body', [[
                'value' => $body,
                'format' => 'full_html',
              ]]);
            }
            else {
              unset($node->body);
            }
            if (!empty($phones)) {
              $node->set('field_phone',$phones);
            }
            else {
              unset($node->field_phone);
            }
            if (!empty($email)) {
              $node->set('field_email', $email);
            }
            else {
              unset($node->field_email);
            }
            if (!empty($consultation_hours)) {
              $node->set('field_consultation_hours', $consultation_hours);
            }
            else {
              unset($node->field_consultation_hours);
            }
            if (!empty($education)) {
              $node->set('field_education', $education);
            }
            else {
              unset($node->field_education);
            }
            if (!empty($link)) {
              $node->set('field_link', [[
                'uri' => $link,
                'title' => 'CV',
                'options' => [
                  'attributes' => [
                    'target' => '_blank',
                  ],
                ],
              ]]);
            }
            else {
              unset($node->field_link);
            }
            $node->save();
            $update++;
          }
        }
      } catch (\Exception $e) {
        \Drupal::logger('harno_settings')->error($e->getMessage());
        $messenger->addError($e->getMessage());
      }

    }
    $message = t('Lisati @insert töötajat ja uuendati @update töötaja andmed CSV failist.',
      [
        '@insert' => $insert,
        '@update' => $update,
      ]);
    \Drupal::logger('harno_settings')->notice($message);
    $messenger->addStatus($message);

  }

  public function trimText($text) {
    return trim(str_replace(['\t','\n','\r','\0','\x0B'], '', $text));
  }
}

class HarnoCsvImporter
{
  private $fp;
  private $parse_header;
  private $header;
  private $delimiter;
  private $length;
  //--------------------------------------------------------------------
  function __construct($file_name, $parse_header = false, $delimiter = "\t", $length = 8000)
  {
    $this->fp = fopen($file_name, "r");
    $this->parse_header = $parse_header;
    $this->delimiter = $delimiter;
    $this->length = $length;

    if ($this->parse_header) {
      $this->header = fgetcsv($this->fp, $this->length, $this->delimiter);
    }

  }
  //--------------------------------------------------------------------
  function __destruct()
  {
    if ($this->fp)
    {
      fclose($this->fp);
    }
  }
  //--------------------------------------------------------------------
  function get($max_lines = 0)
  {
    //if $max_lines is set to 0, then get all the data

    $data = array();

    if ($max_lines > 0) {
      $line_count = 0;
    } else {
      $line_count = -1; // so loop limit is ignored
    }
    while ($line_count < $max_lines && ($row = fgetcsv($this->fp, $this->length, $this->delimiter)) !== FALSE)
    {
      if ($this->parse_header)
      {
        foreach ($this->header as $i => $heading_i)
        {
          $row_new[$heading_i] = $row[$i];
        }
        $data[] = $row_new;
      }
      else
      {
        $data[] = $row;
      }

      if ($max_lines > 0) {
        $line_count++;
      }
    }
    return $data;
  }
  //--------------------------------------------------------------------

}
