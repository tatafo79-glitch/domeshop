<?php

declare(strict_types=1);

namespace App\Lib;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizer
{
  private HTMLPurifier $purifier;

  /**
   * HTMLPurifier 기반 입력 정제기를 초기화합니다.
   *
   * @param ?string $cachePath HTMLPurifier 캐시 저장 경로
   *
   * @return void
   */
  public function __construct(?string $cachePath = null)
  {
    // Keep the allow-list narrow because this sanitizer is applied to request input.
    $config = HTMLPurifier_Config::createDefault();
    $config->set('Cache.SerializerPath', $cachePath ?? sys_get_temp_dir());
    $config->set('HTML.Allowed', 'p,b,i,u,span[style],div[style],br,img[src|alt|width|height],a[href|target|title]');
    $config->set('CSS.AllowedProperties', 'font-weight,color,text-align,background-color,font-size,text-decoration');

    $this->purifier = new HTMLPurifier($config);
  }

  /**
   * 입력 데이터를 안전한 HTML 허용 목록 기준으로 정제합니다.
   *
   * @param string|array|null $data 정제할 입력 데이터
   *
   * @return string|array
   */
  public function clean(string|array|null $data): string|array
  {
    if ($data === null) {
      return '';
    }

    if (is_array($data)) {
      return array_map([$this, 'clean'], $data);
    }

    if ($data === '') {
      return '';
    }

    return $this->purifier->purify($data);
  }
}
