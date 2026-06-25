<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../../vendor/autoload.php';

$rootPath = dirname(__DIR__, 2);

if (file_exists($rootPath . '/.env')) {
  Dotenv::createImmutable($rootPath)->safeLoad();
}

$containerBuilder = new ContainerBuilder();
$containerBuilder->addDefinitions(require __DIR__ . '/dependencies.php');

$container = $containerBuilder->build();
AppFactory::setContainer($container);

$app = AppFactory::create();

(require __DIR__ . '/../routes/route.php')($app);
(require __DIR__ . '/middleware.php')($app, $container);

return $app;
