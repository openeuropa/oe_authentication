<?php

declare(strict_types=1);

namespace Drupal\oe_authentication_test;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Decorates the oe_authentication logger channel to store entries in state.
 */
class TestLoggerDecorator implements LoggerChannelInterface {

  use LoggerTrait;

  public function __construct(
    protected LoggerChannelInterface $decorated,
    protected StateInterface $state,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function log($level, $message, array $context = []): void {
    $this->decorated->log($level, $message, $context);

    $messages = $this->state->get('oe_authentication_test.log_messages', []);
    $messages += [$level => []];
    // Store only the decoded, HTML-free message.
    $messages[$level][] = Html::decodeEntities(strip_tags((string) (new FormattableMarkup($message, $context))));
    $this->state->set('oe_authentication_test.log_messages', $messages);
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestStack(?RequestStack $requestStack = NULL) {
    $this->decorated->setRequestStack($requestStack);
  }

  /**
   * {@inheritdoc}
   */
  public function setCurrentUser(?AccountInterface $current_user = NULL) {
    $this->decorated->setCurrentUser($current_user);
  }

  /**
   * {@inheritdoc}
   */
  public function setLoggers(array $loggers) {
    $this->decorated->setLoggers($loggers);
  }

  /**
   * {@inheritdoc}
   */
  public function addLogger(LoggerInterface $logger, $priority = 0) {
    $this->decorated->addLogger($logger, $priority);
  }

}
