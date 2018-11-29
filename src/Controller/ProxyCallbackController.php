<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Controller;

use Drupal\cas\Controller\ProxyCallbackController as CASProxyCallbackController;
use Drupal\cas\Service\CasHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ProxyCallbackOEAuthenticationController.
 */
class ProxyCallbackController extends CASProxyCallbackController {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Database\Connection $database_connection
   *   The database service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The Symfony request stack.
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   *   The CasHelper.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Config Factory.
   */
  public function __construct(Connection $database_connection, RequestStack $request_stack, CasHelper $cas_helper, ConfigFactoryInterface $configFactory) {
    parent::__construct($database_connection, $request_stack, $cas_helper);
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('request_stack'),
      $container->get('cas.helper'),
      $container->get('config.factory')
    );
  }

  /**
   * Route callback for the ProxyGrantingTicket information.
   *
   * This function stores the incoming PGTIOU and pgtId parameters so that
   * the incoming response from the EULogin Server can be looked up.
   */
  public function callback() {
    $config = $this->configFactory->get('oe_authentication.settings');
    if ($config->get('protocol') !== 'eulogin') {
      parent::callback();
    }

    $current_request = $this->requestStack->getCurrentRequest();

    // Check the method of the request.
    if ($current_request->getRealMethod() !== 'POST') {
      return new Response(t('The Request should use the POST method'), 400);
    }

    $pgt_id = $current_request->request->get('pgtId');
    $pgt_iou = $current_request->request->get('pgtIou');

    // Check for both a pgtIou and pgtId parameter.
    // If either is not present, inform EULogin Server of an error.
    if (!isset($pgt_id) && !isset($pgt_iou)) {
      return new Response(t('Missing necessary parameters'), 400);
    }

    if ($this->checkPgtMapping($pgt_iou, $pgt_id)) {
      return new Response(t('Parameters already in use'), 400);
    }

    // Store the pgtIou and pgtId in the database for later use.
    $this->storePgtMapping($pgt_iou, $pgt_id);

    // PGT stored properly, tell EULogin Server to proceed.
    $xml = sprintf(
        '<?xml version="1.0" encoding="UTF-8"?><proxySuccess xmlns="https://%s"/>',
        $current_request->getHost() . $current_request->getBaseUrl()
    );

    $response = new Response($xml);
    $response->headers->set('Content-Type', 'xml');

    return $response;
  }

  /**
   * Check if the pgtIou and pgtId are mapped in the database.
   *
   * @param string $pgt_iou
   *   The pgtIou from CAS Server.
   * @param string $pgt_id
   *   The pgtId from the CAS server.
   *
   * @return bool
   *   Return TRUE is present in the database.
   */
  protected function checkPgtMapping($pgt_iou, $pgt_id) {
    $result = $this->connection->select('cas_pgt_storage')
      ->fields('cas_pgt_storage', ['pgt_iou', 'pgt'])
      ->condition('pgt_iou', $pgt_iou)
      ->condition('pgt', $pgt_id)
      ->execute()->fetchAll();

    return count($result) > 0;
  }

}
