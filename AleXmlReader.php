<?php
class AleXmlReader
{
  public $xml, $offers, $cats, $products, $categories, $variants_attr_names;

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
    $this->cats = $xml->shop->categories->category;
    $this->products = [];
    $this->categories = [];
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

  public function is_parent_product($offer)
  {
    $group_id = (string)$offer['group_id'];
    $offer_id = (string)$offer['id'];

    $is_parent_product = 0;
    if ($offer_id == $group_id) {
      $is_parent_product = 1;
    }

    if (isset($offer->vendorCode) || isset($offer->vendor)) {
      $is_parent_product = 1;
    }
    return $is_parent_product;
  }

  public function set_variants_attrs_names()
  {
    foreach ($this->offers as $offer) {
      if (!$this->is_parent_product($offer)) {
        $params = $this->parse_params($offer->param);
        $names = array_keys($params);
        $this->variants_attr_names += $names;
      }
    }
  }

  public function parse_categories()
  {
    foreach ($this->cats as $cat) {
      $id = (string)$cat['id'];
      $parent_id = (string)$cat['parentId'];
      $name = (string)$cat;
      $this->categories[$id] = [
        'id' => $id,
        'parent_id' => $parent_id,
        'name' => $name,
      ];
    }
  }

  public function parse()
  {

    $this->parse_categories();
    $this->set_variants_attrs_names();

    foreach ($this->offers as $offer) {
      $group_id = (string)$offer['group_id'];
      $offer_id = (string)$offer['id'];
      $price = (float)$offer->price;
      $name = (string)$offer->name;
      $xml_category_id = (string)$offer->categoryId;

      $vendor = (string)$offer->vandor;
      $vendor_code = (string)$offer->vendorCode;
      $pictures = (array)$offer->picture;
      $pictures = array_map('trim', $pictures);
      $description = trim((string)$offer->description);

      $is_parent_product = $this->is_parent_product($offer);

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

      $this->products[$group_id][$offer_id] = [];
      if ($is_parent_product) {
        $this->products[$group_id][$offer_id] = [
          'name' => $name,
          'xml_category_id' => $xml_category_id,
          'simple_attributes' => $s_attrs,
          'vendor' => $vendor,
          'vendor_code' => $vendor_code,
        ];
      }

      $this->products[$group_id][$offer_id] =
        $this->products[$group_id][$offer_id] + [
          'price' => $price,
          'images' => $pictures,
          'offer_id' => $offer_id,
          'group_id' => $group_id,
          'variants_attributes' => $v_attrs,
          'is_parent_product' => $is_parent_product,
          'description' => $description,
        ];
    }

    $this->set_parent_all_variants_attributes();


    return [
      $this->categories,
      $this->products,
    ];
  }

  function set_parent_all_variants_attributes()
  {
    foreach ($this->products as $group_id => $products_group) {
      $all_variants_attrs = [];
      $parent_product_id = null;
      foreach ($products_group as $product_id => $product) {
        if ($product['is_parent_product']) {
          $parent_product_id = $product_id;
        }
        foreach ($product['variants_attributes'] as $k => $v) {
          $all_variants_attrs[$k][$v] = $v;
        }
      }
      $this->products[$group_id][$parent_product_id]['all_variants_attrs'] = $all_variants_attrs;
    }
  }
}
