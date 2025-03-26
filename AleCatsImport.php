<?php
require_once('AleUtils.php');
class AleCatsImport
{

  function import($categories_array)
  {
    $imported_terms = array();
    foreach ($categories_array as $category_data) {
      if (!isset($imported_terms[$category_data['id']])) {
        $parent_term_id = 0;

        if (!empty($category_data['parent_id']) && isset($imported_terms[$category_data['parent_id']])) {
          $parent_term_id = $imported_terms[$category_data['parent_id']];
        }

        $existing_term = term_exists($category_data['name'], 'product_cat', $parent_term_id);

        if ($existing_term === 0 || $existing_term === null) {
          $term_data = wp_insert_term(
            $category_data['name'],
            'product_cat',
            array('parent' => $parent_term_id)
          );

          if (!is_wp_error($term_data)) {
            $imported_terms[$category_data['id']] = $term_data['term_id'];
            add_term_meta($term_data['term_id'], 'xml_id', $category_data['id'], true);
          }
        } else {
          $imported_terms[$category_data['id']] = $existing_term['term_id'];
          wp_update_term($existing_term['term_id'], 'product_cat', array('parent' => $parent_term_id));
          update_term_meta($existing_term['term_id'], 'xml_id', $category_data['id']);
        }
      }
    }
    return $imported_terms;
  }
}
