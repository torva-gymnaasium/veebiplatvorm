<?php
namespace Drupal\harno_settings\TwigExtension;
/**
 * Class DefaultService.
 *
 * @package Drupal\IFrameSrcChanger
 */
class IFrameSrcChanger extends \Twig\Extension\AbstractExtension {

  /**
   * {@inheritdoc}
   * This function must return the name of the extension. It must be unique.
   */
  public function getName() {
    return 'IFrameChanger.twig_extension';
  }

  /**
   * Generates a list of all Twig filters that this extension defines.
   */
  public function getFilters() {
    return [
      new \Twig\TwigFilter('iframeSrc', array($this, 'iFrameSourceChanger')),
    ];
  }

  /**
   * Filter to return colorized text
   */
  public static function iFrameSourceChanger($txt) {
    if (!empty($txt)){

      preg_match('/src="([^"]+)"/', $txt, $match);
      $url = $match[1];
      $out_txt = '';
      if (strpos($url, 'youtube') !== false) {
        $new_url = $url.'&iv_load_policy=1&cc_load_policy=1';
        $out_txt = str_replace($url,$new_url,$txt);
      }
      elseif (strpos($url,'vimeo') !==false){
        $new_url = $url.'&texttrack=et';
        $out_txt = str_replace($url,$new_url,$txt);
      }
      else{
        $out_txt = $txt;
      }
      $out_txt = str_replace('></iframe','style="background-color: #000"></iframe',$out_txt);
      return $out_txt;
    }
//    return '<span style="color: ' . $color . '">Whatever</span>';
  }

}
