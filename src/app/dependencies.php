<?php

declare(strict_types=1);

use App\Core\SecureDb;
use App\Lib\GusLib;
use App\Lib\HtmlSanitizer;
use App\Services\ServiceResolver;
use App\Settings\Setting;
use App\Settings\SettingInterface;
use App\Settings\TwigGlobals;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Csrf\Guard;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Views\Twig;
use SlimSession\Helper as SessionHelper;

return [
  /**
   * 설정 서비스를 생성한다.
   *
   * @return SettingInterface
   */
  SettingInterface::class => fn (): SettingInterface => new Setting(__DIR__ . '/../source/Settings/.env'),

  Setting::class => fn (ContainerInterface $container): SettingInterface => $container->get(SettingInterface::class),

  /**
   * 기존 배열 기반 설정 접근을 유지한다.
   *
   * @param ContainerInterface $container DI 컨테이너
   *
   * @return array<string, mixed>
   */
  'settings' => fn (ContainerInterface $container): array => $container->get(SettingInterface::class)->get(),
  /**
   * Create the Twig view service.
   *
   * @param ContainerInterface $container [explicit description]
   *
   * @return Twig
   */
  Twig::class => function (ContainerInterface $container): Twig {
    $settings = $container->get(SettingInterface::class)->get();
    $view = Twig::create($settings['paths']['templates'], [
      'cache' => false,
      'debug' => $settings['debug'],
    ]);
    $view->addExtension(new TwigGlobals($container));

    return $view;
  },
  'view' => fn (ContainerInterface $container): Twig => $container->get(Twig::class),

  /**
   * Create the PDO database connection.
   *
   * @param ContainerInterface $container [explicit description]
   *
   * @return PDO
   */
  PDO::class => function (ContainerInterface $container): PDO {
    $settings = $container->get(SettingInterface::class)->get('db');
    $dsn = sprintf(
      'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
      $settings['host'],
      $settings['port'],
      $settings['name']
    );

    return new PDO($dsn, $settings['user'], $settings['pass'], [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  },

  SecureDb::class => fn (ContainerInterface $container): SecureDb => new SecureDb($container->get(PDO::class)),
  'secureDb' => fn (ContainerInterface $container): SecureDb => $container->get(SecureDb::class),

  ServiceResolver::class => fn (ContainerInterface $container): ServiceResolver => new ServiceResolver($container),

  GusLib::class => fn (): GusLib => new GusLib(),
  'lib' => fn (ContainerInterface $container): GusLib => $container->get(GusLib::class),

  /**
   * Create the HTML sanitizer service.
   *
   * @param ContainerInterface $container [explicit description]
   *
   * @return HtmlSanitizer
   */
  HtmlSanitizer::class => function (ContainerInterface $container): HtmlSanitizer {
    $cachePath = rtrim((string) $container->get(SettingInterface::class)->get('root_path'), '/\\') . '/var/htmlpurifier';
    if (!is_dir($cachePath)) {
      mkdir($cachePath, 0775, true);
    }

    return new HtmlSanitizer($cachePath);
  },
  'sanitizer' => fn (ContainerInterface $container): HtmlSanitizer => $container->get(HtmlSanitizer::class),

  ResponseFactory::class => fn (): ResponseFactory => new ResponseFactory(),
  /**
   * Create the CSRF guard service.
   *
   * @param ContainerInterface $container [explicit description]
   *
   * @return Guard
   */
  Guard::class => function (ContainerInterface $container): Guard {
    if (!isset($_SESSION['csrf']) || !is_array($_SESSION['csrf'])) {
      $_SESSION['csrf'] = [];
    }
    $storage = &$_SESSION['csrf'];

    $guard = new Guard($container->get(ResponseFactory::class), 'csrf', $storage);
    $guard->setPersistentTokenMode(true);
    /**
     * Handle failed CSRF validation responses.
     *
     * @param Request $request [explicit description]
     * @param RequestHandler $handler [explicit description]
     *
     * @return mixed
     */
    $guard->setFailureHandler(function (Request $request, RequestHandler $handler) use ($container) {
      $response = $container->get(ResponseFactory::class)->createResponse(400);
      $accept = $request->getHeaderLine('Accept');
      // AJAX requests need a JSON error while normal form posts can use browser history fallback.
      $isAjax = str_contains($accept, 'application/json')
        || $request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';

      if ($isAjax) {
        $response->getBody()->write(json_encode([
          'success' => false,
          'message' => 'CSRF token validation failed. Please refresh and try again.',
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
      }

      $response->getBody()->write("<script>alert('Invalid request. Please refresh and try again.'); history.back();</script>");

      return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    });

    return $guard;
  },
  'csrf' => fn (ContainerInterface $container): Guard => $container->get(Guard::class),

  'session' => fn (): SessionHelper => new SessionHelper(),
];
