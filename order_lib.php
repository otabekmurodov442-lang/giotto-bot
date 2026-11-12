<?php
require_once __DIR__ . '/config.php';

function kb() {
  return ['keyboard' => [
    [['text' => '🍽 Menyuni ochish', 'web_app' => ['url' => SITE_URL . '/index.html']]],
    [['text' => '📍 Lokatsiya'], ['text' => '📞 Aloqa']],
    [['text' => '🕒 Ish vaqti']],
  ], 'resize_keyboard' => true];
}

// Foydalanuvchi kanalga a'zomi?
function is_member($uid) {
  if (CHANNEL === '') return true;
  $r = tg('getChatMember', ['chat_id' => CHANNEL, 'user_id' => (int)$uid]);
  if (!($r['ok'] ?? false)) return true; // kanal noto'g'ri sozlangan bo'lsa mijozlar to'silmasin (setup.php buni ko'rsatadi)
  $s = $r['result']['status'] ?? '';
  return in_array($s, ['creator', 'administrator', 'member'], true) || ($s === 'restricted' && ($r['result']['is_member'] ?? false));
}
function send_gate($chat) {
  tg('sendMessage', ['chat_id' => $chat, 'parse_mode' => 'HTML',
    'text' => "📢 Botdan foydalanish uchun avval kanalimizga a'zo bo'ling, so'ng <b>«✅ Tekshirish»</b> tugmasini bosing.",
    'reply_markup' => ['inline_keyboard' => [
      [['text' => "📢 Kanalga a'zo bo'lish", 'url' => CHANNEL_LINK]],
      [['text' => '✅ Tekshirish', 'callback_data' => 'check']],
    ]]]);
}
function send_welcome($chat, $name) {
  tg('sendMessage', ['chat_id' => $chat, 'parse_mode' => 'HTML', 'reply_markup' => kb(),
    'text' => "🍕 <b>" . RESTO_NAME . "</b> restoraniga xush kelibsiz, $name!\n\nPitsa, pasta, burger va shirinliklar — hammasi bir joyda. Buyurtma berish uchun pastdagi <b>«Menyuni ochish»</b> tugmasini bosing 👇"]);
}
// Mini app initData imzosini tekshirib, foydalanuvchini qaytaradi
function verify_init($init) {
  if (!$init) return null;
  parse_str($init, $d);
  $hash = $d['hash'] ?? ''; unset($d['hash']);
  ksort($d);
  $pairs = []; foreach ($d as $k => $v) $pairs[] = "$k=$v";
  $secret = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);
  if (!hash_equals(hash_hmac('sha256', implode("\n", $pairs), $secret), $hash)) return null;
  if (time() - (int)($d['auth_date'] ?? 0) > 86400) return null;
  $user = json_decode($d['user'] ?? '', true);
  return ($user && isset($user['id'])) ? $user : null;
}

// Buyurtmani qayta ishlaydi: mijozga, adminga xabar yuboradi, kerak bo'lsa to'lov hisobini chiqaradi
function process_order(array $o, $chat, array $from) {
  $name = htmlspecialchars(trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? '')));
  $lines = []; $prices = []; $sub = 0;
  foreach (($o['items'] ?? []) as $id => $qty) {
    $id = (int)$id; $qty = (int)$qty;
    if (!isset(MENU[$id]) || $qty < 1 || $qty > 50) continue;
    $s = MENU[$id][1] * $qty; $sub += $s;
    $lines[] = "• " . MENU[$id][0] . " × $qty = " . money($s);
    $prices[] = ['label' => mb_substr(MENU[$id][0], 0, 24) . ' ×' . $qty, 'amount' => $s * 100];
  }
  if (!$lines) { tg('sendMessage', ['chat_id'=>$chat,'text'=>"Savat bo'sh 🤷",'reply_markup'=>kb()]); return false; }

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
  return true;
}
