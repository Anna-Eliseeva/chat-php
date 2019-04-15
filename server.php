<?php
define('PORT', '8080');

require_once __DIR__ . '/classes/Chat.php';

$chat = new Chat();

// Открывает сокет соединение по протолоку TCP
// SOCK_STREAM обеспечивает надежность байтовых потоков с установлением соединения
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

// Определяем настройки для сокета
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, 0, PORT);

// Включаем прослушивание сокета по порту который указали
socket_listen($socket);

// Массив из ресурсов сокетов
$clientSocketArray = [$socket];

// Делаем условием для того чтобы данный скрипт работал постоянно
while (true) {
    // Здесь будут новые сокеты которые будем применять
    $newSocketArray = $clientSocketArray;
    $nullA = [];

    // Вызываем системный селект для заданных сокетов с заданным таймаутом
    socket_select($newSocketArray, $nullA, $nullA, 0, 10);

    if(in_array($socket, $newSocketArray, true)) {
        // Включаем прослушивание сокета по данному ресурсу и принимаем определенный сокет от клиента
        $newSocket = socket_accept($socket);

        // Чтобы каждый новый сокет был в массиве указываем доп ячейку
        $clientSocketArray[] = $newSocket;

        // Читаем принимаемые сокет
        $header = socket_read($newSocket, 1024);

        // Отправляем заголовки на сервер
        $chat->sendHeaders($header, $newSocket, 'localhost/testworks', PORT);

        // Получаем IP адрес конкеретного пользователя
        socket_getpeername($newSocket, $client_ip_address);

        $connectionACK = $chat->newConnectionACK($client_ip_address);

        // Отправляем пользователю заданную последовательность байтов
        $chat->send($connectionACK, $clientSocketArray);

        // Данная переменная будет хранить в себе индекс обработанного сокета
        $newSocketArrayIndex = array_search($socket, $newSocketArray, true);

        foreach ($newSocketArray as $newSocketArrayResource) {
            // Читаем информацию поэтапно из каждого конкретного сокета
            while(socket_recv($newSocketArrayResource, $socketData, 1024, 0) >= 1) {
                // Преобразуем последовательность байтов в JSON строку
                $socketMessage = $chat->unseal($socketData);

                // Обьект который отправляет нам клиентская часть
                $messageObj = json_decode($socketMessage);

                echo "\n\n\n\n";
                var_dump($messageObj);
                echo "\n\n\n\n";

                // Данная переменная будет хранить готовое сообщение к отправке
                $chatMessage = $chat->createChatMessage($messageObj->chat_user, $messageObj->chat_message);

                // Отпправляем данные
                $chat->send($chatMessage, $clientSocketArray);

                break 2;
            }
            // Обработка сокетов котрые нами использоваться не будут

        }

        // Удаляем индекс обработанного сокета
        unset($newSocketArray[$newSocketArrayIndex]);
    }

}

//Закрываем сокет соединение
socket_close($socket);