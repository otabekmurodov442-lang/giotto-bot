<?php
// Brauzerda oching: https://sizning-domen.uz/giooto/setup.php?key=maxfiy123
require __DIR__ . '/config.php';
header('Content-Type: text/plain; charset=utf-8');
if (($_GET['key'] ?? '') !== SETUP_KEY) die('key noto\'g\'ri');
echo "Webhook:\n";
print_r(tg('setWebhook', ['url' => SITE_URL . '/bot.php', 'drop_pending_updates' => true]));
echo "\nMenyu tugmasi:\n";
print_r(tg('setChatMenuButton', ['menu_button' => ['type'=>'web_app','text'=>'🍽 Menyu','web_app'=>['url'=>SITE_URL.'/index.html']]]));
echo "\nHolat (last_error_message bo'lsa — muammo shu yerda):\n";
print_r(tg('getWebhookInfo'));
if (CHANNEL !== '') {
  echo "\nKanal tekshiruvi (" . CHANNEL . ") — [ok] => 1 bo'lsa bot kanalga kira oladi:\n";
  print_r(tg('getChatMember', ['chat_id' => CHANNEL, 'user_id' => ADMIN_CHAT_ID]));
} else {
  echo "\nKanal: CHANNEL o'rnatilmagan (majburiy obuna o'chiq)\n";
}
