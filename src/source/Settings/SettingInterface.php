<?php

declare(strict_types=1);

namespace App\Settings;

interface SettingInterface
{
  /**
   * 설정 값을 조회한다.
   *
   * @param string $key 점 표기법으로 조회할 설정 키
   *
   * @return mixed
   */
  public function get(string $key = ''): mixed;
}