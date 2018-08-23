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
  ];

  /**
   * The block being tested.
   *
   * @var \Drupal\block\Entity\BlockInterface
   */
  protected $block;

  /**
   * The block storage.
   *
   * @var \Drupal\Core\Config\Entity\ConfigEntityStorageInterface
   */
  protected $controller;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig(['system', 'block', 'user']);
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('user');
  }

  /**
   * Test login block rendering.
   */
  public function testLoginBlockRendering(): void {

    // Setup and render login block.
    $block_manager = \Drupal::service('plugin.manager.block');

    $config = [
      'id' => 'oe_authentication_login_block',
      'label' => 'Login block',
      'provider' => 'oe_authentication',
      'label_display' => '0',
    ];

    /** @var \Drupal\Core\Block\BlockBase $plugin_block */
    $plugin_block = $block_manager->createInstance('oe_authentication_login_block', $config);
    $render = $plugin_block->build();
    $html = (string) $this->container->get('renderer')->renderRoot($render);

    $crawler = new Crawler($html);

    // Make sure that login block is present.
    $actual = $crawler->filter('.user-login-block');
    $this->assertCount(1, $actual);

    // Make sure the login link is present.
    $link = $crawler->filter('.user-login-block a');
    $this->assertEquals(t('Log in'), $link->text());
    $this->assertEquals(Url::fromRoute('user.login')->toString(), $link->attr('href'));

    // Create a user to login.
    $user1 = User::create([
      'name' => 'oe_user1',
      'mail' => 'oe_user1@example.com',
    ]);

    $user1->activate();
    $user1->save();

    // Logs in the user we just created.
    \Drupal::currentUser()->setAccount($user1);

    // Render the block again.
    $render = $plugin_block->build();
    $html = (string) $this->container->get('renderer')->renderRoot($render);

    // Asserts if the text changed, and log out text is now present.
    $crawler = new Crawler($html);
    $link = $crawler->filter('.user-login-block a');
    $this->assertEquals(t('Log out'), $link->text());
    $this->assertEquals(Url::fromRoute('user.logout')->toString(), $link->attr('href'));
  }

}
