<?php
require __DIR__ . '/config.php';
$u = json_decode(file_get_contents('php://input'), true);
if (!$u || !isset($u['message'])) exit;
$m = $u['message']; $chat = $m['chat']['id']; $from = $m['from'];
$name = htmlspecialchars(trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')));

function kb() {
  return ['keyboard' => [
    [['text' => '🍽 Menyuni ochish', 'web_app' => ['url' => SITE_URL . '/index.html']]],
    [['text' => '📍 Lokatsiya'], ['text' => '📞 Aloqa']],
    [['text' => '🕒 Ish vaqti']],
  ], 'resize_keyboard' => true];
}

$t = $m['text'] ?? '';
if (strpos($t, '/start') === 0) {
  tg('sendMessage', ['chat_id'=>$chat,'parse_mode'=>'HTML','reply_markup'=>kb(),
    'text'=>"🍕 <b>" . RESTO_NAME . "</b> restoraniga xush kelibsiz, $name!\n\nPitsa, pasta, burger va shirinliklar — hammasi bir joyda. Buyurtma berish uchun pastdagi <b>«Menyuni ochish»</b> tugmasini bosing 👇"]);
  exit;
}
if ($t === '📍 Lokatsiya') {
  tg('sendVenue', ['chat_id'=>$chat,'latitude'=>RESTO_LAT,'longitude'=>RESTO_LNG,'title'=>RESTO_NAME,'address'=>RESTO_ADDRESS]);
  exit;
}
if ($t === '📞 Aloqa') {
  tg('sendMessage', ['chat_id'=>$chat,'text'=>"📞 " . RESTO_PHONE . "\n📍 " . RESTO_ADDRESS]); exit;
}
if ($t === '🕒 Ish vaqti') {
  tg('sendMessage', ['chat_id'=>$chat,'text'=>"🕒 " . RESTO_HOURS]); exit;
}

// Mini app'dan buyurtma
if (isset($m['web_app_data'])) {
  $o = json_decode($m['web_app_data']['data'], true) ?: [];
  $lines = []; $sub = 0;
  foreach (($o['items'] ?? []) as $id => $qty) {
    $id = (int)$id; $qty = (int)$qty;
    if (!isset(MENU[$id]) || $qty < 1 || $qty > 50) continue;
    $s = MENU[$id][1] * $qty; $sub += $s;
    $lines[] = "• " . MENU[$id][0] . " × $qty = " . money($s);
  }
  if (!$lines) { tg('sendMessage', ['chat_id'=>$chat,'text'=>"Savat bo'sh 🤷",'reply_markup'=>kb()]); exit; }

  $delivery = ($o['type'] ?? '') === 'delivery';
  $fee = ($delivery && $sub < FREE_DELIVERY_FROM) ? DELIVERY_FEE : 0;
  $total = $sub + $fee;
  $phone = htmlspecialchars(preg_replace('/[^0-9+]/', '', $o['phone'] ?? ''));
  $addr  = htmlspecialchars(mb_substr(strip_tags($o['address'] ?? ''), 0, 200));
  $note  = htmlspecialchars(mb_substr(strip_tags($o['comment'] ?? ''), 0, 300));
  $pay   = ($o['pay'] ?? 'cash') === 'card' ? '💳 Karta' : '💵 Naqd';
  $lat = $o['lat'] ?? null; $lng = $o['lng'] ?? null;
  $hasLoc = is_numeric($lat) && is_numeric($lng);

  $text = "🧾 <b>Buyurtma</b>\n" . implode("\n", $lines) . "\n\n"
        . "Taomlar: " . money($sub) . "\n"
        . ($delivery ? "Yetkazish: " . ($fee ? money($fee) : "bepul 🎉") . "\n" : "")
        . "<b>Jami: " . money($total) . "</b>\n\n"
        . ($delivery ? "🛵 Yetkazib berish\n" : "🍴 Zalda\n")
        . "📞 $phone\n" . ($addr ? "🏠 $addr\n" : "") . "$pay\n" . ($note ? "💬 $note\n" : "");

  tg('sendMessage', ['chat_id'=>$chat,'parse_mode'=>'HTML','reply_markup'=>kb(),
    'text'=>"✅ Buyurtmangiz qabul qilindi! Operator tez orada bog'lanadi.\n\n$text"]);

  $adminText = "🔔 <b>YANGI BUYURTMA</b>\n\n$text\n👤 $name" . (isset($from['username']) ? " (@" . $from['username'] . ")" : "");
  if ($hasLoc) $adminText .= "\n🗺 https://maps.google.com/?q=$lat,$lng";
  tg('sendMessage', ['chat_id'=>ADMIN_CHAT_ID,'parse_mode'=>'HTML','text'=>$adminText]);
  if ($hasLoc) tg('sendLocation', ['chat_id'=>ADMIN_CHAT_ID,'latitude'=>(float)$lat,'longitude'=>(float)$lng]);
}
