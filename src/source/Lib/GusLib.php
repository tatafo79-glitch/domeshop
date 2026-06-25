<?php

declare(strict_types=1);

namespace App\Lib;

class GusLib
{
  /**
   * Method onlyDigits
   *
   * @param string $value [explicit description]
   *
   * @return string
   */
  public function onlyDigits(string $value): string
  {
    return preg_replace('/\D+/', '', $value) ?? '';
  }
}
