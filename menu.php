<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');
function findImg($base) {
  foreach (['jpg','jpeg','png','webp'] as $e) {
    $f = __DIR__ . "/img/$base.$e";
    if (file_exists($f)) return "img/$base.$e?v=" . filemtime($f);
  }
  return null;
}
$items = [];
foreach (MENU as $id => $m) $items[] = ['id'=>$id,'n'=>$m[0],'p'=>$m[1],'e'=>$m[2],'c'=>$m[3],'d'=>$m[4],'img'=>findImg($id)];
$gallery = [];
foreach (glob(__DIR__ . '/img/gallery*.{jpg,jpeg,png,webp}', GLOB_BRACE) ?: [] as $f) $gallery[] = 'img/' . basename($f) . '?v=' . filemtime($f);
echo json_encode([
  'name'=>RESTO_NAME,'phone'=>RESTO_PHONE,'address'=>RESTO_ADDRESS,'lat'=>RESTO_LAT,'lng'=>RESTO_LNG,
  'hours'=>RESTO_HOURS,'fee'=>DELIVERY_FEE,'free'=>FREE_DELIVERY_FROM,'online'=>PAYMENT_TOKEN !== '','channel'=>CHANNEL_LINK,
  'logo'=>findImg('logo'),'hero'=>findImg('hero'),'gallery'=>$gallery,'items'=>$items
], JSON_UNESCAPED_UNICODE);
