<?php

declare(strict_types=1);

use App\Core\SecureDb;
use App\Middlewares\DebuggingMiddleware;
use App\Middlewares\HttpOriginMiddleware;
use App\Settings\SettingInterface;
use Middlewares\TrailingSlash;
use Psr\Container\ContainerInterface;
use Selective\BasePath\BasePathMiddleware;
use Slim\App;
use Slim\Middleware\Session;
use Slim\Views\Twig;
use Slim\Views\TwigMiddleware;
use Tuupola\Middleware\CorsMiddleware;
use Whoops\Util\Misc;
use Zeuxisoo\Whoops\Slim\WhoopsMiddleware;

/**
 * Register application middleware.
 *
 * @param App $app [explicit description]
 * @param ContainerInterface $container [explicit description]
 *
 * @return void
 */
return function (App $app, ContainerInterface $container): void {
  $settings = $container->get(SettingInterface::class)->get();
  $securitySettings = $settings['security'] ?? [];
  $isDebug = (bool) ($settings['debug'] ?? false);

  $allowedOrigins = $securitySettings['origin_allow_hosts'] ?? [];
  if ($allowedOrigins === []) {
    $allowedOrigins = ['*.okdome.com', '*localhost*', '*127.0.0.1*', '*192.168.*'];
  }

  $sessionOptions = [
    'name' => $_ENV['SESSION_NAME'] ?? 'DMS',
    'autorefresh' => true,
    'lifetime' => $_ENV['SESSION_LIFETIME'] ?? '6 hour',
    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
    'httponly' => true,
    'samesite' => 'Lax',
  ];
  $sessionPath = trim((string) ($_ENV['SESSION_SAVE_PATH'] ?? ''));
  if ($sessionPath !== '') {
    if (!is_dir($sessionPath)) {
      mkdir($sessionPath, 0775, true);
    }
    // 명시된 환경변수가 있을 때만 PHP 기본 세션 저장 경로를 덮어씁니다.
    $sessionOptions['ini_settings'] = [
      'session.save_path' => $sessionPath,
    ];
  }

  $app->addBodyParsingMiddleware();
  $app->add(new BasePathMiddleware($app));
  $app->add(TwigMiddleware::create($app, $container->get(Twig::class)));
  $app->add(new Session($sessionOptions));
  $app->add(new TrailingSlash(false));
  $app->add(new HttpOriginMiddleware($container));
  $app->add(new DebuggingMiddleware($container));

  $app->addRoutingMiddleware();
  $app->add(new CorsMiddleware([
    'origin' => $allowedOrigins,
    'methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
    'headers.allow' => ['X-Requested-With', 'Content-Type', 'Accept', 'Origin', 'Authorization', 'X-CSRF-NAME', 'X-CSRF-VALUE'],
    'headers.expose' => ['Content-Disposition'],
    'credentials' => true,
    'cache' => 0,
  ]));

  $app->add(new WhoopsMiddleware([
    'enable' => true,
    'title' => 'Domemall Admin Error',
  ], [
    /**
     * Render and persist uncaught exception details.
     *
     * @param mixed $exception [explicit description]
     * @param mixed $inspector [explicit description]
     *
     * @return void
     */
    function ($exception, $inspector) use ($container, $settings, $isDebug): void {
      $code = (int) $exception->getCode();
      $statusCode = $code >= 400 && $code < 600 ? $code : 500;
      $exceptionName = $inspector->getExceptionName();
      $exceptionMessage = $inspector->getExceptionMessage();
      $publicMessage = match ($statusCode) {
        400, 401, 403 => $exceptionMessage,
        404 => 'Not found.',
        405 => 'not allowed.',
        default => $isDebug ? $exceptionMessage : 'An internal server error occurred.',
      };
      // Keep the public response small while retaining the first stack frame for diagnostics.
      $frame = $inspector->getFrames()[0] ?? null;
      $file = $frame ? (string) $frame->getFile() : '';
      $line = $frame ? (int) $frame->getLine() : 0;
      $publicPath = $settings['paths']['public'] ?? dirname(__DIR__, 2) . '/public_html';

      try {
        $logFile = rtrim((string) $publicPath, '/\\') . '/error_debug.log';
        $logMsg = sprintf(
          "[%s] [Code: %d] %s: %s in %s:%d\n%s\n\n",
          date('Y-m-d H:i:s'),
          $statusCode,
          $exceptionName,
          $exceptionMessage,
          $file,
          $line,
          $exception->getTraceAsString()
        );
        file_put_contents($logFile, $logMsg, FILE_APPEND | LOCK_EX);
        @chmod($logFile, 0666);
      } catch (Throwable) {
        // Ignore logging failures.
      }

      try {
        // Persist a compact database error log so admin screens can inspect failures later.
        $db = $container->get(SecureDb::class);
        $cookieNames = array_filter(array_map(
          static function (string $cookie): string {
            $parts = explode('=', trim($cookie), 2);

            return trim($parts[0] ?? '');
          },
          explode(';', (string) ($_SERVER['HTTP_COOKIE'] ?? ''))
        ));
        // 쿠키 값은 세션/CSRF 토큰을 포함할 수 있으므로 이름만 남기고 값은 저장하지 않는다.
        $maskedCookie = implode('; ', array_map(
          static fn (string $cookieName): string => $cookieName . '=[masked]',
          $cookieNames
        ));
        $db->execute(
          'INSERT INTO error_logs (name, method, file, line, request_uri, query_string, remote_ip, cookie, message, reg_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
          [
            substr($exceptionName, 0, 255),
            substr($_SERVER['REQUEST_METHOD'] ?? '', 0, 10),
            substr($file, 0, 255),
            (string) $line,
            substr($_SERVER['REQUEST_URI'] ?? '', 0, 255),
            substr($_SERVER['QUERY_STRING'] ?? '', 0, 255),
            substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45),
            substr($maskedCookie, 0, 500),
            substr($exceptionMessage, 0, 1000),
          ]
        );
      } catch (Throwable) {
        // Ignore DB logging failures.
      }

      http_response_code($statusCode);

      if (Misc::isAjaxRequest()) {
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Credentials: true');
        echo json_encode([
          'success' => false,
          'statusCode' => $statusCode,
          'message' => $publicMessage,
        ], JSON_UNESCAPED_UNICODE);
        exit;
      }

      header('Content-Type: text/plain; charset=UTF-8');
      echo "Error ({$statusCode}) -> {$publicMessage}";
      exit;
    },
  ]));
};
