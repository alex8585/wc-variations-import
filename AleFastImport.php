<?php
require_once('AleUtils.php');
require_once('AleCatsImport.php');

class AleFastImport
{
  public function __construct()
  {
    $this->u = new AleUtils();
    $this->cats_importer = new AleCatsImport();
    $this->inserted_parent_products_ids = [];
  }

  function import_parent_product_attributes($product, $parent_id)
  {
    $all_v_attrs = $product['all_variants_attrs'];
    $s_attrs = $product['simple_attributes'];
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
  }

  function import_parent_product($product)
  {
    $group_id = $product['group_id'];
    $name = $product['name'];
    $cat_id = $product['db_category_id'];

    $wc_product = new WC_Product_Variable();
    $wc_product->set_name($name);
    $wc_product->set_sku($group_id);
    $wc_product->set_manage_stock(false);
    $wc_product->set_status('publish');
    $wc_product->set_category_ids(array($cat_id));
    $parent_id = $wc_product->save();

    $this->import_parent_product_attributes($product, $parent_id);
    return $parent_id;
  }

  function import_variant_product($product, $parent_id)
  {
    $group_id = $product['group_id'];
    $price = $product['price'];
    $v_attrs = $product['variants_attributes'];

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

  function import_product($product)
  {
    $is_parent_product = $product['is_parent_product'];
    $group_id = $product['group_id'];

    if ($is_parent_product) {
      $parent_id = $this->import_parent_product($product);
      $this->inserted_parent_products_ids[$group_id] = $parent_id;
    } else {
      $parent_id = $this->inserted_parent_products_ids[$group_id];
    }

    $this->import_variant_product($product, $parent_id);
  }

  function import_cats($products, $categories)
  {
    $imported_cats = $this->cats_importer->import($categories);
    foreach ($products as $group_id => $group_products) {
      foreach ($group_products as $offer_id => $product) {
        $xml_id = $product['xml_category_id'] ?? null;
        if ($xml_id) {
          $db_id = $imported_cats[$xml_id];
          $products[$group_id][$offer_id]['db_category_id'] =  $db_id;
        }
      }
    }

    return $products;
  }

  function import($products, $categories)
  {
    $products = $this->import_cats($products, $categories);
    foreach ($products as $group_id => $group_products) {
      foreach ($group_products as $offer_id => $product) {
        $this->import_product($product);
      }
    }
  }
}
