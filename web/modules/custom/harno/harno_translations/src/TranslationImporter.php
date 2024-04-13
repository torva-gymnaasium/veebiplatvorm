<?php

namespace Drupal\harno_translations;

use Drupal\locale\SourceString;

class TranslationImporter
{
  /**
   * @param $source_string - Source string we want to translate
   * @param $translation - String translation
   * @param $language - Language to translate to
   * @param $context - Conetxt where translation applies
   * @return void
   */
  public static function importTranslation($source_string,$translation,$language,$context=null){
    $storage = \Drupal::service('locale.storage');
    if ($context) {
      // Get Strings with context
      $string = $storage->findString(array('source' => $source_string, 'context' => $context));
    }
    else{
      //Strings without context
      $string = $storage->findString(array('source' => $source_string));
    }
    if (is_null($string)) {
      $string = new SourceString();
      $string->setString($source_string);
      if ($context) {
        $string->setValues(['context' => $context]);
      }
      $string->setStorage($storage);
      $string->save();
    }
    if (!empty($string)){
      $translation = $storage->createTranslation(array(
        'lid' => $string->lid,
        'language' => $language,
        'translation' => $translation,
      ))->save();
    }
  }
}
