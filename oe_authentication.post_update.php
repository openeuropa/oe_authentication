<?php

/**
 * @file
 * Post update functions for OE Authentication module.
 */

declare(strict_types = 1);

/**
 * Add the 'block_on_site_admin_approval' module setting.
 */
function oe_authentication_post_update_user_register_redirect(): void {
  \Drupal::configFactory()->getEditable('oe_authentication.settings')
    ->set('block_on_site_admin_approval', TRUE)
    ->save();
}
