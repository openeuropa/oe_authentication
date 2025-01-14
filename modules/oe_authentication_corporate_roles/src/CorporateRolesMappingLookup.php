<?php

declare(strict_types=1);

namespace Drupal\oe_authentication_corporate_roles;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\oe_authentication_corporate_roles\Entity\CorporateRolesMapping;
use Drupal\user\UserInterface;

/**
 * Helper class for looking up mappings for users.
 */
class CorporateRolesMappingLookup {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new CorporateRoleMappingLookup.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Gets the matching users of a given mapping.
   *
   * @param \Drupal\oe_authentication_corporate_roles\Entity\CorporateRolesMapping $mapping
   *   The mapping.
   *
   * @return \Drupal\user\UserInterface[]
   *   The users.
   */
  public function getMatchingUsers(CorporateRolesMapping $mapping): array {
    $matching_type = $mapping->get('matching_value_type');
    $value = $mapping->get('value');

    $query = $this->entityTypeManager->getStorage('user')->getQuery()
      // This only works for users from the EC.
      ->condition('field_oe_organisation', 'eu.europa.ec')
      ->accessCheck(FALSE);

    if ($matching_type === CorporateRolesMapping::LDAP_GROUP) {
      // For LDAP groups, we can just query using string comparison.
      $query->condition('field_oe_ldap_groups', $value);
    }
    else {
      // Otherwise, for departments, we need to use STARTS_WITH because we need
      // to include the broader department/unit/sector.
      $query->condition('field_oe_department', $value, 'STARTS_WITH');
    }

    $ids = $query->execute();
    if ($ids) {
      return $this->entityTypeManager->getStorage('user')->loadMultiple($ids);
    }

    return [];
  }

  /**
   * Gets the users with a given mapping.
   *
   * @param \Drupal\oe_authentication_corporate_roles\Entity\CorporateRolesMapping $mapping
   *   The mapping.
   *
   * @return \Drupal\user\UserInterface[]
   *   The users.
   */
  public function getUsersWithMapping(CorporateRolesMapping $mapping): array {
    $ids = $this->entityTypeManager->getStorage('user')->getQuery()
      ->condition('oe_corporate_roles_mappings', $mapping->id())
      ->accessCheck(FALSE)
      ->execute();

    if (!$ids) {
      return [];
    }

    return $this->entityTypeManager->getStorage('user')->loadMultiple($ids);
  }

  /**
   * Locates potential mappings for a user.
   *
   * We search both by the LDAP group and by department and find all mappings
   * that match.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user.
   *
   * @return \Drupal\oe_authentication_corporate_roles\Entity\CorporateRolesMapping[]
   *   The mappings.
   */
  public function getMappingsForUser(UserInterface $user): array {
    if ($user->get('field_oe_ldap_groups')->isEmpty() && $user->get('field_oe_department')->isEmpty()) {
      return [];
    }

    if ($user->get('field_oe_organisation')->value !== 'eu.europa.ec') {
      return [];
    }

    $query = $this->entityTypeManager->getStorage('corporate_roles_mapping')->getQuery()
      ->accessCheck(FALSE);

    $or_group = $query->orConditionGroup();

    if (!$user->get('field_oe_ldap_groups')->isEmpty()) {
      $ldap_group_condition = $query->andConditionGroup();
      $ldap_group_condition->condition('value', $user->get('field_oe_ldap_groups')->value);
      $ldap_group_condition->condition('matching_value_type', CorporateRolesMapping::LDAP_GROUP);
      $or_group->condition($ldap_group_condition);
    }

    if (!$user->get('field_oe_department')->isEmpty()) {
      $department_condition = $query->andConditionGroup();
      $department_condition->condition('value', $this->processDepartmentValue($user->get('field_oe_department')->value), 'IN');
      $department_condition->condition('matching_value_type', CorporateRolesMapping::DEPARTMENT);
      $or_group->condition($department_condition);
    }

    $query->condition($or_group);
    $ids = $query->execute();

    if (!$ids) {
      return [];
    }

    return $this->entityTypeManager->getStorage('corporate_roles_mapping')->loadMultiple($ids);
  }

  /**
   * Processes the department value to turn it into an array of options.
   *
   * @param string $department
   *   The department string value.
   *
   * @return array
   *   The array of concatenated options.
   */
  protected function processDepartmentValue(string $department): array {
    $parts = explode('.', $department);
    $values = [];
    $accumulator = '';
    foreach ($parts as $key => $value) {
      $accumulator = ($key === 0) ? $value : $accumulator . '.' . $value;
      $values[] = $accumulator;
    }

    return $values;
  }

}
