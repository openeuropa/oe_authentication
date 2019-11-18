<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication;

/**
 * Helper functions to process Cas attributes.
 */
class CasProcessor {

  /**
   * Array mapping CAS attributes with oe_authentication fields.
   */
  const USER_CAS_ATTRIBUTE_MAPPING = [
    'mail' => 'email',
    'field_oe_firstname' => 'firstName',
    'field_oe_lastname' => 'lastName',
    'field_oe_department' => 'departmentNumber',
    'field_oe_organisation' => 'domain',
  ];

  /**
   * Converts an array of cas attributes into an array of drupal field/values.
   *
   * @param array $attributes
   *   An array containing a series of cas attributes.
   *
   * @return array
   *   An array containing a series of Drupal field names and values.
   */
  public static function convertCasAttributesToFieldValues(array $attributes): array {
    $values = [];
    foreach (static::USER_CAS_ATTRIBUTE_MAPPING as $field_name => $property_name) {
      if (!empty($attributes[$property_name])) {
        $values[$field_name] = $attributes[$property_name];
      }
    }
    return $values;
  }

  /**
   * Parses the EU Login attributes from the validation response.
   *
   * @param string $source
   *   The string containing the validation response.
   *
   * @return array
   *   An array containing the parsed attributes.
   */
  public static function processValidationResponseAttributes(string $source): array {
    if (!CasProcessor::isValidResponse($source)) {
      throw new \InvalidArgumentException();
    }
    // Load cas attributes.
    $dom = new \DOMDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->encoding = "utf-8";
    @$dom->loadXML($source);
    $success_elements = $dom->getElementsByTagName("authenticationSuccess");

    // There should only be one success element, grab it and extract username.
    $success_element = $success_elements->item(0);
    // Parse the attributes coming from Eu Login
    // and add them to the default ones.
    $eulogin_attributes = CasProcessor::parseAttributes($success_element);
    return $eulogin_attributes;
  }

  /**
   * Parse the attributes list from the EU Login Server into an array.
   *
   * @param \DOMElement $node
   *   An XML element containing attributes.
   *
   * @return array
   *   An associative array of attributes.
   */
  private static function parseAttributes(\DOMElement $node): array {
    $attributes = [];
    // @var \DOMElement $child
    foreach ($node->childNodes as $child) {
      $name = $child->localName;
      if ($child->hasAttribute('number')) {
        $value = CasProcessor::parseAttributes($child);
      }
      else {
        $value = $child->nodeValue;
      }
      if ($name === 'group') {
        $attributes[$name][] = $value;
      }
      else {
        $attributes[$name] = $value;
      }
    }
    return $attributes;
  }

  /**
   * Check whether the validation response is valid or not.
   *
   * @param string $response
   *   The response to be validated.
   *
   * @return bool
   *   Whether the validation response is valid or not.
   */
  public static function isValidResponse(string $response) {
    $dom = new \DOMDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->encoding = "utf-8";

    // Suppress errors from this function, as we intend to allow other
    // event subscribers to work on the data.
    if (@$dom->loadXML($response) === FALSE) {
      return FALSE;
    }

    $success_elements = $dom->getElementsByTagName("authenticationSuccess");
    return $success_elements->length !== 0;
  }

}
