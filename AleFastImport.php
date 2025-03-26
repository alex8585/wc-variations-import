<?php
require_once('AleUtils.php');
class AleFastImport
{
  // attribute_%d1%80%d0%b0%d0%b7%d0%bc%d0%b5%d1%80
  // attribute_%d0%a0%d0%b0%d0%b7%d0%bc%d0%b5%d1%80
  public function __construct()
  {
    $this->u = new AleUtils();
    $this->inserted_parent_products_ids = [];
  }

  function import_product($product)
  {
    $group_id = $product['group_id'];
    $price = $product['price'];
    $is_parent_product = $product['is_parent_product'];
    $v_attrs = $product['variants_attributes'];

    if ($is_parent_product) {
      $name = $product['name'];
      $all_v_attrs = $product['all_variants_attrs'];
      $s_attrs = $product['simple_attributes'];

      if (empty($group_id)) return;

      // 3. Создаём родительский товар
      $product = new WC_Product_Variable();
      $product->set_name($name);
      $product->set_sku($group_id);
      // $product->set_sku('10');
      $product->set_price($price);
      $product->set_regular_price($price);
      $product->set_manage_stock(false);
      $product->set_status('publish');
      $parent_id = $product->save();
      $this->inserted_parent_products_ids[$group_id] = $parent_id;

      $product_attributes = [];
      $i = 0;
      foreach ($all_v_attrs as $attr_name => $terms) {
        $terms_str = implode('|', $terms);
        $product_attributes[$attr_name] =  [
          'name'         => $attr_name,
          'value'        => $terms_str,
          'position'     => $i++,
          'is_visible'   => 0,
          'is_variation' => 1,
          'is_taxonomy'  => 0,
        ];
      }
      foreach ($s_attrs as $attr_name => $term) {
        $product_attributes[$attr_name] =  [
          'name'         => $attr_name,
          'value'        => $term,
          'position'     => $i++,
          'is_visible'   => 1,
          'is_variation' => 0,
          'is_taxonomy'  => 0,
        ];
      }

      update_post_meta($parent_id, '_product_attributes', $product_attributes);
    } else {
      $parent_id = $this->inserted_parent_products_ids[$group_id];
    }

    $variant_attrs = array_combine(
      array_map([$this->u, 'lower_encode'], array_keys($v_attrs)),
      array_values($v_attrs)
    );

    $attrs_str = implode("-", $variant_attrs);
    $variation_sku = $group_id . '-' . strtolower($attrs_str);

    $variation = new WC_Product_Variation();
    $variation->set_parent_id($parent_id);
    $variation->set_sku($variation_sku);
    $variation->set_price($price);
    $variation->set_regular_price($price);
    $variation->set_manage_stock(false);
    $variation->set_status('publish');

    $variation->set_attributes($variant_attrs);
    $variation->save();
  }

  function import($products)
  {
    foreach ($products as $group_id => $group_products) {
      foreach ($group_products as $offer_id => $product) {
        $this->import_product($product);
      }
    }
  }
}
