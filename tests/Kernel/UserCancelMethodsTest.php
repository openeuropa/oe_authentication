<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;

/**
 * Tests the restriction of the user cancel methods.
 *
 * @group oe_authentication
 */
class UserCancelMethodsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'cas',
    'externalauth',
    'oe_authentication',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected bool $usesSuperUserAccessPolicy = FALSE;

  /**
   * A random user account test users try to delete.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $other;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'user', 'oe_authentication']);

    $this->other = $this->createUser();
  }

  /**
   * Tests what delete methods are available.
   */
  public function testUserAdministratorDeleteMethods(): void {
    $account = $this->setUpCurrentUser(permissions: ['administer users']);

    $this->renderCancelForm($account);
    $this->assertNonDeleteMethodsAvailable();
    $this->assertNoText('Delete the account');

    $this->renderCancelForm($this->other);
    $this->assertNonDeleteMethodsAvailable();
    $this->assertNoText('Delete the account');

    // When the setting is false the methods are available.
    $this->config('oe_authentication.settings')
      ->set('restrict_user_delete_cancel_methods', FALSE)
      ->save();

    $this->renderCancelForm($account);
    $this->assertNonDeleteMethodsAvailable();
    $this->assertDeleteMethodsAvailable();

    $this->renderCancelForm($this->other);
    $this->assertNonDeleteMethodsAvailable();
    $this->assertDeleteMethodsAvailable();

    // Check again with the config turned on but with a more privileged user.
    $this->config('oe_authentication.settings')
      ->set('restrict_user_delete_cancel_methods', TRUE)
      ->save();

    $account = $this->setUpCurrentUser(permissions: [
      'administer users',
      'bypass user deletion methods restriction',
    ]);

    $this->renderCancelForm($account);
    $this->assertNonDeleteMethodsAvailable();
    $this->assertDeleteMethodsAvailable();

    $this->renderCancelForm($this->other);
    $this->assertNonDeleteMethodsAvailable();
    $this->assertDeleteMethodsAvailable();
  }

  /**
   * Renders the cancel form of a user and sets it as the raw content.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user to render the cancel form for.
   */
  protected function renderCancelForm(UserInterface $account): void {
    $form = $this->container->get('entity.form_builder')->getForm($account, 'cancel');
    $this->render($form);
  }

  /**
   * Asserts that the cancel methods that only disable the account are shown.
   */
  protected function assertNonDeleteMethodsAvailable(): void {
    $this->assertText('Disable the account and keep its content.');
    $this->assertText('Disable the account and unpublish its content.');
  }

  /**
   * Asserts that the cancel methods that delete the account are shown.
   */
  protected function assertDeleteMethodsAvailable(): void {
    $this->assertText('Delete the account and its content.');
    $this->assertText('Delete the account and make its content belong to the Anonymous user.');
  }

}
