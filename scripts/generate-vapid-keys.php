<?php
/**
 * Новая пара ключей VAPID для diCore\Push\WebPush. Печатает строки для .env, ничего не пишет.
 * Из корня проекта: php vendor/dimaninc/di_core/scripts/generate-vapid-keys.php [prefix]
 * Смена пары на проде делает недействительными все сохранённые подписки браузеров.
 */

$autoload = getcwd() . '/vendor/autoload.php';
if (!is_file($autoload)) {
    $autoload = __DIR__ . '/../vendor/autoload.php';
}
if (!is_file($autoload)) {
    fwrite(STDERR, "Run from the project root (vendor/autoload.php not found)\n");
    exit(1);
}
require $autoload;
if (!class_exists(\Minishlink\WebPush\VAPID::class)) {
    fwrite(STDERR, "Install minishlink/web-push ^11 first\n");
    exit(1);
}

$prefix = $argv[1] ?? 'WEB_PUSH_VAPID_';
$keys = \diCore\Push\WebPush::createKeys();

echo "{$prefix}PUBLIC={$keys['publicKey']}\n";
echo "{$prefix}PRIVATE={$keys['privateKey']}\n";
// Пустой SUBJECT – web push выключен, пока не вписан контакт: «mailto:» без адреса Apple отвергает 403.
echo "{$prefix}SUBJECT=\n";
fwrite(STDERR, "Fill {$prefix}SUBJECT: mailto:<support address> or https://<site>\n");
