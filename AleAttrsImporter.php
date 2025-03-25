<?php
require_once('AleUtils.php');
class AleAttrsImpotrer
{

  public function __construct()
  {
    $this->u = new AleUtils();
  }

  function import($attrs)
  {
    global $wpdb;
    foreach ($attrs as $attr_name => $v) {
      $pa_slag = $this->u->name_to_pa($attr_name);
      $attr_slag = $this->u->ale_slug($attr_name);
      if (!taxonomy_exists($pa_slag)) {
        $wpdb->insert(
          "{$wpdb->prefix}woocommerce_attribute_taxonomies",
          [
            'attribute_name' => $attr_slag,
            'attribute_label' => $attr_name,
            'attribute_type' => 'select',
            'attribute_orderby' => 'menu_order'
          ],
          ['%s', '%s', '%s', '%s']
        );
        flush_rewrite_rules();
        delete_transient('wc_attribute_taxonomies');
        register_taxonomy($pa_slag, 'product', ['hierarchical' => false, 'show_ui' => false]);
      }
    }
  }
}
