<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication;

/**
 * Helper functions to process Cas attributes.
 */
class CasProcessor {

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
    if (!static::isValidResponse($source)) {
      throw new \InvalidArgumentException();
    }
    // Load cas attributes.
    $dom = new \DOMDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->encoding = 'utf-8';
    @$dom->loadXML($source);
    $success_elements = $dom->getElementsByTagName('authenticationSuccess');

    // There should only be one success element, grab it and extract username.
    $success_element = $success_elements->item(0);
    // Parse the attributes coming from Eu Login
    // and add them to the default ones.
    $eulogin_attributes = static::parseAttributes($success_element);
    return $eulogin_attributes;
  }

  /**
   * Parse the attributes list from the EU Login Server into an array.
   *
   * @param \DOMElement $node
   *   An XML element containing attributes.
   * @param bool $toplevel
   *   Whether the method is called from out of the recursive loop.
   *
   * @return array
   *   An array of attributes.
   */
  protected static function parseAttributes(\DOMElement $node, bool $toplevel = TRUE): array {
    // Check if we can return an associative array or if
    // we must use numeric keys.
    $associative = $toplevel || static::isAssociative($node);

    $attributes = [];
    /** @var \DOMElement $child */
    foreach ($node->childNodes as $key => $child) {
      $name = $child->localName;
      // If the child has sub-levels, recursively parse the attributes
      // underneath.
      if ($child->hasAttribute('number')) {
        $value = static::parseAttributes($child, FALSE);
      }
      else {
        $value = $child->nodeValue;
      }

      if ($associative) {
        $attributes[$name] = $value;
      }
      else {
        $attributes[] = $value;
      }

    }
    return $attributes;
  }

  /**
   * Checks if the node children can be represented as an associative array.
   *
   * Array can be associative if it will get different names for all keys.
   *
   * @param \DOMElement $node
   *   The node element.
   *
   * @return bool
   *   Whether the node children should be mapped to an associated array.
   */
  protected static function isAssociative(\DOMElement $node): bool {
    $names = $counter = [];
    foreach ($node->childNodes as $child) {
      $names[$child->localName] = $counter[] = $child->localName;
    }
    return count($names) === count($counter);
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
    $dom->encoding = 'utf-8';

    // Suppress errors from this function, as we intend to allow other
    // event subscribers to work on the data.
    if (@$dom->loadXML($response) === FALSE) {
      return FALSE;
    }

    $success_elements = $dom->getElementsByTagName('authenticationSuccess');
    return $success_elements->length !== 0;
  }

}
