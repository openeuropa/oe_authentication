<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authentication\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Test login block rendering.
 */
class LoginBlockTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
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

    $crawler = new Crawler($block);

    // Make sure the login link is present.
    $link = $crawler->filter('a');
    $this->assertEquals(t('Log in'), $link->text());
    $this->assertEquals(Url::fromRoute('user.login')->toString(), $link->attr('href'));

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

    // Asserts if the text changed, and log out text is now present.
    $crawler = new Crawler($block);
    $link = $crawler->filter('a');
    $this->assertEquals(t('Log out'), $link->text());
    $this->assertEquals(Url::fromRoute('user.logout')->toString(), $link->attr('href'));
  }

}
