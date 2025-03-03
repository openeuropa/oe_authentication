<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_authentication\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests the login redirect happens with the expected parameters.
 *
 * @group oe_authentication
 */
class EuLoginEventSubscriberTest extends KernelTestBase {

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
   * History of requests/responses.
   *
   * @var array
   */
  protected $history = [];

  /**
   * Mock client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $mockHttpClient;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['cas', 'cas_mock_server', 'oe_authentication']);
    $this->installEntitySchema('path_alias');
  }

  /**
   * Tests the two-factor authentication parameters.
   *
   * @covers EuLoginEventSubscriber::forceTwoFactorAuthentication()
   */
  public function test2faRedirectParameter(): void {
    $config_factory = $this->container->get('config.factory');
    $config_factory->getEditable('cas.settings')->set('forced_login.enabled', TRUE)->save();

    $request = Request::create(Url::fromRoute('user.login')->toString(TRUE)->getGeneratedUrl());
    $response = $this->container->get('http_kernel')->handle(clone $request);
    $this->assertEquals(302, $response->getStatusCode());
    $this->assertStringNotContainsString('acceptStrengths', $response->getContent());

    // Set the config to force 2fa and redo the request to assert the params.
    $config = $config_factory->getEditable('oe_authentication.settings');
    $config->set('force_2fa', TRUE)->save();
    $response = $this->container->get('http_kernel')->handle(clone $request);
    $this->assertEquals(302, $response->getStatusCode());
    $redirect_meta = '<meta http-equiv="refresh" content="0;url=\'https:/login?acceptStrengths=PASSWORD_MOBILE_APP%2CPASSWORD_SOFTWARE_TOKEN%2CPASSWORD_SMS&amp;service=http%3A//localhost/casservice%3Fdestination%3D/user/login\'" />';
    $this->assertStringContainsString($redirect_meta, $response->getContent());

    // Conditions do not impact the forcing of 2FA.
    $config->set('2fa_conditions', [
      'user_role' => [
        'id' => 'user_role',
        'negate' => FALSE,
        'roles' => [
          'authenticated' => 'authenticated',
        ],
      ],
    ])->save();
    $response = $this->container->get('http_kernel')->handle(clone $request);
    $this->assertEquals(302, $response->getStatusCode());
    $this->assertStringContainsString($redirect_meta, $response->getContent());

    $config->set('force_2fa', FALSE)->save();
    $response = $this->container->get('http_kernel')->handle(clone $request);
    $this->assertEquals(302, $response->getStatusCode());
    $this->assertStringNotContainsString('acceptStrengths', $response->getContent());

    // The 2FA parameter is added if the page request has a "force_2fa" query
    // parameter.
    $request = Request::create(Url::fromRoute('user.login', options: [
      'query' => [
        'force_2fa' => TRUE,
      ],
    ])->toString(TRUE)->getGeneratedUrl());
    $response = $this->container->get('http_kernel')->handle(clone $request);
    $this->assertEquals(302, $response->getStatusCode());
    $this->assertStringContainsString(
      '<meta http-equiv="refresh" content="0;url=\'https:/login?acceptStrengths=PASSWORD_MOBILE_APP%2CPASSWORD_SOFTWARE_TOKEN%2CPASSWORD_SMS&amp;service=http%3A//localhost/casservice%3Fdestination%3D/user/login%253Fforce_2fa%253D1%26force_2fa%3D1\'" />',
      $response->getContent(),
    );

    // The parameter works also when used directly in the cas login route.
    $request = Request::create(Url::fromRoute('cas.login', options: [
      'query' => [
        'force_2fa' => TRUE,
      ],
    ])->toString(TRUE)->getGeneratedUrl());
    $response = $this->container->get('http_kernel')->handle(clone $request);
    $this->assertEquals(302, $response->getStatusCode());
    $this->assertStringContainsString(
      '<meta http-equiv="refresh" content="0;url=\'https:/login?acceptStrengths=PASSWORD_MOBILE_APP%2CPASSWORD_SOFTWARE_TOKEN%2CPASSWORD_SMS&amp;service=http%3A//localhost/casservice%3Fforce_2fa%3D1\'" />',
      $response->getContent(),
    );
  }

  /**
   * Tests that the validation request has the correct parameters.
   *
   * @covers EuLoginEventSubscriber::alterValidationPath()
   */
  public function testValidationParameters(): void {
    // Start the cas mock server.
    $server_manager = $this->container->get('cas_mock_server.server_manager');
    $server_manager->start();

    // Create a ticket for the request to validate with CAS.
    $user_data = [
      'username' => 'sharon',
      'email' => 'sharon@example.com',
      'password' => 'hunter2',
      'groups' => 'COMM_CEM, EDITORS,USERS ',
    ];
    $userManager = $this->container->get('cas_mock_server.user_manager');
    $userManager->addUser($user_data);
    $query['ticket'] = 'ST-123456789';
    $userManager->assignServiceTicket('sharon', $query['ticket']);

    // We need to prepare a request object with a valid session.
    // First, we disable session writing.
    /** @var \Drupal\Core\Session\WriteSafeSessionHandlerInterface $writeSafeHandler */
    $writeSafeHandler = $this->container->get('session_handler.write_safe');
    $writeSafeHandler->setSessionWritable(FALSE);
    // We start the session.
    /** @var \Symfony\Component\HttpFoundation\Session\SessionInterface $session */
    $session = $this->container->get('session');
    $session->start();

    // Returns the query string parameter from the last executed request.
    $get_query_from_request = function () use ($session, &$query): array {
      $request = Request::create(Url::fromUri('base:/casservice', ['query' => $query])->toString());
      $request->setSession($session);
      $this->container->get('http_kernel')->handle($request);
      /** @var \Psr\Http\Message\RequestInterface $last_request */
      $last_request = end($this->history)['request'];
      parse_str($last_request->getUri()->getQuery(), $result);

      return $result;
    };

    // Now we mock the http-client, in order to mock a "valid" response for
    // CasValidator class. The http_client should expect six requests.
    $this->mockHttpClient(
      new Response(200, [], 'Success'),
      new Response(200, [], 'Success'),
      new Response(200, [], 'Success'),
      new Response(200, [], 'Success'),
      new Response(200, [], 'Success'),
    );

    // Assert the validation parameters.
    $expected_query_without_2fa = [
      'service' => 'http://localhost/casservice',
      'ticket' => 'ST-123456789',
      'assuranceLevel' => 'TOP',
      'ticketTypes' => 'SERVICE,PROXY',
      'userDetails' => 'true',
      'groups' => '*',
    ];
    $this->assertSame($expected_query_without_2fa, $get_query_from_request());

    // Now force the two-factor authentication and create a new request to run.
    $config = $this->container->get('config.factory')->getEditable('oe_authentication.settings');
    $config->set('force_2fa', TRUE)->save();

    // Assert the validation parameters.
    $expected_query_with_2fa = $expected_query_without_2fa + [
      'acceptStrengths' => 'PASSWORD_MOBILE_APP,PASSWORD_SOFTWARE_TOKEN,PASSWORD_SMS',
    ];
    $this->assertSame($expected_query_with_2fa, $get_query_from_request());

    // Conditions do not impact the forcing of 2FA.
    $config->set('2fa_conditions', [
      'user_role' => [
        'id' => 'user_role',
        'negate' => FALSE,
        'roles' => [
          'authenticated' => 'authenticated',
        ],
      ],
    ])->save();
    $this->assertSame($expected_query_with_2fa, $get_query_from_request());

    $config->set('force_2fa', FALSE)->save();
    $this->assertSame($expected_query_without_2fa, $get_query_from_request());

    // Test that when the "force_2fa" query parameter is present in the service
    // URL, 2FA will be added to the validation.
    // First, we reset the conditions value. The presence of conditions does
    // not impact the force_2fa query parameter.
    $config->set('2fa_conditions', [])->save();

    $query['force_2fa'] = 1;
    $expected_query_with_2fa['service'] = 'http://localhost/casservice?force_2fa=1';
    $this->assertSame($expected_query_with_2fa, $get_query_from_request());
  }

  /**
   * Mocks the http-client.
   *
   * @param \GuzzleHttp\Psr7\Response ...$responses
   *   Several number of responses.
   */
  protected function mockHttpClient(Response ...$responses): void {
    if (!isset($this->mockHttpClient)) {
      // Create a mock and queue responses.
      $mock = new MockHandler($responses);

      $handler_stack = HandlerStack::create($mock);
      // Add a new history middleware, using the $this->history variable to
      // store the request/response history.
      $history = Middleware::history($this->history);
      // Push the history middleware into the handler stack.
      $handler_stack->push($history);
      // Create a new client with that handler stack.
      $this->mockHttpClient = new Client(['handler' => $handler_stack]);
    }
    // Set the http_client with our new mocked client.
    $this->container->set('http_client', $this->mockHttpClient);
  }

}
