<?php
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
if (PHP_SAPI == 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $file = __DIR__ . $_SERVER['REQUEST_URI'];
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Instantiate the app
$settings = require __DIR__ . '/../src/settings.php';
$app = new \Slim\App($settings);

// Set up dependencies
require __DIR__ . '/../src/dependencies.php';

// Register middleware
require __DIR__ . '/../src/middleware.php';

// Database handler
function getDBH() {
	try {
		$dbh = new PDO("mysql:host=localhost;dbname=remotenote", 'remotenote', 'password');
	    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	    return $dbh;
	} catch(PDOException $e) {
		die("Database connection failed");
	}
	return -1;
}
// Register routes
require __DIR__ . '/../src/routes.php';
$app->post('/login', function(Request $request, Response $response) {
	$resultant = array();
	$data = $request->getParsedBody();
	$username = trim($data['username']);
	$password = trim($data['password']);
	$dbh = getDBH();
	if($dbh == -1) {
		die("oh no");
	}
	try {
	$stmt = $dbh->prepare("select token from auth_tokens where user_id in (select id from users where username = :username and password = sha1(:password))");
	$stmt->bindParam(':username', $username);
	$stmt->bindParam(':password', $password);
	$stmt->execute();
	$resultant = $stmt->fetch(PDO::FETCH_OBJ);
} catch(PDOException $e) {
	$resultant = array($e->getMessage());
}
	$nextResponse = $response->withHeader("Content-type", 'application/json');
	$nextResponse->getBody()->write(json_encode($resultant));
	return $nextResponse;
});
// Run app
$app->run();
