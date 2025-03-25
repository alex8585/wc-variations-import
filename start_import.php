<?php
require_once('../wp-load.php');
require_once('AleXmlReader.php');
require_once('AleAttrsImporter.php');
require_once('AleProductsImport.php');


$reader = new AleXmlReader('1.xml');

[
  'products' => $products,
  'variants_attributes' => $v_attrs,
  'simple_attributes' => $s_attrs
] = $reader->parse();

$attrs = $s_attrs + $v_attrs;

$attrs_imp = new AleAttrsImpotrer();
$attrs_imp->import($attrs);

$attrs_imp = new AleProductsImport();
$attrs_imp->import($products);
