<?php

declare(strict_types=1);

namespace Drupal\oe_authentication_corporate_roles\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\oe_authentication_corporate_roles\CorporateRolesMappingInterface;
use Drupal\user\UserInterface;

/**
 * Defines the corporate roles mapping entity type.
 *
 * @ConfigEntityType(
 *   id = "corporate_roles_mapping",
 *   label = @Translation("Corporate roles mapping"),
 *   label_collection = @Translation("Corporate roles mappings"),
 *   label_singular = @Translation("corporate roles mapping"),
 *   label_plural = @Translation("corporate roles mappings"),
 *   label_count = @PluralTranslation(
 *     singular = "@count corporate roles mapping",
 *     plural = "@count corporate roles mappings",
 *   ),
 *   handlers = {
 *     "list_builder" = "Drupal\oe_authentication_corporate_roles\CorporateRolesMappingListBuilder",
 *     "form" = {
 *       "add" = "Drupal\oe_authentication_corporate_roles\Form\CorporateRolesMappingForm",
 *       "edit" = "Drupal\oe_authentication_corporate_roles\Form\CorporateRolesMappingForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm",
 *     },
 *   },
 *   config_prefix = "corporate_roles_mapping",
 *   admin_permission = "manage corporate roles",
 *   links = {
 *     "collection" = "/admin/people/corporate-roles-mapping",
 *     "add-form" = "/admin/people/corporate-roles-mapping/add",
 *     "edit-form" = "/admin/people/corporate-roles-mapping/{corporate_roles_mapping}",
 *     "delete-form" = "/admin/people/corporate-roles-mapping/{corporate_roles_mapping}/delete",
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "matching_value_type",
 *     "value",
 *     "roles"
 *   },
 * )
 */
final class CorporateRolesMapping extends ConfigEntityBase implements CorporateRolesMappingInterface {

  /**
   * LDAP group matching type.
   */
  public const LDAP_GROUP = 'ldap_group';

  /**
   * Department matching type.
   */
  public const DEPARTMENT = 'department';

  /**
   * The ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The example label.
   *
   * @var string
   */
  protected $label;

  /**
   * The type of value to match against: LDAP group or department.
   *
   * @var string
   */
  protected $matching_value_type;

  /**
   * The value to match.
   *
   * @var string
   */
  protected $value;

  /**
   * The roles to match.
   *
   * @var array
   */
  protected $roles = [];

  /**
   * Removes the mapped roles from the user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function removeMappingRoles(UserInterface $user): void {
    $manual_roles = array_column($user->get('oe_manual_roles')->getValue(), 'target_id');
    foreach ($user->getRoles(TRUE) as $role) {
      if (!in_array($role, $manual_roles) && in_array($role, $this->roles)) {
        $user->removeRole($role);
      }
    }
  }

  /**
   * Removes the mapping reference from the user.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function removeMappingReference(UserInterface $user): void {
    $existing_mappings = array_column($user->get('oe_corporate_roles_mappings')->getValue(), 'target_id');
    $current_mapping_id = $this->id();
    $existing_mappings = array_filter($existing_mappings, function ($id) use ($current_mapping_id) {
      return $id !== $current_mapping_id;
    });
    $user->set('oe_corporate_roles_mappings', $existing_mappings);
  }

  /**
   * Updates the roles of the user with the ones from the mapping.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   */
  public function updateUserRoles(UserInterface $user): void {
    // Set the roles.
    foreach ($this->roles as $role) {
      $user->addRole($role);
    }

    // Reference the mapping.
    $mappings = array_column($user->get('oe_corporate_roles_mappings')->getValue(), 'target_id');
    $mappings[] = $this->id();
    $user->set('oe_corporate_roles_mappings', array_unique($mappings));

    // Ensure the account is active.
    $user->activate();
  }

}
