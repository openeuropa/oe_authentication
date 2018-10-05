<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authentication\Behat;

use Drupal\DrupalExtension\Context\ConfigContext;

/**
 * Defines step definitions specifically for testing the RDF entities.
 *
 * We are extending ConfigContext to override the setConfig() method until
 * issue https://github.com/jhedstrom/drupalextension/issues/498 is fixed.
 *
 * @todo Extend DrupalRawContext and gather the config context when the above
 * issue is fixed.
 */
class OeAuthenticationContext extends ConfigContext {

  /**
   * Configures the CAS module to use Drupal login.
   *
   * @Given the site is configured to use Drupal login
   */
  public function setConfigDrupalLogin(): void {
    $this->setConfig('cas.settings', 'forced_login.enabled', FALSE);
  }

}
