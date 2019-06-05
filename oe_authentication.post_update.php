<?php

/**
 * @file
 * Post update functions for OE Authentication module.
 */

declare(strict_types = 1);

/**
 * Add the 'redirect_user_register_route' module setting.
 */
function oe_authentication_post_update_user_register_redirect(): void {
  \Drupal::configFactory()->getEditable('oe_authentication.settings')
    ->set('redirect_user_register_route', TRUE)
    ->save();
}
