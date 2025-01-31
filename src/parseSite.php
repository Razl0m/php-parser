<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/functions.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$_ENV['URLS'] = explode(';', $_ENV['URLS']);
$_ENV["CHATS_ID"] = explode(";", $_ENV["CHATS_ID"]);
if ($_ENV['APP_ENV'] === 'development') {
    (new \NunoMaduro\Collision\Provider)->register();
}

$db = new SQLite3(__DIR__ . "/../db.sqlite");
$db->query("CREATE TABLE IF NOT EXISTS goods 
        (id INTEGER PRIMARY KEY, name TEXT NOT NULL, price INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

try {
    $goods = Functions\parseSites($_ENV['URLS'], $_ENV['PATTERN_NAME'], $_ENV['PATTERN_PRICE']);
    $message = Functions\checkPriceChange($goods, $db);
    if ($message) {
        Functions\sendTelegramMessage($message, $_ENV["CHATS_ID"], $_ENV['BOT_API_KEY']);
    }
} catch (Exception $e) {
    $message = $e->getMessage() . "\n\n" . $e->getTraceAsString();
    Functions\sendTelegramMessage($message, [$_ENV["CHATS_ID"][0]], $_ENV['BOT_API_KEY']);
}
