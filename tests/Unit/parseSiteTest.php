<?php

namespace Test;

use SQLite3;

require_once __DIR__ . '/../../src/functions.php';
require_once __DIR__ . '/../TestCase.php';

describe('mock function', function () {
    it('can parse the site and extract the name and price', function (string ...$urls) {
        $this->mock = mock_function("Functions", "file_get_contents", function () {
            static $name = "Product";
            static $iteration = 0;
            static $price = 0;
            $price += 1000;
            $name = "Product" . $iteration++;
            return "<h1>$name</h1><p class=\"price\">$price</p>";
        });

        $patternName = '/<h1>(.*?)<\/h1>/';
        $patternPrice = '/<p class="price">(.*?)<\/p>/';

        $name = "Product";
        $price = 0;
        for ($i = 0; $i < count($urls); $i++) {
            $price += 1000;
            $expected[$name . $i] = $price;
        }

        $data = \Functions\parseSites($urls, $patternName, $patternPrice);
        expect($data)->toEqual($expected);
    })->with("urls");

    it('can send a message to a telegram chat', function () {
        $this->mock = mock_function("Functions", "file_get_contents", function () {
            return '{"ok":true}';
        });

        $chatIDs = ['123456789', '987654321'];
        $botAPIKey = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
        $message = 'message1';

        $response = \Functions\sendTelegramMessage($message, $chatIDs, $botAPIKey);

        expect($response)->toBeArray()
            ->toHaveCount(2)
            ->toEqual([true, true]);
    });

    it('can handle an error when opening a URL', function () {
        $this->mock = mock_function("Functions", "file_get_contents", function () {
            return false;
        });

        $urls = ['https://example.com'];
        $patternName = '/<h1>(.*?)<\/h1>/';
        $patternPrice = '/<p class="price">(.*?)<\/p>/';

        \Functions\parseSites($urls, $patternName, $patternPrice);
    })->throws(\Exception::class, 'Error opening URL');

    it('can handle an error when getting the name from the HTML', function () {
        $this->mock = mock_function("Functions", "file_get_contents", function () {
            return '<p class="price">1000</p>';
        });

        $urls = ['https://example.com'];
        $patternName = '/<h1>(.*?)<\/h1>/';
        $patternPrice = '/<p class="price">(.*?)<\/p>/';

        \Functions\parseSites($urls, $patternName, $patternPrice);
    })->throws(\Exception::class, 'Cannot get name from html');

    it('can handle an error when getting the price from the HTML', function () {
        $this->mock = mock_function("Functions", "file_get_contents", function () {
            return '<h1>Product</h1>';
        });

        $urls = ['https://example.com'];
        $patternName = '/<h1>(.*?)<\/h1>/';
        $patternPrice = '/<p class="price">(.*?)<\/p>/';

        \Functions\parseSites($urls, $patternName, $patternPrice);
    })->throws(\Exception::class, 'Cannot get price from html');

    it('can handle an error when sending a message', function () {
        $this->mock = mock_function("Functions", "file_get_contents", function () {
            return '{"ok":false, "description":"Error"}';
        });

        $chatIDs = ['123456789', '987654321'];
        $botAPIKey = '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11';
        $message = 'message1';

        \Functions\sendTelegramMessage($message, $chatIDs, $botAPIKey);
    })->throws(\Exception::class, 'Error send message Error');

    afterEach(function () {
        $this->mock->disable();
    });
});

it('can check price change', function () {
    $db = new SQLite3(':memory:');
    $db->exec('CREATE TABLE goods (id INTEGER PRIMARY KEY, name TEXT NOT NULL, price INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $db->exec('INSERT INTO goods (name, price) VALUES ("Product1", 1000)');
    $db->exec('INSERT INTO goods (name, price) VALUES ("Product2", 2000)');

    $goods = [
        "Product1" => 2000,
        "Product2" => 3000,
    ];

    $expected = "Цена на товар Product1 изменилась c 1000 на 2000\nЦена на товар Product2 изменилась c 2000 на 3000\n";
    $message = \Functions\checkPriceChange($goods, $db);
    expect($message)->toEqual($expected);

    $result = $db->query("SELECT * FROM goods");
    $data = [];

    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $data[$row['name']] = $row['price'];
    }

    expect($data)->toEqual($goods);

    $db->close();
});

it('can handle an error when checking price change', function () {
    $db = new SQLite3(':memory:');
    $db->exec('CREATE TABLE goods (id INTEGER PRIMARY KEY, name TEXT NOT NULL, price INT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $db->exec('INSERT INTO goods (name, price) VALUES ("Product1", 1000)');

    $goods = [
        "Product1" => 2000,
        "Product2" => 3000,
    ];

    
    $expect = "Цена на товар Product1 изменилась c 1000 на 2000\nТовар Product2 не найден в базе данных\n";
    $response = \Functions\checkPriceChange($goods, $db);

    expect($response)->toEqual($expect);

    $db->close();
});
