<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Auth;

use App\Controllers\Actions\Admin;
use App\Settings\SettingInterface;
use Psr\Http\Message\ResponseInterface as Response;

class Logout extends Admin
{
  /**
   * 관리자 세션을 종료한다.
   *
   * @return Response
   */
  protected function action(): Response
  {
    $this->container->get('session')->delete('admin');
    $adminDir = trim((string) ($this->container->get(SettingInterface::class)->get('site.admin_directory') ?? 'dmmt'), '/');

    return $this->redirect('/' . $adminDir . '/login');
  }
}