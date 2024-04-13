<?php
namespace Drupal\harno_translations\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

class ImportTranslations extends FormBase
{

  public function getFormId()
  {
    return 'harno_translations_import';
  }

  public function buildForm(array $form, FormStateInterface $form_state)
  {

    $form['actions']['submit'] = [
      '#type'=> 'submit',
      '#value' => 'Import'
    ];
    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    harno_translations_import_translations();
  }
}
