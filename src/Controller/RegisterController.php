<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Controller;

use Drupal\cas\Service\CasHelper;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Url;
use Drupal\Core\Utility\UnroutedUrlAssemblerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handles the registration route.
 */
class RegisterController extends ControllerBase {

  /**
   * The unrouted URL assembler service.
   *
   * @var \Drupal\Core\Utility\UnroutedUrlAssemblerInterface
   */
  protected $unroutedUrlAssembler;

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * CAS Helper object.
   *
   * @var \Drupal\cas\Service\CasHelper
   */
  protected $casHelper;

  /**
   * Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Redirects to eulogin pages.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The route match.
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   *   The CAS Helper service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   * @param \Drupal\Core\Utility\UnroutedUrlAssemblerInterface $url_assembler
   *   The unrouted URL assembler service.
   */
  public function __construct(Request $request, CasHelper $cas_helper, ConfigFactoryInterface $configFactory, UnroutedUrlAssemblerInterface $url_assembler) {
    $this->request = $request;
    $this->casHelper = $cas_helper;
    $this->configFactory = $configFactory;
    $this->unroutedUrlAssembler = $url_assembler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('cas.helper'),
      $container->get('config.factory'),
      $container->get('unrouted_url_assembler')
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

    $cache = new CacheableMetadata();
    $cache->addCacheContexts(['user.roles:anonymous']);
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
    $destination = $this->request->query->get('destination');
    if (!empty($destination)) {
      $destination_path = $this->getDestinationAsAbsoluteUrl($destination);
      // Remove destination parameter to avoid redirection.
      $this->request->query->remove('destination');
    }
    else {
      $front_url = Url::fromRoute('<front>');
      $front_url->setAbsolute();
      // We need to ensure we are collecting the cache metadata so it doesn't
      // bubble up to the render context or we get a Logic exception later
      // when we return a Cacheable response.
      $front_url = $front_url->toString(TRUE);
      $destination_path = $front_url->getGeneratedUrl();
    }

    return Url::fromUri($base_url . $path, ['query' => ['service' => $destination_path]]);
  }

  /**
   * Converts the passed in destination into an absolute URL.
   *
   * @param string $destination
   *   The path for the destination. In case it starts with a slash it should
   *   have the base path included already.
   *
   * @return string
   *   The destination as absolute URL.
   */
  protected function getDestinationAsAbsoluteUrl($destination) {
    if (!UrlHelper::isExternal($destination)) {
      // The destination query parameter can be a relative URL in the sense of
      // not including the scheme and host, but its path is expected to be
      // absolute (start with a '/'). For such a case, prepend the scheme and
      // host, because the 'Location' header must be absolute.
      if (strpos($destination, '/') === 0) {
        $destination = $this->request->getSchemeAndHttpHost() . $destination;
      }
      else {
        // Legacy destination query parameters can be internal paths that have
        // not yet been converted to URLs.
        $destination = UrlHelper::parse($destination);
        $uri = 'base:' . $destination['path'];
        $options = [
          'query' => $destination['query'],
          'fragment' => $destination['fragment'],
          'absolute' => TRUE,
        ];
        // Treat this as if it's user input of a path relative to the site's
        // base URL.
        $destination = $this->unroutedUrlAssembler->assemble($uri, $options);
      }
    }
    return $destination;
  }

}
