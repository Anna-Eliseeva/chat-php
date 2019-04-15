$(document).ready(function () {

    //Вывод системных сообщений
    function message(text) {
        $('#chat-result').append(text);
    }

    let socket = new WebSocket("ws://localhost:8080/testworks/server.php");

    //Событие сработает при соединении с сервером
    socket.onopen = function () {
        message("<div>Соединение установлено</div>");
    };

    //Событие сработает при возникновении ошибок
    socket.onerror = function (error) {
        message("<div>Ошибка при соединении " + (error.message ? error.message : "") + "</div>");
    };

    //Событие сработает при закрытии соединения
    socket.onclose = function () {
        message("<div>Соединение закрыто</div>");
    };
    
    //Событие срабатывает когда сервер отправляет какие-либо данные в клиентскую часть
    socket.onmessage = function (event) {
        let data = JSON.parse(event.data);
        message("<div>" + data.type + "--" + data.message + " </div>");
    };

    // Обработка формы
    $('#chat').on('submit', function () {

      let message = {
          chat_message: $('#chat-message').val(),
          chat_user: $('#chat-user').val(),
      };

      // Если пользователь уже вводил имя форму снова ему не показываем
      $('#chat-user').attr('type', 'hidden');

      // Отправляем сообщение преобразуя ее в строку
      socket.send(JSON.stringify($message));
        return false;
    });
});