<?php

declare(strict_types=1);

namespace App\Settings;

use Noodlehaus\Config;
use Noodlehaus\ConfigInterface;
use Noodlehaus\Parser\Json;

class Setting implements SettingInterface
{
  private ConfigInterface $settings;

  /**
   * JSON 설정 파일과 기본 설정을 병합한다.
   *
   * @param string $file JSON 설정 파일 경로
   *
   * @return void
   */
  public function __construct(string $file)
  {
    $fileConfig = Config::load($file, new Json());
    $config = new AppConfig([]);
    // JSON 파일 설정이 기본 설정보다 우선하도록 마지막에 병합한다.
    $config->merge($fileConfig);

    $this->settings = $config;
  }

  /**
   * 설정 값을 조회한다.
   *
   * @param string $key 점 표기법으로 조회할 설정 키
   *
   * @return mixed
   */
  public function get(string $key = ''): mixed
  {
    return $key === '' ? $this->settings->all() : $this->settings->get($key);
  }
}