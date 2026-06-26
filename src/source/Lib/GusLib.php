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

  /**
   * 엑셀 수식으로 해석될 수 있는 값을 일반 텍스트로 이스케이프합니다.
   *
   * @param mixed $value CSV 셀 값
   *
   * @return string
   */
  public function escapeCsvValue(mixed $value): string
  {
    $text = (string) ($value ?? '');

    if ($text !== '' && preg_match('/^[=+\-@]/', $text) === 1) {
      return "'" . $text;
    }

    return $text;
  }

}
