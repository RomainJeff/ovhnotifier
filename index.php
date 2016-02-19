<?php
session_start();

require_once __DIR__ ."/vendor/autoload.php";
require_once __DIR__ ."/OVHServerAvailability.php";
require_once __DIR__ ."/conf/database.php";


$app = new Silex\Application();
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/twig',
));
$app->register(new Silex\Provider\SwiftmailerServiceProvider());
$app['swiftmailer.options'] = [
    'host' => 'SMTP',
    'port' => 'PORT',
    'username' => 'EMAIL',
    'password' => 'PASSWORD',
    'encryption' => null,
    'auth_mode' => null
];


/** HOMEPAGE **/
$app->get('/', function () use ($app) {
    $error = false;
    $success = false;

    if (isset($_SESSION['error'])) {
        $error = $_SESSION['error'];
        unset($_SESSION['error']);
    }
    
    if (isset($_SESSION['success'])) {
        $success = $_SESSION['success'];
        unset($_SESSION['success']);
    }
    

    return $app['twig']->render('home.twig', [
        'error'     => $error,
        'success'   => $success
    ]);
});

$app->post('/', function () use ($app, $db) {
    $server = $app['request']->get('server');
    $email = $app['request']->get('email');

    $request = $db->prepare("SELECT COUNT(*) as nbre FROM follows WHERE server = '$server' AND email = '$email'");
    $request->execute();
    $result = $request->fetch(PDO::FETCH_OBJ);

    if ($result->nbre > 0) {
        $_SESSION['error'] = 'Cet email est deja abonné au serveur '. $server;
        return $app->redirect('index.php');
    }

    $db->exec("INSERT INTO follows SET server = '$server', email = '$email'");
    $_SESSION['success'] = 'Vous êtes correctement abonné au serveur '. $server;
    return $app->redirect('index.php');
});


/** UPDATE **/
$app->get('/update', function () use ($app, $db) {
    $ovhServer = new OVHServerAvailability();
    $servers = ['KS1', 'KS2'];
    foreach ($servers as $reference) {
        $server = $ovhServer->getAvailability($reference);

        if ($server['available'] > 0) {
            $followersRequest = $db->prepare("
                SELECT follows.id, follows.email, follows.server, MAX(emails.date) as date 
                FROM follows 
                LEFT JOIN emails ON emails.follows_id = follows.id 
                WHERE server = '$reference'
            ");
            $followersRequest->execute();
            $followers = $followersRequest->fetchAll(PDO::FETCH_OBJ);

            $message = \Swift_Message::newInstance()
                ->setSubject('[OVH] Server '. $reference .' available')
                ->setFrom(array($app['swiftmailer.options']['email']))
                ->setBody($app['twig']->render('mail.twig', [
                    'server'    => $server,
                    'ovhServer' => $ovhServer
                ]), 'text/html');

            foreach ($followers as $follower) {
                $time = time();
                $difference = $time - intval($follower->date);
                if ($difference < 3600) continue;

                $db->exec("INSERT INTO emails SET follows_id = '{$follower->id}', date = '$time'");

                // Send email
                $contextMessage = $message;
                $contextMessage->setTo(array($follower->email));
                $app['mailer']->send($contextMessage);
            }
        }
    }

    return "";
});


$app->run();
