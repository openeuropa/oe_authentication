<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides a 'LoginBlock' block.
 *
 * @Block(
 *  id = "login_block",
 *  admin_label = @Translation("EU Login block"),
 * )
 */
class LoginBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $current = \Drupal::currentUser();
    $build = [];
    $build['login_block'] = [
      '#theme' => 'login_block',
      '#logged_out' => $current->isAnonymous(),
    ];

    return $build;
  }

}
