<?php

declare(strict_types=1);

use App\Controllers\Common;
use Slim\Routing\RouteCollectorProxy as RouteGroup;

return function (RouteGroup $group): void {
  $group->post('/upload/image', Common\Upload\Image\Post::class);
};