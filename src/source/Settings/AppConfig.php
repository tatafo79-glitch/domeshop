<?php

declare(strict_types=1);

namespace App\Settings;

use Noodlehaus\AbstractConfig;

class AppConfig extends AbstractConfig
{
  /**
   * 기본 애플리케이션 설정을 반환한다.
   *
   * @return array<string, mixed>
   */
  protected function getDefaults(): array
  {
    $rootPath = dirname(__DIR__, 3);

    return [
      'debug' => ($_ENV['APP_DEBUG'] ?? 'false') === 'true',
      'root_path' => $rootPath,
      'paths' => [
        'templates' => $rootPath . '/src/templates/basic/admin',
        'public' => $rootPath . '/public_html',
      ],

      'db' => [
        'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
        'port' => $_ENV['DB_PORT'] ?? '3306',
        'name' => $_ENV['DB_NAME'] ?? '',
        'user' => $_ENV['DB_USER'] ?? '',
        'pass' => $_ENV['DB_PASS'] ?? '',
      ],
      'security' => [
        'origin_allow_hosts' => array_filter(array_map('trim', explode(',', $_ENV['ORIGIN_ALLOW_HOSTS'] ?? ''))),
        'debugging_allow_ip' => array_filter(array_map('trim', explode(',', $_ENV['DEBUGGING_ALLOW_IP'] ?? ''))),
      ],
      'app_name' => 'Domemall',
      'skin' => [
        'name' => 'basic',
      ],
      'cdn_info' => [],
    ];
  }
}