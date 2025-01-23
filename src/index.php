<?php

require_once 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$db = new SQLite3(__DIR__ . "/db.sqlite");
$db->query("CREATE TABLE IF NOT EXISTS goods 
         (id INTEGER PRIMARY KEY, name TEXT NOT NULL, price INT NOT NULL,
          created_at DATETIME DEFAULT CURRENT_TIMESTAMP)");

$urls = explode(';', $_ENV['URLS']);
$chatIds = explode(";", $_ENV["CHATID"]);

function parseSites($urls): array
{
    $goods = array();
    foreach ($urls as $key => $url) {
        $html = file_get_contents($url);
        if ($html === FALSE) {
            exit('Error opening URL');
        }

        $patternPrice = $_ENV["PATTERNPRICE"];
        $patternName = $_ENV["PATTERNNAME"];

        if (preg_match($patternPrice, $html, $matches)) {
            $price = $matches[1];
        } else {
            throw new Exception("Cannot get price from html", 1);
        }
        if (preg_match($patternName, $html, $matches)) {
            $name = $matches[1];
        } else {
            throw new Exception("Cannot get name from html", 1);
        }

        $goods[$key]["name"] = $name;
        $goods[$key]["price"] = $price;
    }
    return $goods;
}

function sendTelegramMessage($message, $chatIds)
{
    foreach ($chatIds as $chatId) {
        $url = 'https://api.telegram.org/bot' . $_ENV["BOTAPIKEY"] . '/sendMessage';
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
        ];

        // Отправка POST запроса
        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data),
            ],
        ];
        $context = stream_context_create($options);
        file_get_contents($url, false, $context);
    }
}

function sendErrors($message, $chatId) {
    $url = 'https://api.telegram.org/bot' . $_ENV["BOTAPIKEY"] . '/sendMessage';
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
    ];

    // Отправка POST запроса
    $options = [
        'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($data),
        ],
    ];
    $context = stream_context_create($options);
    file_get_contents($url, false, $context);
}

function checkPriceChange($urls, $db, $chatIds)
{
    $goods = parseSites($urls, $db);
    $message = "";
    foreach ($goods as $good) {
        $response = $db->querySingle("SELECT price FROM goods WHERE name = '{$good["name"]}' ORDER BY id DESC LIMIT 1");
        $db->query("INSERT INTO goods (name, price) VALUES('{$good["name"]}', '{$good["price"]}')");
        if ($response == NULL) {
            sendErrors("Товар {$good["name"]} не найден в базе данных", $chatIds[0]);
            continue;
        }
        if ($response != $good["price"]) {
            $message = $message . "Цена на товар {$good["name"]} изменилась c $response на {$good['price']}\n";
        }
    }
    if ($message) {
        sendTelegramMessage($message, $chatIds);
    }
}

try {
    checkPriceChange($urls, $db, $chatIds);
} catch (\Throwable $th) {
    sendErrors($th->getMessage(), $chatIds[0]);
}