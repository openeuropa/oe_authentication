<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication_user_fields;

/**
 * Helper functions to map EU Login attributes to user profile.
 */
class EuLoginAttributesHelper {

  /**
   * Array mapping of EU Login attributes with user account fields.
   */
  const USER_EU_LOGIN_ATTRIBUTE_MAPPING = [
    'mail' => 'email',
    'field_oe_firstname' => 'firstName',
    'field_oe_lastname' => 'lastName',
    'field_oe_department' => 'departmentNumber',
    'field_oe_organisation' => 'domain',
  ];

  /**
   * Converts the EU Login attributes into a Drupal field/values array.
   *
   * @param array $attributes
   *   An array containing a series of EU Login attributes.
   *
   * @return array
   *   An associative array of field values indexed by the field name.
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
