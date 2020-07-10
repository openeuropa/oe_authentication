<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Event;

use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber for CAS module events.
 *
 * The class subscribes to the events provided by the CAS module and shows
 * messages accordingly.
 */
class MessengerEuLoginEventSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs the MessengerEuLoginEventSubscriber.
   *
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(MessengerInterface $messenger) {
    $this->messenger = $messenger;
  }

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * @return array
   *   The event names to listen to.
   */
  public static function getSubscribedEvents(): array {
    $events = [];
    $events[CasHelper::EVENT_PRE_REGISTER] = ['showUserMessage', -100];
    return $events;
  }

  /**
   * Show user message about its activation status.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The triggered event.
   */
  public function showUserMessage(CasPreRegisterEvent $event): void {
    $properties = $event->getPropertyValues();

    if (!isset($properties['status'])) {
      return;
    }

    if (!$properties['status']) {
      $this->messenger->addStatus($this->t('Thank you for applying for an account. Your account is currently pending approval by the site administrator.'));
    }
  }

}
