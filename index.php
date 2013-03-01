<?php

require 'vendor/autoload.php';

$app = new \Slim\Slim();
Twig_Autoloader::register();
\Slim\Extras\Views\Twig::$twigOptions = array(
    'charset' => 'utf-8',
    'auto_reload' => true,
    'strict_variables' => false,
    'autoescape' => true
);

session_cache_limiter(false);
session_start();

\Slim\Extras\Views\Twig::$twigDirectory = 'Twig';
$app->view(new \Slim\Extras\Views\Twig());

require 'app/config.php';
$app->config($config);

$app->hook('slim.before', function () use ($app) {
    $user = null;
    if (isset($_SESSION['user'])) {
       $user = $_SESSION['user'];
    }
    $app->view()->setData('user', $user);
    $app->view()->appendData(array('baseUrl' => $app->config('base.url'),
        'siteTitle' => $app->config('site.title'),
        'disqusUser' => $app->config('disqus.username') ));
        });

require 'app/Blog.php';
require 'app/routes/main.php';

$app->run();
