<?php
namespace Drupal\eu_login;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\YamlFileLoader;

class EuLoginServiceProvider extends ServiceProviderBase {
  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    parent::register($container);
    // Register application services.
    $yaml_loader = new YamlFileLoader($container);
    $path = DRUPAL_ROOT . '/../vendor/OpenEuropa/pcas/Resources/config/p_cas.yml';
    $yaml_loader->load($path);
  }

  public function alter(ContainerBuilder $container) {
    parent::alter($container);
    // Register application services.
    $yaml_loader = new YamlFileLoader($container);
    $path = __DIR__ . '/../pcas.services.yml';
    $yaml_loader->load($path);
  }

}
