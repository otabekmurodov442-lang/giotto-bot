<?php
require __DIR__ . '/order_lib.php';
$u = json_decode(file_get_contents('php://input'), true);
if (!$u) exit;

// Telegram to'lovni tasdiqlashdan oldingi so'rov
if (isset($u['pre_checkout_query'])) {
  tg('answerPreCheckoutQuery', ['pre_checkout_query_id' => $u['pre_checkout_query']['id'], 'ok' => true]);
  exit;
}

// «Tekshirish» tugmasi
if (isset($u['callback_query'])) {
  $cq = $u['callback_query'];
  if (($cq['data'] ?? '') === 'check') {
    if (is_member($cq['from']['id'])) {
      tg('answerCallbackQuery', ['callback_query_id' => $cq['id'], 'text' => "✅ Rahmat! Endi foydalanishingiz mumkin."]);
      send_welcome($cq['message']['chat']['id'], htmlspecialchars($cq['from']['first_name'] ?? ''));
    } else {
      tg('answerCallbackQuery', ['callback_query_id' => $cq['id'], 'text' => "❌ Siz hali kanalga a'zo emassiz.", 'show_alert' => true]);
    }
  }
  exit;
}
if (!isset($u['message'])) exit;

$m = $u['message']; $chat = $m['chat']['id']; $from = $m['from'];
$name = htmlspecialchars(trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')));

// Majburiy obuna (to'lov xabarlari bundan mustasno)
if (!isset($m['successful_payment']) && !is_member($from['id'])) { send_gate($chat); exit; }

// To'lov muvaffaqiyatli o'tdi
if (isset($m['successful_payment'])) {
  $p = $m['successful_payment']; $oid = htmlspecialchars($p['invoice_payload']); $sum = $p['total_amount'] / 100;
  tg('sendMessage', ['chat_id'=>$chat,'parse_mode'=>'HTML','reply_markup'=>kb(),'text'=>"✅ To'lov qabul qilindi! Buyurtma <b>#$oid</b> tayyorlanmoqda 👨‍🍳"]);
  tg('sendMessage', ['chat_id'=>ADMIN_CHAT_ID,'parse_mode'=>'HTML','text'=>"💰 <b>TO'LANDI</b> #$oid\nSumma: " . money($sum) . "\n👤 $name"]);
  exit;
}

$t = $m['text'] ?? '';
if (strpos($t, '/start') === 0) { send_welcome($chat, $name); exit; }
if ($t === '📍 Lokatsiya') { tg('sendVenue', ['chat_id'=>$chat,'latitude'=>RESTO_LAT,'longitude'=>RESTO_LNG,'title'=>RESTO_NAME,'address'=>RESTO_ADDRESS]); exit; }
if ($t === '📞 Aloqa')     { tg('sendMessage', ['chat_id'=>$chat,'text'=>"📞 " . RESTO_PHONE . "\n📍 " . RESTO_ADDRESS]); exit; }
if ($t === '🕒 Ish vaqti') { tg('sendMessage', ['chat_id'=>$chat,'text'=>"🕒 " . RESTO_HOURS]); exit; }

// Mini app'dan buyurtma (klaviatura tugmasi orqali)
if (isset($m['web_app_data'])) {
  $o = json_decode($m['web_app_data']['data'], true) ?: [];
  process_order($o, $chat, $from);
}
