<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Controller;

use Drupal\cas\Service\CasHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handles the registration route.
 */
class RegisterController extends ControllerBase {

  /**
   * CAS Helper object.
   *
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * Constructs the controller object.
   *
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   *   The CAS Helper service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(CasHelper $cas_helper, ConfigFactoryInterface $configFactory) {
    $this->casHelper = $cas_helper;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('cas.helper'),
      $container->get('config.factory')
    );
  }

  /**
   * Redirects a user to the CAS path for registering.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   The response object.
   */
  public function register(): TrustedRedirectResponse {
    if ($this->currentUser()->isAuthenticated()) {
      throw new AccessDeniedHttpException();
    }

    $url = $this->getRegisterUrl()->toString();
    $response = new TrustedRedirectResponse($url);

    $cache = (new CacheableMetadata())
      ->addCacheContexts(['user.roles:anonymous'])
      ->setCacheTags(['config:oe_authentication.settings']);
    $response->addCacheableDependency($cache);

    return $response;
  }

  /**
   * Get the register URL.
   *
   * @return \Drupal\Core\Url
   *   The register URL.
   */
  public function getRegisterUrl(): Url {
    $config = $this->configFactory->get('oe_authentication.settings');
    $base_url = $this->casHelper->getServerBaseUrl();
    $path = $config->get('register_path');
    $service = Url::fromRoute('<front>');
    $service->setAbsolute();

    // We need to ensure we are collecting the cache metadata so it doesn't
    // bubble up to the render context or we get a Logic exception later
    // when we return a Cacheable response.
    $service = $service->toString(TRUE);

    return Url::fromUri($base_url . $path, ['query' => ['service' => $service->getGeneratedUrl()]]);
  }

}
