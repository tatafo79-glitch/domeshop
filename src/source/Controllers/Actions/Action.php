<?php

declare(strict_types=1);

namespace App\Controllers\Actions;

use App\Lib\GusLib;
use App\Lib\HtmlSanitizer;
use App\Services\ServiceResolver;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpBadRequestException;
use Slim\Views\Twig;

abstract class Action
{
  protected Request $request;
  protected Response $response;
  protected array $args = [];
  protected mixed $service = null;
  protected GusLib $lib;
  protected HtmlSanitizer $sanitizer;

  /**
   * Method __construct
   *
   * @param ContainerInterface $container [explicit description]
   * @param Twig $view [explicit description]
   * @param ServiceResolver $serviceResolver [explicit description]
   * @param GusLib $lib [explicit description]
   * @param HtmlSanitizer $sanitizer [explicit description]
   *
   * @return void
   */
  public function __construct(
    protected readonly ContainerInterface $container,
    protected readonly Twig $view,
    ServiceResolver $serviceResolver,
    GusLib $lib,
    HtmlSanitizer $sanitizer
  ) {
    $this->lib = $lib;
    $this->sanitizer = $sanitizer;
    $this->service = $serviceResolver->resolve(static::class);
  }

  /**
   * Method __invoke
   *
   * @param Request $request [explicit description]
   * @param Response $response [explicit description]
   * @param array $args [explicit description]
   *
   * @return Response
   */
  public function __invoke(Request $request, Response $response, array $args): Response
  {
    $this->request = $request;
    $this->response = $response;
    $this->args = $args;

    return $this->action();
  }

  /**
   * Method action
   *
   * @return Response
   */
  abstract protected function action(): Response;

  /**
   * Method resolveArg
   *
   * @param string $name [explicit description]
   *
   * @return mixed
   */
  protected function resolveArg(string $name): mixed
  {
    if (!array_key_exists($name, $this->args)) {
      throw new HttpBadRequestException($this->request, "Missing route argument: {$name}");
    }

    return $this->args[$name];
  }

  /**
   * Method getQueryData
   *
   * @return array
   */
  protected function getQueryData(): array
  {
    return $this->sanitizer->clean($this->request->getQueryParams());
  }

  /**
   * Method getFormData
   *
   * @return array
   */
  protected function getFormData(): array
  {
    $data = $this->request->getParsedBody();

    return $this->sanitizer->clean(is_array($data) ? $data : []);
  }

  /**
   * Method getContentsData
   *
   * @return array
   */
  protected function getContentsData(): array
  {
    $contents = (string) $this->request->getBody();
    $data = json_decode($contents, true);

    return $this->sanitizer->clean(is_array($data) ? $data : []);
  }

  /**
   * Method render
   *
   * @param string $template [explicit description]
   * @param array $data [explicit description]
   *
   * @return Response
   */
  protected function render(string $template, array $data = []): Response
  {
    return $this->view->render($this->response, $template . '.html', $data);
  }

  /**
   * Method successResponse
   *
   * @param array $data [explicit description]
   * @param string $message [explicit description]
   * @param int $statusCode [explicit description]
   *
   * @return Response
   */
  protected function successResponse(array $data = [], string $message = 'OK', int $statusCode = 200): Response
  {
    return $this->respondWithData([
      'success' => true,
      'data' => $data,
      'message' => $message,
    ], $statusCode);
  }

  /**
   * Method errorResponse
   *
   * @param string $message [explicit description]
   * @param int $statusCode [explicit description]
   * @param array $extra [explicit description]
   *
   * @return Response
   */
  protected function errorResponse(string $message, int $statusCode = 400, array $extra = []): Response
  {
    return $this->respondWithData(array_merge([
      'success' => false,
      'message' => $message,
    ], $extra), $statusCode);
  }

  /**
   * Method respondWithData
   *
   * @param array $payload [explicit description]
   * @param int $statusCode [explicit description]
   *
   * @return Response
   */
  protected function respondWithData(array $payload, int $statusCode = 200): Response
  {
    $this->response->getBody()->write((string) json_encode($payload, JSON_UNESCAPED_UNICODE));

    return $this->response
      ->withHeader('Content-Type', 'application/json; charset=utf-8')
      ->withStatus($statusCode);
  }

  /**
   * Method redirect
   *
   * @param string $url [explicit description]
   * @param int $statusCode [explicit description]
   *
   * @return Response
   */
  protected function redirect(string $url, int $statusCode = 302): Response
  {
    return $this->response
      ->withHeader('Location', $url)
      ->withStatus($statusCode);
  }

}
