<?php

$servername = "localhost"; // Имя сервера базы данных
$username = "root"; // Имя пользователя базы данных
$password = ""; // Пароль пользователя базы данных
$dbname = "threads_api"; // Имя базы данных

include "Database/Database.php";
$conn = $getConnect($servername, $dbname, $username, $password);
// Получите HTTP метод запроса
$method = $_SERVER['REQUEST_METHOD'];

// Получите запрошенный URL
$request = $_SERVER['REQUEST_URI'];

$parts = explode('/', $request);
array_shift($parts);
array_shift($parts);

function sendExeption() {
    header('Content-Type: application/json');
    //http_response_code(400);
    echo json_encode(['message'=>'no data']);
};

function getLastThreads($conn){
    $sql = "
    SELECT threads.*, users.name as author_name, users.image as author_image, (
    SELECT COUNT(*) 
    FROM likes 
    WHERE likes.thread_id = threads.id) AS likes_count
    FROM threads
    INNER JOIN users ON threads.author_id = users.id;
    ";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['images'] = getThreadImages($row['id'], $conn);
            array_push($results, $row);
        }
        header('Content-Type: application/json');
        echo json_encode($results);
    } else {
        sendExeption();
    }
};
function getUserThreads($id, $conn){
    $sql = "
    SELECT threads.*, (
    SELECT COUNT(*) 
    FROM likes 
    WHERE likes.thread_id = threads.id) AS likes_count
    FROM threads WHERE threads.author_id = ${id}
    ";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['images'] = getThreadImages($row['id'], $conn);
            array_push($results, $row);
        }
        return $results;
    } else {
        return [];
    }
}
function getUser($id, $conn){
    $sql = "SELECT * FROM `users` WHERE `users`.`id` = ${id}";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);

    } else {
        sendExeption();
    }
}
function getUsers($conn){
    $sql = "SELECT * FROM `users`";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($results, $row);
        }
        return $results;

    } else {
        sendExeption();
    }
}

function getThreadImages($threadId, $conn){
    $sql = "
    SELECT threads_images.id, threads_images.image_name 
    FROM threads_images
    WHERE threads_images.thread_id = ${threadId};
    ";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            array_push($results, $row);
        }
        return $results;
    } else {
        return [];
    }
};

function checkUser($login, $password, $conn){
    $sql = "
    SELECT users.* 
    FROM users
    WHERE users.name = '${login}';
    ";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['password'] === $password) {
            return $result;
        }else{
            return ['error'=> true];
        }
    } else {
        return ['error'=> true];
    }
};

if ($method === 'GET'){
    if ($parts[0] === 'threads'){
        getLastThreads($conn);
    }
    if ($parts[0] === 'users'){
        if (isset($parts[1])) {
            $user = getUser($parts[1], $conn);
            $user['threads'] = getUserThreads($parts[1], $conn);
            header('Content-Type: application/json');
            echo json_encode($user);
        }
        else{
            $users = getUsers($conn);
            header('Content-Type: application/json');
            echo json_encode($users);
        }
    }
}

if($method === 'PATCH'){
    if($parts[0] === 'users'){
        $data = json_decode(file_get_contents('php://input'), true);
        $user = checkUser($data['login'], $data['password'], $conn);
        if (!isset($user['error'])){
            $user['threads'] = getUserThreads($user['id'], $conn);
        }
        header('Content-Type: application/json');
        echo json_encode($user);

    }
}



//// Определите ресурс и идентификатор (если есть)
//$resource = $parts[0];
//$id = isset($parts[1]) ? $parts[1] : null;
//
//// Создайте массив для ответа
//$response = array();
//
//// Обработка запроса
//if ($method === 'GET') {
//    if ($resource === 'users') {
//        // Вернуть список пользователей или одного пользователя по идентификатору
//        if ($id) {
//            // Запрос на одного пользователя
//            $user = getUserById($id); // Функция, которая получает данные пользователя
//            if ($user) {
//                $response['data'] = $user;
//            } else {
//                $response['error'] = 'Пользователь не найден';
//            }
//        } else {
//            // Запрос на список пользователей
//            $users = getAllUsers(); // Функция, которая получает список пользователей
//            $response['data'] = $users;
//        }
//    } else {
//        $response['error'] = 'Недопустимый ресурс';
//    }
//} else {
//    $response['error'] = 'Недопустимый HTTP метод';
//}
//
//// Отправьте ответ в формате JSON

//
//// Функция для получения данных пользователя
//function getUserById($id) {
//    // Здесь можно выполнить SQL-запрос или другой способ получения данных пользователя
//    return array(/* данные пользователя */);
//}
//
//// Функция для получения списка пользователей
//function getAllUsers() {
//    // Здесь можно выполнить SQL-запрос или другой способ получения списка пользователей
//    return array(/* список пользователей */);
//}

?>