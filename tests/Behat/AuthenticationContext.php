<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authentication\Behat;

use Drupal\DrupalExtension\Context\ConfigContext;

/**
 * Defines step definitions specifically for testing the CAS options.
 *
 * We are extending ConfigContext to override the setConfig() method until
 * issue https://github.com/jhedstrom/drupalextension/issues/498 is fixed.
 *
 * @todo Extend DrupalRawContext and gather the config context when the above
 * issue is fixed.
 */
class AuthenticationContext extends ConfigContext {

  /**
   * Configures the CAS module to use Drupal login.
   *
   * @BeforeScenario @DrupalLogin
   */
  public function setConfigDrupalLogin(): void {
    $this->setConfig('cas.settings', 'forced_login.enabled', FALSE);
  }

  /**
   * Configures the CAS module to use CAS login.
   *
   * @AfterScenario @DrupalLogin
   */
  public function setConfigCasLogin(): void {
    $this->setConfig('cas.settings', 'forced_login.enabled', TRUE);
  }

  /**
   * Configures the CAS module to initialize this client as a proxy.
   *
   * @Given the site is configured to initialize this client as a proxy
   */
  public function setConfigProxyInitialize(): void {
    $this->setConfig('cas.settings', 'proxy.initialize', TRUE);
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
    $users = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties([
        'name' => $username,
      ]);
    /** @var \Drupal\user\Entity\User $user */
    $user = $users ? reset($users) : FALSE;
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
    foreach ($configs as $key => $backup) {
      $this->config[$name][$key] = $backup;
    }
  }

}
