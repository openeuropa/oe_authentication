<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Controller;

use Drupal\cas\Controller\ProxyCallbackController as CASProxyCallbackController;
use Psr\Log\LogLevel;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ProxyCallbackOEAuthenticationController.
 */
class ProxyCallbackController extends CASProxyCallbackController {

  /**
   * Route callback for the ProxyGrantingTicket information.
   *
   * This function stores the incoming PGTIOU and pgtId parameters so that
   * the incoming response from the EULogin Server can be looked up.
   */
  public function callback() {
    $current_request = $this->requestStack->getCurrentRequest();
    $this->casHelper->log(LogLevel::ERROR, 'Proxy callback processing started.');

    // @TODO: Check that request is coming from configured CAS server to avoid
    // filling up the table with bogus pgt values.
    // $authentication_server = $this->casHelper->getServerBaseUrl();
    $pgt_id = $current_request->request->get('pgtId');
    $pgt_iou = $current_request->request->get('pgtIou');

    // Check for both a pgtIou and pgtId parameter. If either is not present,
    // inform CAS Server of an error.
    if (!isset($pgt_id) && !isset($pgt_iou)) {
      $this->casHelper->log(LogLevel::ERROR, "Either pgtId or pgtIou parameters are missing from the request.");
      return new Response('Missing necessary parameters', 400);
    }

    // Store the pgtIou and pgtId in the database for later use.
    $this->storePgtMapping($pgt_iou, $pgt_id);
    $this->casHelper->log(
      LogLevel::DEBUG,
      "Storing pgtId %pgt_id with pgtIou %pgt_iou",
      ['%pgt_id' => $pgt_id, '%pgt_iou' => $pgt_iou]
    );

    // PGT stored properly, tell EULogin Server to proceed.
    $xml = sprintf(
        '<?xml version="1.0" encoding="UTF-8"?><proxySuccess xmlns="https://%s"/>',
        $current_request->getHost() . $current_request->getBaseUrl()
    );

    $response = new Response($xml);
    $response->headers->set('Content-Type', 'xml');

    return $response;
  }

}
