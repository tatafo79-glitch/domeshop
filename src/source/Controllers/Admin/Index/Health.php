<?php

declare(strict_types=1);

namespace App\Controllers\Admin\Index;

use App\Controllers\Actions\Admin;
use Psr\Http\Message\ResponseInterface as Response;

class Health extends Admin
{
  /**
   * Method action
   *
   * @return Response
   */
  protected function action(): Response
  {
    return $this->successResponse([
      'app' => 'domemall',
      'status' => 'ok',
    ]);
  }
}
