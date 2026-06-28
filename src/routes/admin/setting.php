<?php

declare(strict_types=1);

use App\Controllers\Admin\Setting;
use Slim\Routing\RouteCollectorProxy as RouteGroup;

/**
 * 관리자 환경설정 도메인 라우트를 등록합니다.
 *
 * @param RouteGroup $group 라우트 그룹 객체
 *
 * @return void
 */
return function (RouteGroup $group): void {
  $group->get('/setting/goods/register', Setting\Goods\Register\Page::class);
  $group->post('/setting/goods/register', Setting\Goods\Register\Post::class);
  $group->get('/setting/goods/platform-fee', Setting\Goods\PlatformFee\Page::class);
  $group->post('/setting/goods/platform-fee', Setting\Goods\PlatformFee\Post::class);
  $group->post('/setting/goods/platform-fee/{id:[0-9]+}', Setting\Goods\PlatformFee\Put::class);
  $group->post('/setting/goods/platform-fee/{id:[0-9]+}/delete', Setting\Goods\PlatformFee\Delete\Post::class);
};
