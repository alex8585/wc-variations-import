<?php
require_once('../wp-load.php');
require_once('AleXmlReader.php');
require_once('AleFastImport.php');

$reader = new AleXmlReader('1.xml');

[
  'categories' => $categories,
  'products' => $products,
] = $reader->parse();

// print_r($categories);

// $cats_import = new AleCatsImport();
// $imported_cats = $cats_import->import($categories);
// print_r($imported_cats);


$import = new AleFastImport();
$import->import($products, $categories);
