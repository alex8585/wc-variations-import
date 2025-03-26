<?php
require_once('AleUtils.php');
class AleProductsImport
{

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

    if (empty($group_id)) return;

    if ($is_parent_product) {
      $name = $product['name'];
      $all_v_attrs = $product['all_variants_attrs'];

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

      // Обновляем такс_термы родителя
      foreach ($all_v_attrs as $attr_name => $vals) {
        foreach ($vals as $k => $term) {
          $pa_slag = $this->u->name_to_pa($attr_name);
          $term_slug = $this->u->ale_slug($term);
          if (!term_exists($term, $pa_slag)) {
            wp_insert_term($term, $pa_slag, ['slug' => $term_slug]);
          }
          wp_set_object_terms($parent_id, $term, $pa_slag, true);
        }
      }

      // Обновляем метаданные атрибута для родительского товара
      $existing_terms = get_post_meta($parent_id, '_product_attributes', true);
      if (!$existing_terms) $existing_terms = [];

      $i = 0;
      foreach ($all_v_attrs as $attr_name => $terms) {
        $pa_slag = $this->u->name_to_pa($attr_name);
        $new_terms = array_map([$this->u, 'ale_slug'], $terms);
        $terms_str = implode('|', $new_terms);
        $existing_terms += [
          $pa_slag => [
            'name'         => $pa_slag,
            'value'        => $terms_str,
            'position'     => $i++,
            'is_visible'   => 1,
            'is_variation' => 1,
            'is_taxonomy'  => 1,
          ]
        ];
      }

      update_post_meta($parent_id, '_product_attributes', $existing_terms);
    } else {
      $parent_id = $this->inserted_parent_products_ids[$group_id];
    }

    $variant_attrs = array_combine(
      array_map([$this->u, 'name_to_pa'], array_keys($v_attrs)),
      array_map([$this->u, 'ale_slug'], array_values($v_attrs))
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
