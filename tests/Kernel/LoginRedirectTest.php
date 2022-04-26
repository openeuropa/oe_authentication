<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_authentication\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the login redirect happens with the expected parameters.
 *
 * @group oe_authentication
 */
class LoginRedirectTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'cas',
    'cas_mock_server',
    'externalauth',
    'oe_authentication',
    'oe_authentication_eulogin_mock',
    'path_alias',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('system', ['key_value_expire']);
    $this->installConfig(['cas', 'cas_mock_server', 'oe_authentication']);
  }

  /**
   * Tests that has the two-factor auth parameters.
   */
  public function test2faRedirectParameter(): void {
    // Set the required configurations for CAS and to force the 2fa.
    $this->container->get('config.factory')->getEditable('cas.settings')->set('forced_login.enabled', TRUE)->save();
    $this->container->get('config.factory')->getEditable('oe_authentication.settings')->set('force_2fa', TRUE)->save();

    $request = Request::create(Url::fromRoute('user.login')->toString(TRUE)->getGeneratedUrl());

    $response = \Drupal::service('http_kernel')->handle($request);
    $redirect_string = 'Redirecting to https:/login?acceptStrengths=PASSWORD_MOBILE_APP%2CPASSWORD_SOFTWARE_TOKEN%2CPASSWORD_SMS&amp;service=http%3A//localhost/casservice%3Fdestination%3D/user/login';
    $this->assertStringContainsString($redirect_string, $response->getContent());
  }

}
