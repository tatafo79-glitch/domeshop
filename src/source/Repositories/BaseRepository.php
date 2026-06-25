<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\SecureDb;

abstract class BaseRepository
{
  /**
   * Method __construct
   *
   * @param SecureDb $db [explicit description]
   *
   * @return void
   */
  public function __construct(protected readonly SecureDb $db)
  {
  }
}
