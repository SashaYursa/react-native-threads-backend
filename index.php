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
    return ['message'=>'no data'];
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
        return sendExeption();
    }
};
function getUserThreads($id, $conn){
    $sql = "
    SELECT threads.*, users.name as author_name, users.image as author_image, (
    SELECT COUNT(*) 
    FROM likes 
    WHERE likes.thread_id = threads.id) AS likes_count
    FROM threads 
    INNER JOIN users ON threads.author_id = users.id
    WHERE threads.author_id = ${id}
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
function getUser($param, $value, $conn){
    $sql = "SELECT * FROM `users` WHERE `users`.`${param}` = '${value}'";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);

    } else {
        return sendExeption();
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
       return sendExeption();
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
            return ['error'=> true, 'message' => 'login failed'];
        }
    } else {
        return ['error'=> true, 'message' => 'login failed'];
    }
};

function insertUser($login, $password, $conn){
    $sql = "INSERT INTO `users` (`id`, `name`, `description`, `password`, `image`) 
            VALUES (NULL, :login, '', :password, NULL)";
    try {

        $sth = $conn->prepare($sql);
        $sth->execute([":login" => $login, ":password" => $password]);
        return $conn->lastInsertId();
    }
    catch (Exception $e){
        return  false;
    }
}

if ($method === 'GET'){
    if ($parts[0] === 'threads'){
        getLastThreads($conn);
    }
    if ($parts[0] === 'users'){
        if (isset($parts[1])) {
            if ($parts[1] === 'check') {
                $user = getUser('name', $parts[2], $conn);
                header('Content-Type: application/json');
                if (isset($user['message'])){
                    echo json_encode(true);
                }
                else echo json_encode(false);
            }
            else {
                $user = getUser('id', $parts[1], $conn);
                $user['threads'] = getUserThreads($parts[1], $conn);
                header('Content-Type: application/json');
                echo json_encode($user);
            }
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

if ($method === 'POST'){
    header('Content-Type: application/json');
    if ($parts[0] === 'users'){
        $data = json_decode(file_get_contents('php://input'), true);
        $login = $data['login'];
        $password = $data['password'];
        $userId = insertUser($login, $password, $conn);
        if ($userId !== false){
            $user = getUser('id', $userId, $conn);
            echo json_encode($user);
        }else{
            echo json_encode(['error'=> 'registration failed']);
        }

    }
}
?>