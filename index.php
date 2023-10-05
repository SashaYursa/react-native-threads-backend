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

$getParams = explode('?', array_pop($parts));
array_push($parts, array_shift($getParams));

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
    INNER JOIN users ON threads.author_id = users.id
    ORDER BY threads.id DESC
    LIMIT 40;
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
            $row['comments'] = getCommentsCount($row['id'], $conn);
            $row['likes'] = getThreadLikes($row['id'], $conn);
            array_push($results, $row);
        }
        header('Content-Type: application/json');
        echo json_encode($results);
    } else {
        return sendExeption();
    }
};
function getThread($threaId, $conn){
    $sql = "SELECT *, (SELECT COUNT(*) 
    FROM likes 
    WHERE likes.thread_id = threads.id) AS likes_count
    FROM `threads`
    WHERE threads.id = '${threaId}'";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $res['images'] = getThreadImages($res['id'], $conn);
        $res['comments'] = getAllComments($res['id'], $conn);
        foreach($res['comments'] as $key => $comm){
            $res['comments'][$key]['replies'] = getAllCommentReplies($comm['id'], $conn);
        }
        return $res;
    } else {
        return [];
    }
}
function getUserThreads($id, $conn){
    $sql = "
    SELECT threads.*, users.name as author_name, users.image as author_image, (
    SELECT COUNT(*) 
    FROM likes 
    WHERE likes.thread_id = threads.id) AS likes_count
    FROM threads
    INNER JOIN users ON threads.author_id = users.id
    WHERE threads.author_id = '${id}'
    ORDER BY threads.id DESC
    LIMIT 40
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
            $row['comments'] = getCommentsCount($row['id'], $conn);
            $row['likes'] = getThreadLikes($row['id'], $conn);
            array_push($results, $row);
        }
        header('Content-Type: application/json');
        return $results;
    } else {
        return sendExeption();
    }
}
function getAllComments($threadId, $conn){
    $sql = "SELECT `comments`.*, `users`.`name` as user_name, `users`.`image` as user_image, (SELECT COUNT(*) 
    FROM comments_likes 
    WHERE comments_likes.comment_id = comments.id) AS likes_count FROM `comments`
            INNER JOIN `users` ON `users`.`id` = `comments`.`author_id`
            WHERE `comments`.`thread_id` = '${threadId}' 
            AND `comments`.`reply_to` IS NULL";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['reply_info'] = getReplicesCount($row['id'], $conn);
            $row['likes'] = getCommentLikes($row['id'], $conn);
            $results[] = $row;
        }
        return $results;
    } else {
        return [];
    }
}
function getSingleComment($commentId, $conn){
    $sql = "SELECT `comments`.*, `users`.`name` as user_name, `users`.`image` as user_image, (SELECT COUNT(*) 
    FROM comments_likes 
    WHERE comments_likes.comment_id = comments.id) AS likes_count FROM `comments`
            INNER JOIN `users` ON `users`.`id` = `comments`.`author_id`
            WHERE `comments`.`id` = '${commentId}'";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        $res['reply_info'] = getReplicesCount($res['id'], $conn);
        $res['likes'] = getCommentLikes($res['id'], $conn);
        return $res;
    } else {
        return [];
    }
}
function getReplicesCount($commentId, $conn){
    $sql = "SELECT COUNT(*) as count_reply   FROM comments WHERE comments.reply_to = '${commentId}'";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
    if ($stmt->rowCount() > 0) {

        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['preview_images'] = getPreReplicesImages($commentId, $conn);
            $results[] = $row;
        }
        return $results;
    }
    return 0;
}
function getCommentsCount($threadId, $conn){
    $sql = "SELECT COUNT(*) as comments_count FROM comments WHERE comments.thread_id = '${threadId}'";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
    if ($stmt->rowCount() > 0) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['preview_images'] = getPreCommentsImages($threadId, $conn);
            return $row;
        }
    }
    return 0;
}
function getPreReplicesImages($commentId, $conn){
    $sql = "SELECT DISTINCT users.image FROM comments 
            INNER JOIN users ON users.id = comments.author_id
            WHERE comments.reply_to = '${commentId}'
            LIMIT 2";
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
    }
    return 0;
}
function getPreCommentsImages($threadId, $conn){
    $sql = "SELECT DISTINCT users.image FROM comments 
            INNER JOIN users ON users.id = comments.author_id
            WHERE comments.thread_id = '${threadId}'
            LIMIT 2";
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
    }
    return 0;
}
function getAllCommentReplies($commentId, $conn){
    $sql = "SELECT `comments`.*, `users`.`name` as user_name, `users`.`image` as user_image, (SELECT COUNT(*) 
    FROM comments_likes 
    WHERE comments_likes.comment_id = comments.id) AS likes_count FROM `comments`
            INNER JOIN `users` ON `users`.`id` = `comments`.`author_id`
            WHERE `comments`.`reply_to` = '$commentId'";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        $results = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $row['reply_info'] = getReplicesCount($row['id'], $conn);
            $row['likes'] = getCommentLikes($row['id'], $conn);
            $results[] = $row;
        }
        return $results;
    } else {
        return [];
    }
}

function getUser($param, $value, $conn){
    $sql = "SELECT users.*, (SELECT COUNT(*) FROM subscribers WHERE subscribers.user_id = users.id) AS subs FROM `users` WHERE `users`.`${param}` = '${value}'";
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
function getUsers($userId, $conn){
    $sql = "SELECT users.*, COUNT(subscribers.id) AS subscribers
    FROM users 
    LEFT JOIN subscribers ON users.id = subscribers.user_id
    WHERE `users`.`id` != '$userId'
    GROUP BY users.id
    ORDER BY subscribers DESC LIMIT 10 OFFSET 0";
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
function  getThreadLikes($threadId, $conn){
    $sql = "SELECT * FROM `likes` WHERE likes.thread_id = '${threadId}'";
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
}
function  getCommentLikes($commentId, $conn){
    $sql = "SELECT * FROM `comments_likes` WHERE comments_likes.comment_id = '${commentId}'";
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
}

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

function updateFieldById($table, $param, $value, $id, $conn){
    $sql = "UPDATE `${table}` SET `${param}` = :value WHERE `${table}`.`id` = $id";
    try {
        $sth = $conn->prepare($sql);
        $sth->execute([":value" => $value]);
        return true;
    }
    catch (Exception $e){
        return  false;
    }
}
function updateUser($name, $password, $isPrivate, $description, $id, $conn){
    $sql = "UPDATE `users` SET `name` = :name, 
                   `description` = :description, 
                   `password` = :password, 
                   is_private = $isPrivate
               WHERE `users`.`id` = 1";
    try {
        $sth = $conn->prepare($sql);
        $sth->execute([":name" => $name, ":password" => $password, ":description" =>$description]);
        return true;
    }
    catch (Exception $e){
        return  $e;
    }
}
function checkSubscribe($userId, $selectedUserId, $conn){
    $sql = "SELECT * FROM subscribers WHERE subscribers.user_id = ${selectedUserId} AND subscribers.subscriber_id = $userId";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        return  true;
    } else {
        return false;
    }
}
function addSubscribe($userId, $selectedUserId, $conn){
    $sql = "INSERT INTO `subscribers` (`id`, `user_id`, `subscriber_id`) VALUES (NULL, ${selectedUserId}, ${userId})";
    try {
        $sth = $conn->prepare($sql);
        $sth->execute();
        return true;
    }
    catch (Exception $e){
        return  false;
    }
}
function deleteSubscribe($userId, $selectedUserId, $conn){
    $sql = "DELETE FROM subscribers WHERE `subscribers`.`user_id` = ${selectedUserId} AND `subscribers`.`subscriber_id` = ${userId}";
    try {
        $sth = $conn->prepare($sql);
        $sth->execute();
        return true;
    }
    catch (Exception $e){
        return  false;
    }
}
function insertCommentToThread($comment, $userId, $threadId, $conn){
    $sql = "INSERT INTO `comments` (`id`, `thread_id`, `author_id`, `comment_data`, `created_at`, `reply_to`) 
            VALUES (NULL, :thread_id, :author_id, :comment, current_timestamp(), NULL)";
    try {
        $sth = $conn->prepare($sql);
        $sth->execute([":thread_id" => $threadId, ":author_id" => $userId, ":comment" => $comment]);
        return $conn->lastInsertId();
    }
    catch (Exception $e){
        return  false;
    }
}
function insertCommentRelply($comment, $userId, $threadId, $commentId, $conn){
    $sql = "INSERT INTO `comments` (`id`, `thread_id`, `author_id`, `comment_data`, `created_at`, `reply_to`) 
            VALUES (NULL, :thread_id, :author_id, :comment, current_timestamp(), :comment_id)";
    try {
        $sth = $conn->prepare($sql);
        $sth->execute([":thread_id" => $threadId, ":author_id" => $userId, ":comment" => $comment, ":comment_id" => $commentId]);
        return $conn->lastInsertId();
    }
    catch (Exception $e){
        return  false;
    }
}
function insertThread($authorId, $threadData, $conn){
    $sql = "INSERT INTO `threads` (`id`, `author_id`, `data`, `created_at`) VALUES (NULL, :author_id, :data, current_timestamp())";
    try {
        $sth = $conn->prepare($sql);
        $sth->execute([":author_id" => $authorId, ":data" => $threadData]);
        return $conn->lastInsertId();
    }
    catch (Exception $e){
        return  false;
    }
}
function checkThreadLike($userId, $threadId, $conn){
    $sql = "SELECT * FROM `likes` WHERE likes.user_id = '${userId}' AND likes.thread_id = '${threadId}'";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        return false;
    }
}
function checkCommentLike($userId, $commentId, $conn){
    $sql = "SELECT * FROM `comments_likes` WHERE comments_likes.user_id = '${userId}' AND comments_likes.comment_id = '${commentId}'";
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute();
    } catch(PDOException $e) {
        echo "Error: " . $e->getMessage();
    }

    if ($stmt->rowCount() > 0) {
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        return false;
    }
}
function insertLike($table, $param, $valueId, $userId, $conn){
    $sql = "INSERT INTO `${table}` (`id`, `user_id`, `$param`) VALUES (NULL, :userId, :value)";
    try {
        $sth = $conn->prepare($sql);
        $sth->execute([":value" => $valueId, ":userId" => $userId]);
        return $conn->lastInsertId();
    }
    catch (Exception $e){
        return  false;
    }
}
function insertThreadImage($threadId, $imageName, $conn){
    $sql = "INSERT INTO `threads_images` (`id`, `thread_id`, `image_name`) VALUES (NULL, :threadId, :imageName)";
    try {
        $sth = $conn->prepare($sql);
        $sth->execute([":threadId" => $threadId, ":imageName" => $imageName]);
        return $conn->lastInsertId();
    }
    catch (Exception $e){
        return  false;
    }
}
function removeLike($table, $likeId, $conn){
    $sql = "DELETE FROM $table WHERE `${table}`.`id` = '${likeId}'";
    try {
        $sth = $conn->prepare($sql);
        $sth->execute();
        return true;
    }
    catch (Exception $e){
        return  false;
    }
}

if ($method === 'GET'){
    header('Content-Type: application/json');
    if ($parts[0] === 'threads'){
        if (isset($parts[1])){
            if ($parts[1] === 'user'){
                if (isset($parts[2])){
                    $threads = getUserThreads($parts[2], $conn);
                    echo json_encode($threads);
                }
            }
            else {
                $res = getThread($parts[1], $conn);
                echo json_encode($res);
            }
        }
        else{
            getLastThreads($conn);
        }
    }
    if($parts[0] === 'comments'){
        if (isset($parts[1])){
            if ($parts[1] === 'thread'){
                if (isset($parts[2])){
                    $comments = getAllComments($parts[2], $conn);
                    header('Content-Type: application/json');
                    echo json_encode($comments);
                }
            }
            if($parts[1] === 'replies'){
                if (isset($parts[2])){
                    $replies = getAllCommentReplies($parts[2], $conn);
                    header('Content-Type: application/json');
                    echo json_encode($replies);
                }
            }
        }
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
                $user['is_private'] = boolval($user['is_private']);
                header('Content-Type: application/json');
                echo json_encode($user);
            }
        }
        else{
            if (isset($_GET['userId'])){
            header('Content-Type: application/json');
            $users = getUsers($_GET['userId'], $conn);
            foreach ($users as $key => $user){
               $users[$key]['isSubscribed'] = checkSubscribe($_GET['userId'], $user['id'], $conn);
            }
            echo json_encode($users);
            }

        }
    }
}

if($method === 'PATCH'){
    if($parts[0] === 'users'){
        $data = json_decode(file_get_contents('php://input'), true);
        $user = checkUser($data['login'], $data['password'], $conn);
        header('Content-Type: application/json');
        echo json_encode($user);

    }
}

if ($method === 'POST'){
    header('Content-Type: application/json');
    if ($parts[0] === 'users'){
        if (isset($parts[1])){
            $dir = 'assets/images/users';
            $tmpName = $_FILES['file']['tmp_name'];
            if (!is_dir($dir)) {
                mkdir($dir);
            }
            if (move_uploaded_file($tmpName, $dir . '/' . $_FILES['file']['name'])) {
                echo json_encode(['files' =>$_FILES['file']['tmp_name']]);
            } else {
                echo json_encode(['error' => 'Файл не збережено']);
            }
            $res = updateFieldById('users', 'image', $_FILES['file']['name'], $parts[1], $conn);
            echo  json_encode($res);
        }
        else {
            if (isset($_GET['userId'])){
                $data = json_decode(file_get_contents('php://input'), true);
                $res = [];
                if (checkSubscribe($_GET['userId'], $data['subscribeTo'], $conn)){
                    $remove = deleteSubscribe($_GET['userId'], $data['subscribeTo'], $conn);
                    $res = ['status' => $remove];
                }
                else{
                    $add = addSubscribe($_GET['userId'], $data['subscribeTo'], $conn);
                    $res = ['status' => $add];
                }
                echo json_encode($res);
            }
            else{
                $data = json_decode(file_get_contents('php://input'), true);
                $login = $data['login'];
                $password = $data['password'];
                $userId = insertUser($login, $password, $conn);
                if ($userId !== false) {
                    $user = getUser('id', $userId, $conn);
                    echo json_encode($user);
                } else {
                    echo json_encode(['error' => 'registration failed']);
                }
            }
        }
    }
    if ($parts[0] === 'comments'){
        $data = json_decode(file_get_contents('php://input'), true);
        if(isset($parts[1])){
            if ($parts[1] === 'threads'){
                if (isset($parts[2])){
                    $commentId = insertCommentToThread($data['comment'], $data['authorId'], $data['threadId'], $conn);
                    $comment = getSingleComment($commentId, $conn);
                    echo json_encode($comment);
                }
            }
            if ($parts[1] === 'reply'){
                if(isset($parts[2])){
                    $commentId = insertCommentRelply($data['comment'], $data['authorId'], $data['threadId'], $data['reply'], $conn);
                    $comment = getSingleComment($commentId, $conn);
                    echo json_encode($comment);
                }
            }
        }
    }
    if ($parts[0] === 'threads'){
        if (isset($parts[1])){
            if ($parts[1] === 'images'){
                if (isset($parts[2])){
                    $dir = 'assets/images/threads';
                    $tmpName = $_FILES['file']['tmp_name'];
                    if (!is_dir($dir)) {
                        mkdir($dir);
                    }
                    $temp = explode(".", $_FILES["file"]["name"]);
                    $newfilename = round(microtime(true)) . '.' . end($temp);
                    if (move_uploaded_file($tmpName, $dir . '/' . $newfilename)) {
                    } else {
                        echo json_encode(['error' => 'Файл не збережено']);
                    }
                    $res = insertThreadImage($parts[2], $newfilename, $conn);
                    echo  json_encode( true);
                }
            }
        }
        else {
            $data = json_decode(file_get_contents('php://input'), true);
            $addedThread = insertThread($data['authorId'], $data['data'], $conn);
            echo json_encode(['threadId' => $addedThread]);
        }
    }
}
if ($method === 'PUT'){
    header('Content-Type: application/json');
    $data = json_decode(file_get_contents('php://input'), true);
    if ($parts[0] === 'users'){
        if (isset($parts[1])){

            $data['is_private'] = intval($data['is_private']);
            $res = updateUser($data['name'], $data['password'], $data['is_private'], $data['description'], $parts[1], $conn);

            echo json_encode($res);
        }
    }
    if ($parts[0] === 'threads'){
        if ($parts[1] === 'like'){
            $liked = checkThreadLike($data['userId'], $data['threadId'], $conn);
            $res = false;
            if(!$liked){
                $res['status'] = 'added';
                $res['data'] = insertLike('likes', 'thread_id', $data['threadId'],$data['userId'], $conn);
            }
            else{
                $res['status'] = 'removed';
                if(removeLike('likes' ,$liked['id'], $conn)) {
                    $res['data'] = $liked['id'];
                }
            }
            echo json_encode($res);
        }
    }
    if ($parts[0] === 'comments'){
        if ($parts[1] === 'likes'){
            $liked = checkCommentLike($data['userId'], $data['commentId'], $conn);
            if(!$liked){
                $res['status'] = 'added';
                $res['data'] = insertLike('comments_likes', 'comment_id', $data['commentId'],$data['userId'], $conn);
            }
            else{
                $res['status'] = 'removed';
                if(removeLike('comments_likes' ,$liked['id'], $conn)) {
                    $res['data'] = $liked['id'];
                }
            }
            echo json_encode($res);
        }
    }
}
?>