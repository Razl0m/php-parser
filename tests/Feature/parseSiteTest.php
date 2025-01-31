<?php

namespace Test;

use SQLite3;

require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../TestCase.php';

it('can parse site, extract, check the price and send telegram message', function (string ...$urls) {
   $this->mock = mock_function("Functions", "file_get_contents", function () {
      static $name = "Product";
      static $iteration = 0;
      static $price = 0;
      $price += 1000;
      $name = "Product" . $iteration++;
      return "<h1>$name</h1><p class=\"price\">$price</p>";
   });

   $db = new SQLite3(':memory:');
   $db->exec('CREATE TABLE goods (id INTEGER PRIMARY KEY, name TEXT NOT NULL, price INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
      
   $price = 1000;
   for ($i=0; $i < count($urls); $i++) { 
      $name = "Product" . $i;
      $db->exec("INSERT INTO goods (name, price) VALUES ('$name', $price)");
      $price += 1000;
   }

   $patternName = '/<h1>(.*?)<\/h1>/';
   $patternPrice = '/<p class="price">(.*?)<\/p>/';
   $chatIDs = ['123456789', '987654321', '123456789'];
   $botAPIKey = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';

   $goods = \Functions\parseSites($urls, $patternName, $patternPrice);
   $this->mock->disable();
   $message = \Functions\checkPriceChange($goods, $db);

   $this->mock = mock_function("Functions", "file_get_contents", function () {
      return '{"ok":true}';
   });

   $response = \Functions\sendTelegramMessage($message, $chatIDs, $botAPIKey);
   $this->assertEquals($response, [true, true, true]);
   $this->mock->disable();
})->with("urls");
