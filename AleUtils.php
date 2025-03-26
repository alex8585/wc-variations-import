<?php
class AleUtils
{
  function lower_encode($str)
  {
    $lc = mb_strtolower($str, 'UTF-8');
    $enc = urlencode($lc);
    return mb_strtolower($enc, 'UTF-8');
  }

  function ale_slug($string)
  {
    $replace = [
      'а' => 'a',
      'б' => 'b',
      'в' => 'v',
      'г' => 'g',
      'д' => 'd',
      'е' => 'e',
      'ё' => 'yo',
      'ж' => 'zh',
      'з' => 'z',
      'и' => 'i',
      'й' => 'y',
      'к' => 'k',
      'л' => 'l',
      'м' => 'm',
      'н' => 'n',
      'о' => 'o',
      'п' => 'p',
      'р' => 'r',
      'с' => 's',
      'т' => 't',
      'у' => 'u',
      'ф' => 'f',
      'х' => 'h',
      'ц' => 'ts',
      'ч' => 'ch',
      'ш' => 'sh',
      'щ' => 'shch',
      'ъ' => '',
      'ы' => 'y',
      'ь' => '',
      'э' => 'e',
      'ю' => 'yu',
      'я' => 'ya',
    ];
    $string = mb_strtolower($string);
    $string = strtr($string, $replace);
    return sanitize_title($string);
  }

  function name_to_pa($attr_name)
  {
    $attr_slag = $this->ale_slug($attr_name);
    return  'pa_' . $attr_slag;
  }

  public function __construct() {}
}
