<?php

declare(strict_types = 1);

namespace Drupal\Tests\cas_mock_server\Kernel;

use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that the mock response mimics the EU Login one.
 *
 * @group oe_authentication_eulogin_mock
 */
class MockResponseTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'cas',
    'cas_mock_server',
    'externalauth',
    'oe_authentication',
    'oe_authentication_eulogin_mock',
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
   * Tests that attributes are reorganised correctly by the response subscriber.
   */
  public function testEuLoginAttributes(): void {
    // Set up a test user with a service ticket.
    $ticket = 'ST-123456789';
    $user_data = [
      'username' => 'sharon',
      'email' => 'sharon@example.com',
      'password' => 'hunter2',
      'groups' => 'COMM_CEM, EDITORS,USERS ',
    ];
    $userManager = $this->container->get('cas_mock_server.user_manager');
    $userManager->addUser($user_data);
    $userManager->assignServiceTicket('sharon', $ticket);

    // Request to validate the ticket.
    $request = Request::create(Url::fromRoute('cas_mock_server.validate', [], [
      'query' => [
        'ticket' => $ticket,
      ],
    ])->toString(TRUE)->getGeneratedUrl());

    $response = \Drupal::service('http_kernel')->handle($request);

    $crawler = new Crawler($response->getContent());
    // The cas:attributes element has been removed.
    $this->assertCount(0, $crawler->filterXPath('//cas:attributes'));
    // All the user attributes are just under the success element.
    $success = $crawler->filterXPath('//cas:serviceResponse/cas:authenticationSuccess');
    $this->assertCount(1, $success);
    // To use xpath properly on children, we need to extract them.
    // @see \Symfony\Component\DomCrawler\Crawler::filterXPath()
    $attributes = $success->children();
    $this->assertEquals('sharon', $attributes->filterXPath('./cas:user')->text());
    $this->assertEquals('sharon@example.com', $attributes->filterXPath('./cas:email')->text());
    $this->assertEquals('hunter2', $attributes->filterXPath('./cas:password')->text());
    $groups = $attributes->filterXPath('./cas:groups');
    $this->assertCount(1, $groups);
    $this->assertEquals(3, $groups->attr('number'));
    $this->assertEquals([
      'COMM_CEM',
      'EDITORS',
      'USERS',
    ], $groups->children()->filterXPath('./cas:group')->extract(['_text']));
  }

}
