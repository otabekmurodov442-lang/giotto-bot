<?php
// Token, admin ID, sayt manzili va parol Render'dagi Environment bo'limidan olinadi (faylga yozilmaydi)
define('BOT_TOKEN', getenv('BOT_TOKEN') ?: '');
define('ADMIN_CHAT_ID', (int)(getenv('ADMIN_CHAT_ID') ?: 0));
define('SITE_URL', rtrim(getenv('SITE_URL') ?: '', '/'));
define('SETUP_KEY', getenv('SETUP_KEY') ?: 'maxfiy123');
define('PAYMENT_TOKEN', getenv('PAYMENT_TOKEN') ?: ''); // Telegram to'lov tokeni (BotFather > Payments). Bo'sh bo'lsa onlayn to'lov o'chiq.
const CURRENCY = 'UZS';
// Majburiy obuna kanali: Render > Environment > CHANNEL = @kanal_username (bo'sh bo'lsa tekshiruv o'chiq)
$__ch = trim(getenv('CHANNEL') ?: '');
if ($__ch !== '' && $__ch[0] !== '@' && !preg_match('/^-?\d+$/', $__ch)) $__ch = '@' . $__ch;
define('CHANNEL', $__ch);
define('CHANNEL_LINK', $__ch !== '' && $__ch[0] === '@' ? 'https://t.me/' . substr($__ch, 1) : (getenv('CHANNEL_LINK') ?: ''));

const RESTO_NAME    = 'Giotto';
const RESTO_PHONE   = '+998 90 123 45 67';
const RESTO_ADDRESS = "Toshkent sh., Amir Temur ko'chasi, 1";
const RESTO_LAT     = 41.311081;
const RESTO_LNG     = 69.240562;
const RESTO_HOURS   = 'Har kuni 10:00 – 23:00';
const DELIVERY_FEE  = 15000;     // yetkazib berish narxi
const FREE_DELIVERY_FROM = 150000; // shu summadan oshsa bepul
// ==================================

// id => [nomi, narxi, emoji, kategoriya, tavsif]
const MENU = [
  1  => ["Margarita", 55000, "🍕", "Pitsa", "Pomidor sousi, mozzarella, rayhon"],
  2  => ["Pepperoni", 65000, "🌶️", "Pitsa", "Achchiq pepperoni, mozzarella"],
  3  => ["To'rt pishloq", 72000, "🧀", "Pitsa", "Mozzarella, parmezan, gorgonzola, cheddar"],
  4  => ["Giooto maxsus", 79000, "⭐", "Pitsa", "Mol go'shti, qo'ziqorin, bolgar qalampiri, zaytun"],
  5  => ["Karbonara", 48000, "🍝", "Pasta", "Spagetti, bekon, qaymoq, parmezan"],
  6  => ["Bolonez", 46000, "🍅", "Pasta", "Spagetti, mol go'shtli sous"],
  7  => ["Alfredo", 49000, "🍤", "Pasta", "Fettuchini, tovuq, qaymoqli sous"],
  8  => ["Lazanya", 52000, "🥘", "Pasta", "Qatlamli pasta, bolonez, bechamel"],
  9  => ["Sezar salati", 38000, "🥗", "Salat", "Tovuq, romano, kruton, sezar sousi"],
  10 => ["Kaprese", 36000, "🍅", "Salat", "Pomidor, mozzarella, pesto"],
  11 => ["Yunon salati", 34000, "🫒", "Salat", "Bodring, pomidor, feta, zaytun"],
  12 => ["Tovuqli burger", 42000, "🍔", "Burger", "Qarsildoq tovuq, sous, yangi sabzavot"],
  13 => ["Mol go'shtli burger", 52000, "🍔", "Burger", "Mol go'shti kotleti, cheddar, karamel piyoz"],
  14 => ["Kartoshka fri", 20000, "🍟", "Burger", "Qarsildoq kartoshka, sous bilan"],
  15 => ["Tiramisu", 30000, "🍰", "Shirinlik", "Klassik italyan shirinligi"],
  16 => ["Chizkeyk", 32000, "🍮", "Shirinlik", "Smetanali, qulupnay sousi bilan"],
  17 => ["Dondirma", 18000, "🍨", "Shirinlik", "3 sharik: vanil, shokolad, qulupnay"],
  18 => ["Limonad", 18000, "🍋", "Ichimlik", "Uy limonadi, yalpiz bilan"],
  19 => ["Kapuchino", 22000, "☕", "Ichimlik", "Yangi qovurilgan kofe"],
  20 => ["Coca-Cola 0.5", 12000, "🥤", "Ichimlik", "Sovuq"],
  21 => ["Choy (choynak)", 10000, "🍵", "Ichimlik", "Qora yoki ko'k"],
];

function tg($method, $data = []) {
  $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/$method");
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true, CURLOPT_TIMEOUT => 15,
    CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  ]);
  $res = curl_exec($ch);
  if ($res === false) file_put_contents(__DIR__ . '/log.txt', date('c') . " curl xato: " . curl_error($ch) . "\n", FILE_APPEND);
  curl_close($ch);
  $j = json_decode((string)$res, true);
  if (!($j['ok'] ?? false)) file_put_contents(__DIR__ . '/log.txt', date('c') . " $method: $res\n", FILE_APPEND);
  return $j;
}
function money($n) { return number_format($n, 0, '', ' ') . " so'm"; }
