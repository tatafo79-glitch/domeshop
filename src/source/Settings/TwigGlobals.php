<?php

declare(strict_types=1);

namespace App\Settings;

use Psr\Container\ContainerInterface;
use Slim\Csrf\Guard;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;
use Twig\TwigFunction;

class TwigGlobals extends AbstractExtension implements GlobalsInterface
{
  /**
   * Method __construct
   *
   * @param ContainerInterface $container [explicit description]
   *
   * @return void
   */
  public function __construct(private readonly ContainerInterface $container)
  {
  }

  /**
   * Method getGlobals
   *
   * @return array
   */
  public function getGlobals(): array
  {
    $settings = $this->container->get(SettingInterface::class)->get();
    $adminDirectory = $settings['site']['admin_directory'] ?? 'dmmt';

    return [
      'setting' => [
        'app_name' => $settings['app_name'] ?? 'Domemall',
        'site' => $settings['site'] ?? ['admin_directory' => $adminDirectory],
        'dir' => ['admin' => $adminDirectory],
        'skin' => $settings['skin'] ?? ['name' => 'basic'],
        'cdn_info' => $settings['cdn_info'] ?? [],
      ],
      'csrf' => $this->getCsrfGlobals(),
      'session' => $this->container->has('session') ? $this->container->get('session') : [],
    ];
  }

  /**
   * Method getCsrfGlobals
   *
   * @return array
   */
  private function getCsrfGlobals(): array
  {
    $csrf = [
      'keys' => [
        'name' => 'csrf_name',
        'value' => 'csrf_value',
      ],
      'name' => '',
      'value' => '',
    ];

    if (!$this->container->has(Guard::class)) {
      return $csrf;
    }

    try {
      // Generate a token lazily so templates can always render CSRF hidden fields.
      $guard = $this->container->get(Guard::class);
      if ($guard->getTokenName() === null || $guard->getTokenValue() === null) {
        $guard->generateToken();
      }

      return [
        'keys' => [
          'name' => $guard->getTokenNameKey(),
          'value' => $guard->getTokenValueKey(),
        ],
        'name' => $guard->getTokenName() ?? '',
        'value' => $guard->getTokenValue() ?? '',
      ];
    } catch (\Throwable) {
      return $csrf;
    }
  }

  /**
   * Method getFunctions
   *
   * @return array
   */
  public function getFunctions(): array
  {
    return [
      new TwigFunction('is_checked', [$this, 'isChecked']),
      new TwigFunction('is_selected', [$this, 'isSelected']),
      new TwigFunction('is_hidden', [$this, 'isHidden']),
      new TwigFunction('is_disabled', [$this, 'isDisabled']),
      new TwigFunction('set_init', [$this, 'setInit']),
      new TwigFunction('dump', [$this, 'dump']),
      new TwigFunction('has_permission', [$this, 'hasPermission']),
    ];
  }

  /**
   * Method isChecked
   *
   * @param string|array|null $value [explicit description]
   * @param string $target [explicit description]
   * @param ?bool $default [explicit description]
   *
   * @return string
   */
  public function isChecked(string|array|null $value, string $target, ?bool $default = null): string
  {
    if (is_array($value)) {
      return in_array($target, $value, true) ? 'checked' : '';
    }

    return $this->matches('checked', $value, $target, $default);
  }

  /**
   * Method isSelected
   *
   * @param ?string $value [explicit description]
   * @param string $target [explicit description]
   * @param ?bool $default [explicit description]
   *
   * @return string
   */
  public function isSelected(?string $value, string $target, ?bool $default = null): string
  {
    return $this->matches('selected', $value, $target, $default);
  }

  /**
   * Method isHidden
   *
   * @param ?string $value [explicit description]
   * @param string $target [explicit description]
   * @param ?bool $default [explicit description]
   *
   * @return string
   */
  public function isHidden(?string $value, string $target, ?bool $default = null): string
  {
    return $this->matches('hidden', $value, $target, $default);
  }

  /**
   * Method isDisabled
   *
   * @param ?string $value [explicit description]
   * @param string $target [explicit description]
   * @param ?bool $default [explicit description]
   *
   * @return string
   */
  public function isDisabled(?string $value, string $target, ?bool $default = null): string
  {
    return $this->matches('disabled', $value, $target, $default);
  }

  /**
   * Method setInit
   *
   * @param ?string $value [explicit description]
   * @param string $default [explicit description]
   *
   * @return string
   */
  public function setInit(?string $value, string $default): string
  {
    return ($value === null || $value === '') ? $default : $value;
  }

  /**
   * Method dump
   *
   * @param mixed $data [explicit description]
   *
   * @return string
   */
  public function dump(mixed $data): string
  {
    return print_r($data, true);
  }

  /**
   * Method hasPermission
   *
   * @param string $permission [explicit description]
   *
   * @return bool
   */
  public function hasPermission(string $permission): bool
  {
    return false;
  }

  /**
   * Method matches
   *
   * @param string $attribute [explicit description]
   * @param mixed $value [explicit description]
   * @param string $target [explicit description]
   * @param ?bool $default [explicit description]
   *
   * @return string
   */
  private function matches(string $attribute, mixed $value, string $target, ?bool $default): string
  {
    if ((string) $value === $target) {
      return $attribute;
    }

    if (($value === null || $value === '') && $default === true) {
      return $attribute;
    }

    return '';
  }
}
