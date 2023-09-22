<?php

$getConnect = function($servername, $dbname, $username, $password){
    try {
        // Создаем подключение к базе данных
        $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);

        // Устанавливаем режим ошибок PDO на выброс исключений
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Выполните SQL-запросы здесь
        return $conn;
    } catch (PDOException $e) {
        echo "Ошибка подключения: " . $e->getMessage();
    }
};
