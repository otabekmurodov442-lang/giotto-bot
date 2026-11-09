<?php
require __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
$items = [];
foreach (MENU as $id => $m) $items[] = ['id'=>$id,'n'=>$m[0],'p'=>$m[1],'e'=>$m[2],'c'=>$m[3],'d'=>$m[4]];
echo json_encode([
  'name'=>RESTO_NAME,'phone'=>RESTO_PHONE,'address'=>RESTO_ADDRESS,'lat'=>RESTO_LAT,'lng'=>RESTO_LNG,
  'hours'=>RESTO_HOURS,'fee'=>DELIVERY_FEE,'free'=>FREE_DELIVERY_FROM,'items'=>$items
], JSON_UNESCAPED_UNICODE);
