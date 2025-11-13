<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication\Behat;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\Tests\cas_mock_server\Context\CasMockServerContext;

/**
 * Wrapper for CasMockServerContext to avoid constructor injection issues.
 *
 * This class extends RawDrupalContext instead of CasMockServerContext to ensure
 * proper initialization order and access to Drupal services.
 */
class WrappedCasMockServerContext extends RawDrupalContext {

  /**
   * The wrapped CAS mock server context.
   *
   * @var \Drupal\Tests\cas_mock_server\Context\CasMockServerContext
   */
  protected $wrappedContext;

  /**
   * Initializes the wrapped context after Drupal bootstrap.
   */
  protected function getWrappedContext(): CasMockServerContext {
    if (!$this->wrappedContext) {
      $attributes_map = [
        'email' => 'E-mail',
        'firstName' => 'First name',
        'lastName' => 'Last name',
        'departmentNumber' => 'Department',
        'domain' => 'Organisation',
        'groups' => 'Groups',
      ];

      $server_manager = \Drupal::service('cas_mock_server.server_manager');
      $cas_user_manager = \Drupal::service('cas_mock_server.user_manager');
      $external_auth = \Drupal::service('externalauth.externalauth');
      $entity_type_manager = \Drupal::service('entity_type.manager');

      $this->wrappedContext = new CasMockServerContext(
        $attributes_map,
        $server_manager,
        $cas_user_manager,
        $external_auth,
        $entity_type_manager
      );
      $this->wrappedContext->setDrupal($this->getDrupal());
      $this->wrappedContext->setMink($this->getMink());
      $this->wrappedContext->setMinkParameters($this->getMinkParameters());
    }

    return $this->wrappedContext;
  }

  /**
   * Enables the mock server.
   *
   * @param \Behat\Behat\Hook\Scope\BeforeScenarioScope $scope
   *   The before scenario scope.
   *
   * @BeforeScenario @casMockServer
   */
  public function startMockServer(BeforeScenarioScope $scope): void {
    $this->getWrappedContext()->startMockServer($scope);
  }

  /**
   * Disables the mock server.
   *
   * @AfterScenario @casMockServer
   */
  public function stopMockServer(): void {
    if ($this->wrappedContext) {
      $this->wrappedContext->stopMockServer();
    }
  }

  /**
   * Clean up any CAS users created in the scenario.
   *
   * @AfterScenario @casMockServer
   */
  public function cleanCasUsers(): void {
    if ($this->wrappedContext) {
      $this->wrappedContext->cleanCasUsers();
    }
  }

  /**
   * Registers users in the mock CAS service.
   *
   * @param \Behat\Gherkin\Node\TableNode $users_data
   *   The users to register.
   *
   * @Given (the following )CAS users:
   */
  public function registerUsers(TableNode $users_data): void {
    $this->getWrappedContext()->registerUsers($users_data);
  }

}
