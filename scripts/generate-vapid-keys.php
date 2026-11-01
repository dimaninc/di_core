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
require $autoload;

$prefix = $argv[1] ?? 'WEB_PUSH_VAPID_';
$keys = \diCore\Push\WebPush::createKeys();

echo "{$prefix}PUBLIC={$keys['publicKey']}\n";
echo "{$prefix}PRIVATE={$keys['privateKey']}\n";
echo "{$prefix}SUBJECT=mailto:\n";
