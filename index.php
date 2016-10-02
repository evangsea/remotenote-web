<?php
/**
 * remotenote-web
 *
 * @author Evan Seabrook <evan.g.seabrook@gmail.com>
 * @copyright 2016 Evan Seabrook
 * @license Free to distribute, modify, and publish
 *
 */

require 'vendor/autoload.php';

use \Slim\Middleware\HttpBasicAuthentication\PdoAuthenticator;

$app = new Slim\App();

global $dbh;

define('DB_SERVER', 'localhost');
define('DB_NAME', 'remotenote');
define('DB_USER', 'remotenote');
define('DB_PASSWORD', 'password');

try {
    $dbh = new PDO("mysql:host=" . DB_SERVER . ";dbname=" . DB_NAME, DB_USER, DB_PASSWORD);

    if (!is_object($dbh)) {
        throw new Exception("Unable to connect to database");
    }

} catch(Exception $e) {
    die("Exception thrown: " . $e->getMessage());
}

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "path" => array("/getNotes", "/newNote"),
    "authenticator" => new PdoAuthenticator([
        "pdo" => $dbh,
        "table" => "users",
        "user" => "username",
        "hash" => "password"
        ])
    ]));

$app->get('/', function ($request, $response, $args) {
    $newResponse = $response->withStatus(501);
    $body = $response->getBody();
    $body->write("<h1>501</h1>No action specified.");
    return $newResponse;
});

$app->post("/newNote", function ($request, $response, $args) use ($dbh) {
    $postData = $request->getParsedBody();
    if (!array_key_exists('title', $postData) || !array_key_exists('content', $postData)) {
        $newResponse = $response->withStatus(400);
        $body = $newResponse->getBody();
        return $newResponse;
    }
    $title = $postData['title'];
    $content = $postData['content'];

    if (strlen($title) == 0 || strlen($content) == 0) {
        $newResponse = $response->withStatus(400);
        $body = $newResponse->getBody();
        return $newResponse;
    }

    $username = $request->getHeaders()['PHP_AUTH_USER'][0];
    try {
        $stmt = $dbh->prepare("select id from users where username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user_id = $stmt->fetch(PDO::FETCH_ASSOC)['id'];

        if (empty($user_id)) {
            $newResponse = $response->withStatus(500);
            $body = $newResponse->getBody();
            $body->write("User ID not found.");
            return $newResponse;
        }

        $stmt = $dbh->prepare("insert into notes (user_id, title, content) VALUES (:id,:title,:content)");

        $stmt->bindParam(':id', $user_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':content', $content);
        if ($stmt->execute()) {
            $data = [
                "success" => 1
            ];
            $newResponse = $response->withJson($data);
            return $newResponse;
        } else {
            $newResponse = $response->withStatus(500);
            $body = $newResponse->getBody();
            $body->write("Query execution error.");
            return $newResponse;
        }
    } catch (PDOException $e) {
        $newResponse = $response->withStatus(500);
        $body = $newResponse->getBody();
        $body->write("General database error.");
        return $newResponse;
    }

});

$app->get("/getNotes", function ($request, $response, $args) use ($dbh) {
    $data = [];
    $username = $request->getHeaders()['PHP_AUTH_USER'][0];
    $user_id = '';
    try 
    {
        $stmt = $dbh->prepare("select id from users where username = :username ORDER BY id ASC");

        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user_id = $stmt->fetch()['id'];
        if (empty($user_id)) {
            // that's unfortunate... user deleted in critical region
            $newResponse = $response->withStatus(500);
            return $response;
        }
        $stmt = $dbh->prepare("select * from notes where user_id = :userid");
        $stmt->bindParam(':userid', $user_id);
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $newResponse = $response->withJson($result);
        return $newResponse;
    } catch(PDOException $e) {
        $newResponse = $response->withStatus(500);
        return $response;
    }

});

$app->get("/deleteNote", function ($request, $response, $args) use ($dbh) {
    $data = [];
    $username = $request->getHeaders()['PHP_AUTH_USER'][0];
    var_dump($username);
    $id = $request->getParam('id');
    $user_id = '';
    try 
    {
        $stmt = $dbh->prepare("select id from users where username = :username");

        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user_id = $stmt->fetch()['id'];	
	var_dump($user_id);
        if (empty($user_id)) {
            // that's unfortunate... user deleted in critical region
            $newResponse = $response->withStatus(500);
            return $response;
        }
        $stmt = $dbh->prepare("delete from notes where user_id = :userid and id = :id");
        $stmt->bindParam(':userid', $user_id);
	$stmt->bindParam(':id', $id);
        $stmt->execute();
        $newResponse = $response->withStatus(200);
        return $newResponse;
    } catch(PDOException $e) {
        $newResponse = $response->withStatus(500);
        return $response;
    }

});

$app->get("/getNote", function ($request, $response, $args) use ($dbh) {
    $data = [];
    $username = $request->getHeaders()['PHP_AUTH_USER'][0];
    var_dump($username);
    $id = $request->getParam('id');
    $user_id = '';
    try 
    {
        $stmt = $dbh->prepare("select id from users where username = :username");

        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $user_id = $stmt->fetch()['id'];	
	var_dump($user_id);
        if (empty($user_id)) {
            // that's unfortunate... user deleted in critical region
            $newResponse = $response->withStatus(500);
            return $response;
        }
        $stmt = $dbh->prepare("select * from notes where user_id = :userid and id = :id");
        $stmt->bindParam(':userid', $user_id);
	$stmt->bindParam(':id', $id);
        $stmt->execute();
	$data = $stmt->fetch();
        $newResponse = $response->withStatus(200)->withJson($data);
        return $newResponse;
    } catch(PDOException $e) {
        $newResponse = $response->withStatus(500);
        return $response;
    }

});

$app->post("/newUser", function ($request, $response, $args) use ($dbh) {
    $postParams = $request->getParsedBody();
    $failedPostCond = false;
    $username = $password = '';

    if (array_key_exists('username', $postParams) && array_key_exists('password', $postParams)) {
        $username = $postParams["username"];
        $password = $postParams["password"];
        if (strlen(trim($username)) == 0 || strlen(trim($password)) == 0) {
            $failedPostCond = true;
		echo "true";
        }
    } else {
        $failedPostCond = true;
    }
    if (!$failedPostCond) {
        // check to see if username already exists
        try {
            $stmt = $dbh->prepare("select count(*) as numUsers from users where username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $results = $stmt->fetch();
            if ($results['numUsers'] == 0) {
                $stmt = $dbh->prepare("insert into users (username, password) values (:username, :password)");
                $password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $password);
                if ($stmt->execute()) {
                    $returnval = [
                        "success" => 1
                    ];
                    $newResponse = $response->withJson($returnval);
                    return $newResponse;
                }
            }
        } catch(PDOException $e) {
            // nothing to do here, already taken care of.
        }
    }
    $newResponse = $response->withStatus(500);
    return $newResponse;
});

$app->run();
