<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Link;

/**
 * Provides a 'LoginBlock' block.
 *
 * @Block(
 *  id = "oe_authentication_login_block",
 *  admin_label = @Translation("EU Login block"),
 * )
 */
class LoginBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Drupal\Core\Session\AccountProxy definition.
   *
   * @var \Drupal\Core\Session\AccountProxy
   */
  protected $currentUser;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AccountProxy $current_user) {
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
  public function build() {

    if ($this->currentUser->isAnonymous()) {
      $link = Link::fromTextAndUrl('Log in', Url::fromRoute('user.login'));
    }
    else {
      $link = Link::fromTextAndUrl('Log out', Url::fromRoute('user.logout'));
    }

    $build = [];
    $build['login_block'] = [
      '#theme' => 'login_block',
      '#link' => $link,
    ];

    return $build;
  }

}
