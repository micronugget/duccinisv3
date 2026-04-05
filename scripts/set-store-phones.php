<?php
$stores = \Drupal::entityTypeManager()->getStorage('commerce_store')->loadMultiple();
$phones = [
  'Adams Morgan DC' => '+12022342622',
  'Arlington VA' => '+17035328888',
  '7th Street NW' => '+12026280099',
];
foreach ($stores as $store) {
  $label = $store->label();
  if (isset($phones[$label])) {
    $store->set('field_phone', $phones[$label]);
    $store->save();
    echo 'Saved: ' . $label . ' => ' . $phones[$label] . PHP_EOL;
  }
}
