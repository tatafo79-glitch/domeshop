<?php

declare(strict_types=1);

use App\Controllers\Admin;
use App\Middlewares\AdminSessionMiddleware;
use App\Settings\SettingInterface;
use Slim\App;
use Slim\Routing\RouteCollectorProxy as RouteGroup;

/**
 * Configure application routes.
 *
 * @param App $app [explicit description]
 *
 * @return void
 */
return function (App $app): void {
  $settings = $app->getContainer()->get(SettingInterface::class)->get();
  $adminDir = $settings['site']['admin_directory'] ?? 'dmmt';

  $app->get('/health', Admin\Index\Health::class);

  /**
   * Register admin child routes.
   *
   * @param RouteGroup $group [explicit description]
   *
   * @return void
   */
  $app->group('/' . $adminDir, function (RouteGroup $group): void {
    $group->get('/login', Admin\Auth\Page::class);
    $group->post('/login', Admin\Auth\Post::class);
    $group->post('/logout', Admin\Auth\Logout::class);
    $group->get('', Admin\Index\Page::class);
    (require __DIR__ . '/admin/member.php')($group);
    (require __DIR__ . '/admin/goods.php')($group);
    (require __DIR__ . '/admin/order.php')($group);
    (require __DIR__ . '/common/upload.php')($group);
  })
    ->add('csrf')
    ->add(new AdminSessionMiddleware($app->getContainer()));
};
