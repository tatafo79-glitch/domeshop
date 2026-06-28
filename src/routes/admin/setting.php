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
  $group->get('/setting/goods/prohibited-word', Setting\Goods\ProhibitedWord\Page::class);
  $group->post('/setting/goods/prohibited-word', Setting\Goods\ProhibitedWord\Post::class);
  $group->post('/setting/goods/prohibited-word/{id:[0-9]+}', Setting\Goods\ProhibitedWord\Put::class);
  $group->post('/setting/goods/prohibited-word/{id:[0-9]+}/delete', Setting\Goods\ProhibitedWord\Delete\Post::class);
  $group->get('/setting/goods/origin', Setting\Goods\Origin\Page::class);
  $group->post('/setting/goods/origin/download', Setting\Goods\Origin\Download\Post::class);
  $group->post('/setting/goods/origin/template-download', Setting\Goods\Origin\TemplateDownload\Post::class);
  $group->post('/setting/goods/origin/upload', Setting\Goods\Origin\Upload\Post::class);
  $group->post('/setting/goods/origin', Setting\Goods\Origin\Post::class);
  $group->post('/setting/goods/origin/{id:[0-9]+}', Setting\Goods\Origin\Put::class);
  $group->post('/setting/goods/origin/{id:[0-9]+}/delete', Setting\Goods\Origin\Delete\Post::class);
};
