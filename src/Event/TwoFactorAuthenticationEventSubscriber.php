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
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Error;
use Drupal\user\UserInterface;
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
    TranslationInterface $stringTranslation,
  ) {
    $this->stringTranslation = $stringTranslation;
  }

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
    // This has not been enforced in the past, and we cannot change this
    // behaviour in a minor.
    // @todo In the next major, consider cancelling the login altogether.
    if ($config->get('force_2fa')) {
      $this->logger->warning('Two-factor authentication is enforced, but user @uid was logged in without 2FA (authenticationLevel: %level)', [
        '@uid' => $event->getAccount()->id(),
        '%level' => $event->getCasPropertyBag()->getAttribute('authenticationLevel') ?? 'NULL',
      ]);
      return;
    }

    $conditions_configuration = $config->get('2fa_conditions') ?? [];
    try {
      if ($this->isTwoFactorAuthenticationRequiredForUser($event->getAccount(), $conditions_configuration)) {
        $event->cancelLogin($config->get('message_login_2fa_required'));
      }
    }
    catch (\Throwable $exception) {
      // If any exception happens, we cannot trust the login attempt anymore.
      // Use the default error message from the CAS module.
      Error::logException(
        $this->logger,
        $exception,
        'An exception occurred when evaluating 2FA conditions for account with uid @uid. ' . Error::DEFAULT_ERROR_MESSAGE,
        [
          '@uid' => $event->getAccount()->id(),
        ],
      );
      $event->cancelLogin($this->casHelper->getMessage('error_handling.message_validation_failure'));
    }
  }

  /**
   * Returns if two-factor authentication is required for a user account.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account being logged in.
   * @param array[] $conditions_configuration
   *   The conditions' configuration.
   *
   * @return bool
   *   TRUE if 2FA should be required for this account login, FALSE otherwise.
   *
   * @throws \Throwable
   *   The method does not catch any exceptions thrown during plugin execution.
   */
  protected function isTwoFactorAuthenticationRequiredForUser(UserInterface $user, array $conditions_configuration): bool {
    // If no conditions are present, 2FA is not required.
    if (empty($conditions_configuration)) {
      return FALSE;
    }

    $contexts = [
      'user' => EntityContext::fromEntity($user),
    ];

    foreach ($conditions_configuration as $id => $configuration) {
      /** @var \Drupal\Core\Condition\ConditionInterface $plugin */
      $plugin = $this->conditionManager->createInstance($id, $configuration);
      if ($plugin instanceof ContextAwarePluginInterface) {
        $this->contextHandler->applyContextMapping($plugin, $contexts);
      }

      // Reject the login as soon as a condition plugin matches the user
      // account.
      if ($this->conditionManager->execute($plugin)) {
        return TRUE;
      }
    }

    return FALSE;
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
    return in_array(
      $event->getCasPropertyBag()->getAttribute('authenticationLevel'),
      ['MEDIUM', 'HIGH'],
    );
  }

}
