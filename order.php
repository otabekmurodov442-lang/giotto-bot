<?php
// Mini app buyurtmani shu yerga yuboradi (menyu tugmasi orqali ochilganda ham ishlaydi)
require __DIR__ . '/order_lib.php';
header('Content-Type: application/json; charset=utf-8');
function fail($code, $msg) { http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

$in = json_decode(file_get_contents('php://input'), true);
if (!isset($in['order'])) fail(400, 'bad request');
$user = verify_init($in['initData'] ?? '');
if (!$user) fail(403, 'auth');
if (!is_member($user['id'])) { echo json_encode(['ok'=>false,'error'=>'subscribe']); exit; }

echo json_encode(['ok' => process_order($in['order'], $user['id'], $user)]);
