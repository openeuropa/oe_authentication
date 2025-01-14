<?php

declare(strict_types=1);

namespace Drupal\oe_authentication_corporate_roles\EventSubscriber;

use Drupal\cas\Event\CasPostLoginEvent;
use Drupal\cas\Event\CasPreRegisterEvent;
use Drupal\oe_authentication_corporate_roles\CorporateRolesMappingLookup;
use Drupal\oe_authentication_user_fields\EuLoginAttributesHelper;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Copies the EU Login attributes to user fields.
 */
class EuLoginSubscriber implements EventSubscriberInterface {

  /**
   * The mapping lookup.
   *
   * @var \Drupal\oe_authentication_corporate_roles\CorporateRolesMappingLookup
   */
  protected $mappingLookup;

  /**
   * Constructs a EuLoginSubscriber.
   *
   * @param \Drupal\oe_authentication_corporate_roles\CorporateRolesMappingLookup $mappingLookup
   *   The mapping lookup.
   */
  public function __construct(CorporateRolesMappingLookup $mappingLookup) {
    $this->mappingLookup = $mappingLookup;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // We ensure this runs after EuLoginAttributesToUserFieldsSubscriber.
      CasPostLoginEvent::class => ['onPostLogin', -100],
      CasPreRegisterEvent::class => ['postProcessUserProperties', -50],
    ];
  }

  /**
   * Handles the role assignment once the user logged in.
   *
   * @param \Drupal\cas\Event\CasPostLoginEvent $event
   *   The triggered event.
   */
  public function onPostLogin(CasPostLoginEvent $event): void {
    $account = $event->getAccount();
    // First, if there are any mapping referenced, clear the roles of those
    // mappings and the mappings themselves.
    $result = $this->clearExistingMappings($account);

    $mappings = $this->mappingLookup->getMappingsForUser($account);

    if (!$mappings) {
      // If we don't have any mappings for this user, bail out. But also save
      // the user account if a change had been made earlier.
      if ($result) {
        $account->automatic_corporate_roles = TRUE;
        $account->save();
      }
      return;
    }

    // Add all the mapping roles for the mappings that have been found.
    $roles = [];
    foreach ($mappings as $mapping) {
      $roles = array_merge($roles, $mapping->get('roles'));
    }
    $roles = array_unique($roles);
    foreach ($roles as $role) {
      $account->addRole($role);
    }

    // Reference the mappings.
    $mapping_ids = [];
    foreach ($mappings as $mapping) {
      $mapping_ids[] = $mapping->id();
    }
    $account->set('oe_corporate_roles_mappings', array_unique($mapping_ids));

    // Ensure the account is active and save.
    $account->activate();
    $account->automatic_corporate_roles = TRUE;
    $account->save();
  }

  /**
   * Acts on the CAS user registration.
   *
   * If the user registers and they have a potential mapping, set the status
   * to active. Apart from the fact that it's needed, it will allow the user
   * to log in, and the post login subscriber kicks in and assigns the roles.
   *
   * @param \Drupal\cas\Event\CasPreRegisterEvent $event
   *   The triggered event.
   */
  public function postProcessUserProperties(CasPreRegisterEvent $event): void {
    $properties = EuLoginAttributesHelper::convertEuLoginAttributesToFieldValues($event->getCasPropertyBag()->getAttributes());
    $account = User::create([]);
    foreach ($properties as $name => $value) {
      if (is_array($value)) {
        $value = array_values($value);
      }
      $account->set($name, $value);
    }
    $mappings = $this->mappingLookup->getMappingsForUser($account);
    if ($mappings) {
      $event->setPropertyValue('status', 1);
    }
  }

  /**
   * Clears the existing roles and mappings from the user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return bool
   *   Whether a change was made on the user.
   */
  protected function clearExistingMappings(UserInterface $user): bool {
    if ($user->get('oe_corporate_roles_mappings')->isEmpty()) {
      // If there are no mapping referenced, we don't need to do anything. It
      // means the user never got any automatic roles.
      return FALSE;
    }

    /** @var \Drupal\oe_authentication_corporate_roles\Entity\CorporateRolesMapping[] $mappings */
    $mappings = $user->get('oe_corporate_roles_mappings')->referencedEntities();
    foreach ($mappings as $mapping) {
      $mapping->removeMappingRoles($user);
      $mapping->removeMappingReference($user);
    }

    return TRUE;
  }

}
