<?php

/**
 * @file
 * ModalController class.
 */

namespace Drupal\harno_pages\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\node\Entity\Node;

class ModalController extends ControllerBase {

  public function index($type = null, $id = null) {

    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    // TODO:
    $build = [];
    if($type) {
      if($type == 'content') {
        if(is_numeric($id)) {
          $content = Node::load($id);
          #$content = $content->getTranslation($language);
          $build['#theme'] = 'contact-modal';
          $build['#language'] = $language;
          $build['#title'] = t('Contact card');
          $build['#content'] = $content;
          $build['#cache'] = [
            'conttexts' => ['url.query_args'],
            'tags' => ['node_type:worker'],
          ];
        }
      }
      elseif ($type == 'gallery'){
        if(is_numeric($id)) {
          $content = Node::load($id);
          $build['#theme'] = 'picture-modal';
          $build['#content'] = $content;
          $build['#cache'] = [
            'conttexts' => ['url.query_args'],
            'tags' => ['node_type:gallery'],
          ];
        }
      }
      elseif($type == 'webform'){
        $webform = \Drupal\webform\Entity\Webform::load($id);
//        $filter_form = \Drupal::formBuilder()->getForm('Drupal\harno_pages\Form\WebformCustomForm', $id, $webform);
        $build['#title'] = $webform->label();
        $build['#theme'] = 'form-modal';
        $build['#content'] = $id;
      }
    }
    return $build;
  }
}
