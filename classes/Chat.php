<?php
/**
 * Created by PhpStorm.
 * User: admin
 * Date: 2019-03-29
 * Time: 15:18
 */

class Chat
{
    // Метод для отправки определенных заголовков в клиентскую часть
    public function sendHeaders($headersText, $newSocket, $host, $port)
    {
        $headers = [];
        $tmpLine = preg_split("/\r\n/", $headersText);

        foreach ($tmpLine as $line) {
            //Убираем лишние пробелы
            $line = rtrim($line);
            if (preg_match('/\A(\S+): (.*)\z/', $line, $matches)) {
                $headers[$matches[1]] = $matches[2];
            }
        }

        $key = $headers['Sec-WebSocket-Key'];
        $sKey = base64_encode(pack('H*', sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

        // Здесь будут храниться заголовки которые бдут отправляться на сервер
        $strHeadr =
            "HTTP/1.1 101 Switching Protocol \r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "WebSocket-Origin: $host\r\n" .
            "WebSocket-Location: ws://$host:$port/testworks/server.php\r\n" .
            "Sec-WebSocket-Accept:$sKey\r\n\r\n";

        // Записываем в сокет конкретные данные
        socket_write($newSocket, $strHeadr, strlen($strHeadr));
    }

    // Формируем сообщение о новом соединении которое будет отправляться пользователям
    public function newConnectionACK($client_ip_address)
    {
        $message = 'New client ' . $client_ip_address . 'connected';

        // Формируем массив данных которые будут отправлены
        $messageArray = [
            'message' => $message,
            'type' => 'newConnectionACK'
        ];

        // Преобразуем массив к определенной последовательности байт котороая будет отпаравлена в клиентскую часть
        $ask = $this->seal(json_encode($messageArray));

        return $ask;
    }

    // Метод который осуществляет передачу последовательности байтов на клиентскую часть
    public function send($message, $clientSocketArray)
    {
        $messageLength = strlen($message);

        foreach ($clientSocketArray as $clientSocket) {
            @socket_write($clientSocket, $message, $messageLength);
        }

        return true;
    }

    // Метод который преобразует строковый тип в последовательность байтов которые отправляются клиенту
    public function seal($socketData)
    {
        $b1 = 0x81;
        $length = strlen($socketData);
        $header = '';
        if ($length <= 125) {
            $header = pack('CC', $b1, $length); // передаем 7 бит
        } elseif ($length > 125 && $length < 65536) {
            $header = pack('CCn', $b1, 126, $length); // передаем 16 бит
        } elseif ($length > 65536) {
            $header = pack('CCNN', $b1, 127, $length); // передаем 64 бит
        }
        return $header . $socketData;
    }

    // Обратная функция методу seal
    public function unseal($socketData)
    {

        // Конвертируем первый байт в число и сравниваем с числом 127
        $length = ord($socketData[1]) & 127;

        if ($length == 126) {
            $mask = substr($socketData, 4, 4);
            $data = substr($socketData, 8);
        } elseif ($length == 127) {
            $mask = substr($socketData, 10, 4);
            $data = substr($socketData, 14);
        } else {
            // Получаем ключ маски начиная с 2 индекса и берем 4 байта
            $mask = substr($socketData, 2, 4);

            // Получаем данные
            $data = substr($socketData, 6);
        }

        // Строка которую будем возвращать
        $socketStr = '';

        for ($i = 0, $iMax = strlen($data); $i < $iMax; ++$i) {
            $socketStr .= $data[$i] ^ $mask[$i % 4];
        }

        return $socketStr;

    }

    // Создание готового сообщения которое будет отправлено пользователю
    public function createChatMessage($username, $messageStr)
    {

        // Текст который бкдет отправлен пользователю
        $message = $username . '<div>" . $messageStr . "</div>';

        // Массив который отправляем на клиентскую часть
        $messageArray = [
            'type' => 'chat-box',
            'message' => $message
        ];

        // Прербразовываем массив в строку это сделает метод seal который мы описывали ранее
        return $this->seal(json_encode($messageArray));
    }
}