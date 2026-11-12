<?php
// Mini app ochilganda foydalanuvchi kanalga a'zoligini tekshiradi
require __DIR__ . '/order_lib.php';
header('Content-Type: application/json; charset=utf-8');
$in = json_decode(file_get_contents('php://input'), true);
$user = verify_init($in['initData'] ?? '');
if (!$user) { http_response_code(403); echo json_encode(['ok'=>false]); exit; }
echo json_encode(['ok'=>true,'member'=>is_member($user['id']),'link'=>CHANNEL_LINK]);
