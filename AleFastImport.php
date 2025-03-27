<?php
require_once('AleCatsImport.php');
require_once('AleImagesImport.php');
require_once('AleUtils.php');

class AleFastImport
{
  private $execution_times = [];
  private $inserted_parent_products_ids = [];

  public function __construct()
  {
    $this->images_import = new AleImagesImport();
    $this->u = new AleUtils();
    $this->cats_importer = new AleCatsImport();
  }

  private function startTimer(): float
  {
    return microtime(true);
  }

  private function recordTime(string $function_name, float $start_time): void
  {
    $end_time = microtime(true);
    $execution_time = round(($end_time - $start_time) * 1000, 2); // в миллисекундах
    $this->execution_times[$function_name][] = $execution_time;
  }

  public function getPerformanceStats(): array
  {
    $stats = [];
    foreach ($this->execution_times as $function => $times) {
      $stats[$function] = [
        'count' => count($times),
        'total_time' => round(array_sum($times), 2) . 'ms',
        'avg_time' => round(array_sum($times) / count($times), 2) . 'ms',
        'max_time' => round(max($times), 2) . 'ms',
        'min_time' => round(min($times), 2) . 'ms'
      ];
    }
    return $stats;
  }

  function lower_encode($str)
  {
    $result = mb_strtolower(urlencode(mb_strtolower($str, 'UTF-8')), 'UTF-8');
    return $result;
  }

  function import_parent_product_attributes($product, $parent_id)
  {
    $start = $this->startTimer();

    $all_v_attrs = $product['all_variants_attrs'];
    $s_attrs = $product['simple_attributes'];
    $product_attributes = [];
    $i = 0;

    foreach ($all_v_attrs as $attr_name => $terms) {
      $product_attributes[$attr_name] = [
        'name' => $attr_name,
        'value' => implode('|', $terms),
        'position' => $i++,
        'is_visible' => 0,
        'is_variation' => 1,
        'is_taxonomy' => 0,
      ];
    }

    foreach ($s_attrs as $attr_name => $term) {
      $product_attributes[$attr_name] = [
        'name' => $attr_name,
        'value' => $term,
        'position' => $i++,
        'is_visible' => 1,
        'is_variation' => 0,
        'is_taxonomy' => 0,
      ];
    }

    update_post_meta($parent_id, '_product_attributes', $product_attributes);
    $this->recordTime(__FUNCTION__, $start);
  }

  function import_parent_product($product)
  {
    $start = $this->startTimer();

    $wc_product = new WC_Product_Variable();
    $wc_product->set_name($product['name']);
    $wc_product->set_sku($product['group_id']);
    $wc_product->set_manage_stock(false);
    $wc_product->set_status('publish');
    $wc_product->set_category_ids([$product['db_category_id']]);
    $wc_product->set_description($product['description']);

    [
      "first_image_id" => $first_image_id,
      "gallery_ids" => $gallery_ids,
    ] = $this->images_import->import($product['images']);

    $wc_product->set_image_id($first_image_id);
    if (!empty($gallery_ids)) {
      $wc_product->set_gallery_image_ids($gallery_ids);
    }

    $parent_id = $wc_product->save();
    $this->import_parent_product_attributes($product, $parent_id);

    $this->recordTime(__FUNCTION__, $start);
    return $parent_id;
  }

  function import_variant_product($product, $parent_id)
  {
    $start = $this->startTimer();

    $variant_attrs = array_combine(
      array_map([$this, 'lower_encode'], array_keys($product['variants_attributes'])),
      array_values($product['variants_attributes'])
    );

    $variation_sku = $product['group_id'] . '-' . strtolower(implode("-", $variant_attrs));

    $variation = new WC_Product_Variation();
    $variation->set_parent_id($parent_id);
    $variation->set_sku($variation_sku);
    $variation->set_price($product['price']);
    $variation->set_regular_price($product['price']);
    $variation->set_manage_stock(false);
    $variation->set_status('publish');
    $variation->set_attributes($variant_attrs);
    $variation->save();

    $this->recordTime(__FUNCTION__, $start);
  }

  function import_product($product)
  {
    $start = $this->startTimer();

    if ($product['is_parent_product']) {
      $parent_id = $this->import_parent_product($product);
      $this->inserted_parent_products_ids[$product['group_id']] = $parent_id;
    } else {
      $parent_id = $this->inserted_parent_products_ids[$product['group_id']];
    }

    $this->import_variant_product($product, $parent_id);
    $this->recordTime(__FUNCTION__, $start);
  }

  function import_cats($products, $categories)
  {
    $start = $this->startTimer();

    $imported_cats = $this->cats_importer->import($categories);
    foreach ($products as $group_id => &$group_products) {
      foreach ($group_products as $offer_id => &$product) {
        if ($xml_id = $product['xml_category_id'] ?? null) {
          $product['db_category_id'] = $imported_cats[$xml_id];
        }
      }
    }

    $this->recordTime(__FUNCTION__, $start);
    return $products;
  }

  function import($categories, $products)
  {
    $start = $this->startTimer();

    $totalItems = count($products);
    $processed = 0;
    $startTime = time();

    $products = $this->import_cats($products, $categories);
    foreach ($products as $group_id => $group_products) {
      $processed++;
      $this->u->draw_progress_bar($processed, $totalItems, $startTime);
      ob_flush();
      flush();

      foreach ($group_products as $product) {
        $this->import_product($product);
      }
    }

    $this->recordTime(__FUNCTION__, $start);

    // Вывод статистики
    $stats = $this->getPerformanceStats();
    echo "\n\n=== PERFORMANCE STATISTICS ===\n";
    foreach ($stats as $function => $data) {
      echo sprintf(
        "%s:\n  Called: %d times\n  Total: %s\n  Avg: %s\n  Max: %s\n  Min: %s\n\n",
        $function,
        $data['count'],
        $data['total_time'],
        $data['avg_time'],
        $data['max_time'],
        $data['min_time']
      );
    }
  }
}

