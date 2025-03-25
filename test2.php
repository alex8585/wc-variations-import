<?php

require_once('../wp-load.php');
function ale_slug($string) {
    $replace = [
        'а'=>'a', 'б'=>'b', 'в'=>'v', 'г'=>'g', 'д'=>'d', 'е'=>'e', 'ё'=>'yo',
        'ж'=>'zh', 'з'=>'z', 'и'=>'i', 'й'=>'y', 'к'=>'k', 'л'=>'l', 'м'=>'m',
        'н'=>'n', 'о'=>'o', 'п'=>'p', 'р'=>'r', 'с'=>'s', 'т'=>'t', 'у'=>'u',
        'ф'=>'f', 'х'=>'h', 'ц'=>'ts', 'ч'=>'ch', 'ш'=>'sh', 'щ'=>'shch',
        'ъ'=>'', 'ы'=>'y', 'ь'=>'', 'э'=>'e', 'ю'=>'yu', 'я'=>'ya',
    ];
    $string = mb_strtolower($string);
    $string = strtr($string, $replace);
    return sanitize_title($string);
}
function name_to_pa($attr_name) {
    $attr_slag = ale_slug($attr_name);
    return  'pa_' . $attr_slag; 
}



function import_yml_variations($yml_file) {
    if (!file_exists($yml_file)) {
        die("Файл не найден");
    }
    
    $xml = simplexml_load_file($yml_file);
    if (!$xml) {
        die("Ошибка загрузки YML");
    }
    
    global $wpdb;

    $attrs = [
      "Цвет" => [
          'Синий',
          'Красный',
          'Зеленый'
      ],
      "Размер" => [
          '1',
          '2',
          '3'
      ],
    ];
      
    foreach($attrs as $attr_name=>$v) {
        $pa_slag = name_to_pa($attr_name);
        if (!taxonomy_exists($pa_slag)) {
            $wpdb->insert(
                "{$wpdb->prefix}woocommerce_attribute_taxonomies",
          ['attribute_name' => $attr_slag, 'attribute_label' => $attr_name,
           'attribute_type' => 'select', 'attribute_orderby' => 'menu_order'],
                ['%s', '%s', '%s', '%s']
            );
            flush_rewrite_rules();
            delete_transient('wc_attribute_taxonomies');
            register_taxonomy($pa_slag, 'product', ['hierarchical' => false, 'show_ui' => false]);
        }
    }

    $products = [];

    foreach ($xml->shop->offers->offer as $offer) {
        $group_id = (string)$offer['group_id'];
        $offer_id = (string)$offer['id'];
        $price = (float)$offer->price;
        $name = (string)$offer->name;

        if (empty($group_id)) continue;

        if (!isset($products[$group_id])) {
            // 3. Создаём родительский товар
            $product = new WC_Product_Variable();
            $product->set_name($name);
            $product->set_sku($group_id);
            $product->set_price($price);
            $product->set_regular_price($price);
            $product->set_manage_stock(false);
            $product->set_status('publish');
            $parent_id = $product->save();
            $products[$group_id] = $parent_id;
        } else {
            $parent_id = $products[$group_id];
        }

        foreach ($attrs as $attr_name => $vals) {
          foreach ($vals as $term) {
            $pa_slag = name_to_pa($attr_name);        
            $term_slug = ale_slug($term);
            if (!term_exists($term, $pa_slag)) {
                wp_insert_term($term, $pa_slag, ['slug' => $term_slug]);
            }
            wp_set_object_terms($parent_id, $term, $pa_slag, true);
          }   
        }

        // 6. Обновляем метаданные атрибута для родительского товара
        $existing_terms = get_post_meta($parent_id, '_product_attributes', true);
        if (!$existing_terms) $existing_terms = [];

        $i=0;
        foreach ($attrs as $attr_name=>$terms) {
            $pa_slag = name_to_pa($attr_name);
            $new_terms = array_map('ale_slug', $terms);
            $terms_str = implode('|', $new_terms);
            $existing_terms += [
                $pa_slag => [
                  'name'         => $pa_slag,
                  'value'        => $term_slug,
                  'position'     => $i++,
                  'is_visible'   => 1,
                  'is_variation' => 1,
                  'is_taxonomy'  => 1,
                ]
            ];
        }
        
        update_post_meta($parent_id, '_product_attributes', $existing_terms);

        $prod_attrs = [
          "Цвет" => 'Синий',
          "Размер" => '1',
        ];

        $product_attrs = array_combine(
          array_map('name_to_pa', array_keys($prod_attrs)),
          array_map('ale_slug', array_values($prod_attrs))
        ); 

        $attrs_str = implode("-",$product_attrs);
        $variation_sku = $group_id . '-' . strtolower($attrs_str);
        $existing_variation_id = wc_get_product_id_by_sku($variation_sku);
        if ($existing_variation_id) continue;

        $variation = new WC_Product_Variation();
        $variation->set_parent_id($parent_id);
        $variation->set_sku($variation_sku);
        $variation->set_price($price);
        $variation->set_regular_price($price);
        $variation->set_manage_stock(false);
        $variation->set_status('publish');

        $variation->set_attributes($product_attrs);
        $variation->save();
        return;
    }
}


import_yml_variations('1.xml');

