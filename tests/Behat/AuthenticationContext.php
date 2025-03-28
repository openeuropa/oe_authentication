<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication\Behat;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Defines step definitions specifically for testing the CAS options.
 */
class AuthenticationContext extends RawDrupalContext {


  /**
   * The config context.
   *
   * @var \Drupal\DrupalExtension\Context\ConfigContext
   */
  protected $configContext;

  /**
   * Gathers some other contexts.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   The before scenario scope.
   *
   * @BeforeScenario
   */
  public function gatherContexts(BeforeScenarioScope $scope) {
    $environment = $scope->getEnvironment();
    $this->configContext = $environment->getContext('Drupal\DrupalExtension\Context\ConfigContext');
  }

  /**
   * Configures the CAS module to use Drupal login.
   *
   * @BeforeScenario @DrupalLogin
   */
  public function setConfigDrupalLogin(): void {
    $this->configContext->setConfig('cas.settings', 'forced_login.enabled', FALSE);
  }

  /**
   * Configures the CAS module to use CAS login.
   *
   * Revert the CAS login setting. The ConfigContext does revert
   * this value, however it is cached and therefore it isn't available for
   * other scenarios following this tag.
   *
   * @AfterScenario @DrupalLogin
   */
  public function setConfigCasLogin(): void {
    $this->configContext->setConfig('cas.settings', 'forced_login.enabled', TRUE);
  }

  /**
   * Configures the CAS module to initialize this client as a proxy.
   *
   * @Given the site is configured to initialize this client as a proxy
   */
  public function setConfigProxyInitialize(): void {
    $this->configContext->setConfig('cas.settings', 'proxy.initialize', TRUE);
  }

  /**
   * Blocks a user given its username.
   *
   * @var string $username
   *   The name of the user to be blocked.
   *
   * @When the user :username is blocked
   *
   * @throws \Exception
   *   Thrown when the user with the given name does not exist.
   */
  public function blockUser(string $username): void {
    /** @var \Drupal\user\Entity\User $user */
    $user = user_load_by_name($username);

    if ($user) {
      $user->block();
      $user->save();
    }
  }

  /**
   * Backup configs that need to be reverted in AfterScenario by ConfigContext.
   *
   * @BeforeScenario @BackupAuthConfigs
   */
  public function backupCasConfigs(): void {
    $name = 'oe_authentication.settings';

    $configs = $this->getDriver()->getCore()->configGet($name);
    foreach ($configs as $key => $value) {
      $this->configContext->setConfig($name, $key, $value);
    }
  }

  /**
   * Navigates to the current user's page.
   *
   * @Given I visit my user page
   */
  public function visitOwnUserPage(): void {
    $current_user = $this->getUserManager()->getCurrentUser();
    /** @var \Drupal\user\Entity\User $user */
    $user = User::load($current_user->uid);
    /** @var \Drupal\Core\Url $url */
    $url = $user->toUrl();
    $this->visitPath($url->getInternalPath());
  }

  /**
   * Navigates to other user's page.
   *
   * @var string $username
   *   The name of the user whose page is to be visited.
   *
   * @Given I visit the :user_name user page
   *
   * @throws \Exception
   *   Thrown when the user with the given name does not exist.
   */
  public function visitUserPage(string $user_name): void {
    $user = user_load_by_name($user_name);
    if ($user === FALSE) {
      throw new \Exception(sprintf('User with name %s could not be found.', $user_name));
    }
    /** @var \Drupal\Core\Url $url */
    $url = $user->toUrl();
    $this->visitPath($url->getInternalPath());
  }

  /**
   * Configures the the Drupal site so that users are active on creation.
   *
   * @Given the site is configured to make users active on creation
   */
  public function setNewUsersActive(): void {
    $this->configContext->setConfig('user.settings', 'register', UserInterface::REGISTER_VISITORS);
  }

  /**
   * Configures the Drupal site so that users are blocked on creation.
   *
   * @Given the site is configured to make users blocked on creation
   */
  public function setNewUsersBlocked(): void {
    $this->configContext->setConfig('user.settings', 'register', UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL);
  }

  /**
   * Allow external users to login.
   *
   * @BeforeScenario @AllowExternalLogin
   */
  public function allowExternalUsers(): void {
    // Set the assurance level to allow also external users to login.
    $this->configContext->setConfig('oe_authentication.settings', 'assurance_level', 'LOW');
  }

  /**
   * Configures oe_authentication module to allow to use user delete options.
   *
   * @Given the site is configured to not restrict user delete options
   */
  public function setConfigEnableDeleteUsersOptions(): void {
    $this->configContext->setConfig('oe_authentication.settings', 'restrict_user_delete_cancel_methods', FALSE);
  }

  /**
   * Logs in as superuser user using default credentials.
   *
   * @Given I am logged in as the superuser user
   */
  public function loginSuperuser(): void {
    $user = (object) [
      'uid' => 1,
      'name' => 'admin',
      'pass' => 'admin',
    ];
    // Login.
    $this->login($user);
  }

}
