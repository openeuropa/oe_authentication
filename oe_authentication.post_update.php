<?php

/**
 * @file
 * Post update functions for OE Authentication module.
 */

declare(strict_types=1);

/**
 * Customize error messages that refer to CAS.
 */
function oe_authentication_post_update_00001(array &$sandbox) {
  \Drupal::configFactory()->getEditable('cas.settings')
    ->set('error_handling.message_prevent_normal_login', 'This account must log in using <a href="[cas:login-url]">EU Login</a>.')
    ->set('error_handling.message_restrict_password_management', 'The requested account is associated with EU Login and its password cannot be managed from this website.')
    ->save();
}

/**
 * Update email domain in CAS settings.
 */
function oe_authentication_post_update_00002(array &$sandbox) {
  \Drupal::configFactory()->getEditable('cas.settings')
    ->set('user_accounts.email_hostname', 'example.com')
    ->save();
}

/**
 * Set force_2fa to FALSE as default.
 */
function oe_authentication_post_update_00003() {
  \Drupal::configFactory()->getEditable('oe_authentication.settings')
    ->set('force_2fa', FALSE)->save();
}
