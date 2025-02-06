<?php

namespace Functions;

function parseSites($URLS, $patternName, $patternPrice): array
{
   $goods = array();
   foreach ($URLS as $URL) {
      $html = file_get_contents($URL);
      if ($html === FALSE) {
         throw new \Exception("Error opening URL", 1);
         continue;
      }

      if (preg_match($patternName, $html, $matches)) {
         $name = $matches[1];
      } else {
         throw new \Exception("Cannot get name from html", 1);
      }

      if (preg_match($patternPrice, $html, $matches)) {
         $price = $matches[1];
      } else {
         throw new \Exception("Cannot get price from html", 1);
      }

      $goods[$name] = $price;
   }
   return $goods;
}

function sendTelegramMessage($message, $chatIDs, $botAPIKey): array
{
   $respose = array();
   foreach ($chatIDs as $chatID) {
      $url = "https://api.telegram.org/bot$botAPIKey/sendMessage";
      $data = [
         'chat_id' => $chatID,
         'text' => $message,
      ];

      // Отправка POST запроса
      $options = [
         'http' => [
            'header' => "Content-Type: application/x-www-form-urlencoded",
            'method' => 'POST',
            'content' => http_build_query($data),
         ],
      ];
      $context = stream_context_create($options);
      $requestResponse = file_get_contents($url, false, $context);
      $requestResponse = json_decode($requestResponse, true);
      if ($requestResponse["ok"]) {
         $response[] = true;
      } else {
         throw new \Exception("Error send message " . $requestResponse["description"], 1);
      }
   }
   return $response;
}

function checkPriceChange($goods, $db): string
{
   $message = "";
   foreach ($goods as $name => $price) {
      $oldPrice = $db->querySingle("SELECT price FROM goods WHERE name = '$name' ORDER BY id DESC LIMIT 1");
      $db->query("INSERT INTO goods (name, price) VALUES('{$name}', '{$price}')");
      if ($oldPrice == NULL) {
         $message .= "Товар $name не найден в базе данных\n";
      } elseif ($oldPrice != $price) {
         $message .= "Цена на товар $name изменилась c $oldPrice на $price\n";
      }
   }
   return $message;
}
