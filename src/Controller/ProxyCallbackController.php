<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Controller;

use Drupal\cas\Controller\ProxyCallbackController as CASProxyCallbackController;
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
    $config = \Drupal::configFactory()->getEditable('oe_authentication.settings');
    if ('cas' === $config->get('protocol')) {
      parent::callback();
    }

    $current_request = $this->requestStack->getCurrentRequest();

    // Check the method of the request.
    if ('POST' !== $current_request->getRealMethod()) {
      return new Response(t('The Request should use the POST method'), 400);
    }

    $pgt_id = $current_request->request->get('pgtId');
    $pgt_iou = $current_request->request->get('pgtIou');

    // Check for both a pgtIou and pgtId parameter.
    // If either is not present, inform EULogin Server of an error.
    if (!isset($pgt_id) && !isset($pgt_iou)) {
      return new Response(t('Missing necessary parameters'), 400);
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

}
