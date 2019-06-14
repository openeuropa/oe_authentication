<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication_user_fields;

/**
 * Helper functions to map EU Login attributes to user profile.
 */
class EuLoginAttributesHelper {

  /**
   * Array mapping EU Login attributes with user account fields.
   */
  const USER_EU_LOGIN_ATTRIBUTE_MAPPING = [
    'mail' => 'email',
    'field_oe_firstname' => 'firstName',
    'field_oe_lastname' => 'lastName',
    'field_oe_department' => 'departmentNumber',
    'field_oe_organisation' => 'domain',
  ];

  /**
   * Converts an array EU Login attributes into an array of Drupal field/values.
   *
   * @param array $attributes
   *   An array containing a series of EU Login attributes.
   *
   * @return array
   *   An array containing a series of Drupal field names and values.
   */
  public static function convertEuLoginAttributesToFieldValues(array $attributes): array {
    $values = [];
    foreach (static::USER_EU_LOGIN_ATTRIBUTE_MAPPING as $field_name => $property_name) {
      if (!empty($attributes[$property_name])) {
        $values[$field_name] = $attributes[$property_name];
      }
    }
    return $values;
  }

}
