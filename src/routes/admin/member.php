<?php

declare(strict_types=1);

use App\Controllers\Admin\Member;
use Slim\Routing\RouteCollectorProxy as RouteGroup;

/**
 * 관리자 회원 도메인 라우트를 등록합니다.
 *
 * @param RouteGroup $group 라우트 그룹 객체
 *
 * @return void
 */
return function (RouteGroup $group): void {
  $group->get('/member/lists', Member\Lists\Page::class);
  $group->post('/member/lists/download', Member\Lists\Download\Post::class);
  $group->get('/member/register', Member\Register\Page::class);
  $group->post('/member/register', Member\Register\Post::class);
  $group->get('/member/detail/{id:[0-9]+}', Member\Detail\Page::class);
  $group->put('/member/detail/{id:[0-9]+}', Member\Detail\Put::class);
  $group->post('/member/detail/{id:[0-9]+}', Member\Detail\Put::class);
  $group->get('/member/{asset:deposit|point}/history', Member\AssetHistory\Page::class);
  $group->post('/member/{asset:deposit|point}/history/download', Member\AssetHistory\Download\Post::class);
  $group->get('/member/{asset:deposit|point}/{id:[0-9]+}', Member\Asset\Page::class);
  $group->post('/member/{asset:deposit|point}/{id:[0-9]+}/download', Member\Asset\Download\Post::class);
  $group->post('/member/{asset:deposit|point}/{id:[0-9]+}', Member\Asset\Post::class);
  $group->post('/member/memo/{id:[0-9]+}', Member\Memo\Post::class);
  $group->post('/member/memo/delete/{id:[0-9]+}', Member\Memo\Delete::class);
};
