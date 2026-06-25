<?php

declare(strict_types=1);

namespace App\Lib;

use HTMLPurifier;
use HTMLPurifier_Config;

class HtmlSanitizer
{
  private HTMLPurifier $purifier;

  /**
   * Method __construct
   *
   * @param ?string $cachePath [explicit description]
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
   * Method clean
   *
   * @param string|array|null $data [explicit description]
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
