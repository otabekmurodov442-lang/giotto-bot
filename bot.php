<?php
require __DIR__ . '/config.php';
$u = json_decode(file_get_contents('php://input'), true);
if (!$u) exit;

// Telegram to'lovni tasdiqlashdan oldingi so'rov
if (isset($u['pre_checkout_query'])) {
  tg('answerPreCheckoutQuery', ['pre_checkout_query_id' => $u['pre_checkout_query']['id'], 'ok' => true]);
  exit;
}
if (!isset($u['message'])) exit;

$m = $u['message']; $chat = $m['chat']['id']; $from = $m['from'];
$name = htmlspecialchars(trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')));

function kb() {
  return ['keyboard' => [
    [['text' => '🍽 Menyuni ochish', 'web_app' => ['url' => SITE_URL . '/index.html']]],
    [['text' => '📍 Lokatsiya'], ['text' => '📞 Aloqa']],
    [['text' => '🕒 Ish vaqti']],
  ], 'resize_keyboard' => true];
}

// To'lov muvaffaqiyatli o'tdi
if (isset($m['successful_payment'])) {
  $p = $m['successful_payment']; $oid = htmlspecialchars($p['invoice_payload']); $sum = $p['total_amount'] / 100;
  tg('sendMessage', ['chat_id'=>$chat,'parse_mode'=>'HTML','reply_markup'=>kb(),'text'=>"✅ To'lov qabul qilindi! Buyurtma <b>#$oid</b> tayyorlanmoqda 👨‍🍳"]);
  tg('sendMessage', ['chat_id'=>ADMIN_CHAT_ID,'parse_mode'=>'HTML','text'=>"💰 <b>TO'LANDI</b> #$oid\nSumma: " . money($sum) . "\n👤 $name"]);
  exit;
}

$t = $m['text'] ?? '';
if (strpos($t, '/start') === 0) {
  tg('sendMessage', ['chat_id'=>$chat,'parse_mode'=>'HTML','reply_markup'=>kb(),
    'text'=>"🍕 <b>" . RESTO_NAME . "</b> restoraniga xush kelibsiz, $name!\n\nPitsa, pasta, burger va shirinliklar — hammasi bir joyda. Buyurtma berish uchun pastdagi <b>«Menyuni ochish»</b> tugmasini bosing 👇"]);
  exit;
}
if ($t === '📍 Lokatsiya') { tg('sendVenue', ['chat_id'=>$chat,'latitude'=>RESTO_LAT,'longitude'=>RESTO_LNG,'title'=>RESTO_NAME,'address'=>RESTO_ADDRESS]); exit; }
if ($t === '📞 Aloqa')     { tg('sendMessage', ['chat_id'=>$chat,'text'=>"📞 " . RESTO_PHONE . "\n📍 " . RESTO_ADDRESS]); exit; }
if ($t === '🕒 Ish vaqti') { tg('sendMessage', ['chat_id'=>$chat,'text'=>"🕒 " . RESTO_HOURS]); exit; }

// Mini app'dan buyurtma
if (isset($m['web_app_data'])) {
  $o = json_decode($m['web_app_data']['data'], true) ?: [];
  $lines = []; $prices = []; $sub = 0;
  foreach (($o['items'] ?? []) as $id => $qty) {
    $id = (int)$id; $qty = (int)$qty;
    if (!isset(MENU[$id]) || $qty < 1 || $qty > 50) continue;
    $s = MENU[$id][1] * $qty; $sub += $s;
    $lines[] = "• " . MENU[$id][0] . " × $qty = " . money($s);
    $prices[] = ['label' => mb_substr(MENU[$id][0], 0, 24) . ' ×' . $qty, 'amount' => $s * 100];
  }
  if (!$lines) { tg('sendMessage', ['chat_id'=>$chat,'text'=>"Savat bo'sh 🤷",'reply_markup'=>kb()]); exit; }

  $delivery = ($o['type'] ?? '') === 'delivery';
  $fee = ($delivery && $sub < FREE_DELIVERY_FROM) ? DELIVERY_FEE : 0;
  if ($fee) $prices[] = ['label' => 'Yetkazib berish', 'amount' => $fee * 100];
  $total = $sub + $fee;
  $oid = strtoupper(bin2hex(random_bytes(3)));
  $phone = htmlspecialchars(preg_replace('/[^0-9+]/', '', $o['phone'] ?? ''));
  $addr  = htmlspecialchars(mb_substr(strip_tags($o['address'] ?? ''), 0, 200));
  $note  = htmlspecialchars(mb_substr(strip_tags($o['comment'] ?? ''), 0, 300));
  $lat = $o['lat'] ?? null; $lng = $o['lng'] ?? null;
  $hasLoc = is_numeric($lat) && is_numeric($lng);

  $mode = ($o['pay'] ?? 'cash') === 'card' ? (PAYMENT_TOKEN !== '' ? 'online' : 'terminal') : 'cash';
  $payLabel = ['cash'=>'💵 Naqd','terminal'=>"💳 Karta (terminal orqali)",'online'=>"💳 Karta (onlayn, to'lov kutilmoqda)"][$mode];

  $text = "🧾 <b>Buyurtma #$oid</b>\n" . implode("\n", $lines) . "\n\n"
        . "Taomlar: " . money($sub) . "\n"
        . ($delivery ? "Yetkazish: " . ($fee ? money($fee) : "bepul 🎉") . "\n" : "")
        . "<b>Jami: " . money($total) . "</b>\n\n"
        . ($delivery ? "🛵 Yetkazib berish\n" : "🍴 Zalda\n")
        . "📞 $phone\n" . ($addr ? "🏠 $addr\n" : "") . "$payLabel\n" . ($note ? "💬 $note\n" : "");

  if ($mode === 'online') {
    tg('sendMessage', ['chat_id'=>$chat,'parse_mode'=>'HTML','reply_markup'=>kb(),'text'=>"✅ Buyurtma <b>#$oid</b> qabul qilindi.\n\n$text\nTo'lash uchun pastdagi hisobni oching 👇"]);
    $r = tg('sendInvoice', ['chat_id'=>$chat,'title'=>RESTO_NAME . " buyurtma #$oid",'description'=>"Buyurtma #$oid — " . count($lines) . " xil taom",
      'payload'=>$oid,'provider_token'=>PAYMENT_TOKEN,'currency'=>CURRENCY,'prices'=>$prices]);
    if (!($r['ok'] ?? false)) tg('sendMessage', ['chat_id'=>$chat,'text'=>"⚠️ Onlayn to'lov hozir ishlamayapti. Operator siz bilan bog'lanib, to'lov usulini kelishadi."]);
  } else {
    tg('sendMessage', ['chat_id'=>$chat,'parse_mode'=>'HTML','reply_markup'=>kb(),'text'=>"✅ Buyurtmangiz qabul qilindi! Operator tez orada bog'lanadi.\n\n$text"]);
  }

  $adminText = "🔔 <b>YANGI BUYURTMA</b>\n\n$text\n👤 $name" . (isset($from['username']) ? " (@" . $from['username'] . ")" : "");
  if ($hasLoc) $adminText .= "\n🗺 https://maps.google.com/?q=$lat,$lng";
  tg('sendMessage', ['chat_id'=>ADMIN_CHAT_ID,'parse_mode'=>'HTML','text'=>$adminText]);
  if ($hasLoc) tg('sendLocation', ['chat_id'=>ADMIN_CHAT_ID,'latitude'=>(float)$lat,'longitude'=>(float)$lng]);
}
