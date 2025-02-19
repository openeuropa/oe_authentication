<?php

declare(strict_types=1);

namespace Drupal\oe_authentication\Event;

use Drupal\cas\Event\CasPreLoginEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\Core\Plugin\Context\EntityContext;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber that handles two-factor authentication conditions.
 */
class TwoFactorAuthenticationEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly ExecutableManagerInterface $conditionManager,
    protected readonly ContextHandlerInterface $contextHandler,
    protected readonly LoggerInterface $logger,
    protected readonly CasHelper $casHelper,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CasPreLoginEvent::class => 'checkTwoFactorAuthentication',
    ];
  }

  /**
   * Checks if two-factor authentication is required and used for the login.
   *
   * @param \Drupal\cas\Event\CasPreLoginEvent $event
   *   The pre-login event.
   */
  public function checkTwoFactorAuthentication(CasPreLoginEvent $event) {
    // If the login has been executed with a two-factor authentication method,
    // there is no need for further checks.
    if ($this->isLoginWithTwoFactorAuthentication($event)) {
      return;
    }

    $config = $this->configFactory->get('oe_authentication.settings');
    // If 2FA is set to be enforced for all users, it should have been applied
    // already on EU Login side. But something happened, and it wasn't applied
    // to this login attempt.
    if ($config->get('force_2fa')) {
      $event->cancelLogin($this->t('You are required to log in using a two-factor authentication method.'));
      return;
    }

    $conditions_configuration = $config->get('2fa_conditions');
    // If no conditions are present, all users can freely log in with any
    // method.
    if (empty($conditions_configuration)) {
      return;
    }

    $this->evaluateConditions($event, $conditions_configuration);
  }

  /**
   * Evaluates the configured conditions.
   *
   * @param \Drupal\cas\Event\CasPreLoginEvent $event
   *   The pre-login event.
   * @param mixed $conditions_configuration
   *   The conditions' configuration.
   */
  protected function evaluateConditions(CasPreLoginEvent $event, mixed $conditions_configuration): void {
    $contexts = [
      'user' => EntityContext::fromEntity($event->getAccount()),
    ];

    foreach ($conditions_configuration as $id => $configuration) {
      try {
        /** @var \Drupal\Core\Condition\ConditionInterface $plugin */
        $plugin = $this->conditionManager->createInstance($id, $configuration);
        if ($plugin instanceof ContextAwarePluginInterface) {
          $this->contextHandler->applyContextMapping($plugin, $contexts);
        }

        // Reject the login as soon as a condition plugin matches the user
        // account.
        if ($this->conditionManager->execute($plugin)) {
          $event->cancelLogin($this->t('You are required to log in using a two-factor authentication method.'));
          break;
        }
      }
      catch (\Throwable $exception) {
        // If any exception happens, we cannot trust the login attempt anymore.
        // Use the default error message from the CAS module.
        Error::logException($this->logger, $exception);
        $event->cancelLogin($this->casHelper->getMessage('error_handling.message_validation_failure'));
        break;
      }
    }
  }

  /**
   * Checks if the login has been executed with a two-factor authentication.
   *
   * @param \Drupal\cas\Event\CasPreLoginEvent $event
   *   The pre-login event.
   *
   * @return bool
   *   True if 2FA has been used, false otherwise.
   */
  protected function isLoginWithTwoFactorAuthentication(CasPreLoginEvent $event): bool {
    return in_array($event->getCasPropertyBag()->getAttribute('authenticationLevel'), [
      'MEDIUM',
      'HIGH',
    ]);
  }

}
