<?php

declare(strict_types=1);

use App\Controllers\Admin\Goods;
use Slim\Routing\RouteCollectorProxy as RouteGroup;

/**
 * 관리자 상품 도메인 라우트를 등록합니다.
 *
 * @param RouteGroup $group 라우트 그룹 객체
 *
 * @return void
 */
return function (RouteGroup $group): void {
  $group->get('/goods/register', Goods\Register\Page::class);
  $group->post('/goods/register', Goods\Register\Post::class);
};

