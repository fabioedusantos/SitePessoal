<?php

declare(strict_types=1);

use App\Bootstrap;
use DI\ContainerBuilder;

require dirname(__DIR__) . '/vendor/autoload.php';

$builder = new ContainerBuilder();
$container = $builder->build();

$app = Bootstrap::create($container);
$app->run();
