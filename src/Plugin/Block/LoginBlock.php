<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block that renders links to login or logout.
 *
 * @Block(
 *  id = "oe_authentication_login_block",
 *  admin_label = @Translation("EU Login Link Block"),
 * )
 */
class LoginBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if ($this->currentUser->isAnonymous()) {
      return [
        '#type' => 'link',
        '#title' => $this->t('Log in'),
        '#url' => Url::fromRoute('user.login'),
      ];
    }

    return [
      '#type' => 'link',
      '#title' => $this->t('Log out'),
      '#url' => Url::fromRoute('user.logout'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user.roles:anonymous']);
  }

}
