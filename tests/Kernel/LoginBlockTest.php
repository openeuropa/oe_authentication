<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication\Kernel;

use Drupal\Core\Link;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;

/**
 * Test login block rendering.
 */
class LoginBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'oe_authentication',
    'system',
    'user',
    'cas',
    'externalauth',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system', 'block', 'user']);
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
  }

  /**
   * Test login block rendering.
   */
  public function testLoginBlockRendering(): void {

    // Setup and render login block.
    $block_manager = $this->container->get('plugin.manager.block');
    $renderer = $this->container->get('renderer');

    /** @var \Drupal\oe_authentication\Plugin\Block\LoginBlock $plugin_block */
    $plugin_block = $block_manager->createInstance('oe_authentication_login_block');
    $build = $plugin_block->build();
    $block = (string) $renderer->renderRoot($build);

    $link = Link::createFromRoute('Log in', 'user.login')->toRenderable();
    $expected_link = (string) $renderer->renderRoot($link);

    // Make sure the login link is present.
    $this->assertEquals($expected_link, $block);

    // Create a user to login.
    $user1 = User::create([
      'name' => 'oe_user1',
      'mail' => 'oe_user1@example.com',
    ]);

    $user1->activate();
    $user1->save();

    // Simulate a login of this user.
    $this->container->get('current_user')->setAccount($user1);

    // Render the block again.
    $build = $plugin_block->build();
    $block = (string) $renderer->renderRoot($build);
    $link = Link::createFromRoute('Log out', 'user.logout')->toRenderable();
    $expected_link = (string) $renderer->renderRoot($link);
    // Asserts if the text changed, and log out text is now present.
    $this->assertEquals($expected_link, $block);
  }

}
