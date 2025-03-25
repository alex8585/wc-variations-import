<?php
class AleXmlReader
{
  public $xml;

  public function __construct(string $xml_file)
  {
    if (!file_exists($xml_file)) {
      die("Файл не найден");
    }

    $xml = simplexml_load_file($xml_file);
    if (!$xml) {
      die("Ошибка загрузки YML");
    }
    if (!$xml->shop->offers) {
      die("Нет тега offers YML");
    }

    $this->xml = $xml;
    $this->offers = $xml->shop->offers->offer;
    $this->products = [];
    $this->variants_attributes = [];
    $this->simple_attributes = [];
    $this->variants_attr_names = [];
  }

  public function parse_params($params_elem)
  {
    foreach ($params_elem as $param) {
      $name = (string)$param['name'];
      $value = (string)$param;
      $params[$name] = $value;
    }
    return $params;
  }

  public function set_variants_attrs_names()
  {
    foreach ($this->offers as $offer) {
      if (!$offer->vendor) {
        $params = $this->parse_params($offer->param);
        $names = array_keys($params);
        $this->variants_attr_names += $names;
      }
    }
  }

  public function parse()
  {

    $this->set_variants_attrs_names();

    $groups_variants_attrs = [];
    foreach ($this->offers as $offer) {
      $group_id = (string)$offer['group_id'];
      $offer_id = (string)$offer['id'];
      $price = (float)$offer->price;
      $name = (string)$offer->name;

      $is_parent_product = 0;
      if ($offer_id == $group_id) {
        $is_parent_product = 1;
      }

      $params = $this->parse_params($offer->param);
      $v_attrs = [];
      $s_attrs = [];
      foreach ($params as $k => $v) {
        if (in_array($k, $this->variants_attr_names)) {
          $v_attrs[$k] = $v;
        } else {
          $s_attrs[$k] = $v;
        }
      }

      $this->products[$group_id][$offer_id] = [
        'price' => $price,
        'offer_id' => $offer_id,
        'group_id' => $group_id,
        'variants_attributes' => $v_attrs,
        'is_parent_product' => $is_parent_product,
        // 'simple_attributes' => $s_attrs
      ];

      if ($is_parent_product) {
        $this->products[$group_id][$offer_id]['simple_attributes'] = $s_attrs;
        $this->products[$group_id][$offer_id]['name'] = $name;
      }

      foreach ($params as $k => $v) {
        if (in_array($k, $this->variants_attr_names)) {
          $this->products[$group_id][$group_id]['all_variants_attrs'][$k][$v] = $v;
          if (!isset($this->variants_attributes[$k]) || !in_array($v, $this->variants_attributes[$k])) {
            $this->variants_attributes[$k][] = $v;
          }
        } else {
          if (!isset($this->simple_attributes[$k]) || !in_array($v, $this->simple_attributes[$k])) {
            $this->simple_attributes[$k][] = $v;
          }
        }
      }
    }
    return [
      'products' => $this->products,
      'variants_attributes' => $this->variants_attributes,
      'simple_attributes' => $this->simple_attributes,
    ];
  }
}

// $reader = new AleXmlReader('1.xml');
// echo $reader->parse();
