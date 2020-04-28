<?php

/**
 * @file
 * Post update functions for OE Authentication module.
 */

declare(strict_types = 1);

/**
 * Customize error messages that refer to CAS.
 */
function oe_authentication_post_update_00001(array &$sandbox) {
  \Drupal::configFactory()->getEditable('cas.settings')
    ->set('error_handling.message_prevent_normal_login', 'This account must log in using <a href="[cas:login-url]">EU Login</a>.')
    ->set('error_handling.message_restrict_password_management', 'The requested account is associated with EU Login and its password cannot be managed from this website.')
    ->save();
}
