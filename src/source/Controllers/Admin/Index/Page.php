<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Index;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Page extends Admin
{
  /**
   * Method action
   *
   * @return Response
   */
  protected function action(): Response
  {
    $result = $this->service->execute();

    return $this->render('main/index', $result);
  }
}
