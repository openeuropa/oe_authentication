<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Service;

use Drupal\cas\Service\CasHelper;
use Drupal\cas\Service\CasValidator;
use Drupal\cas\Exception\CasValidateException;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use GuzzleHttp\Client;
use Drupal\cas\CasPropertyBag;
use Psr\Log\LogLevel;

/**
 * Class ECasValidator.
 *
 * @todo: Replace this custom class whenever
 *  https://www.drupal.org/project/cas/issues/2997099 gets fixed
 */
class ECasValidator extends CasValidator {

  /**
   * Stores ECAS settings object.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $ecasSettings;

  /**
   * Constructor.
   *
   * @param \GuzzleHttp\Client $http_client
   *   The HTTP Client library.
   * @param \Drupal\cas\Service\CasHelper $cas_helper
   *   The CAS Helper service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Routing\UrlGeneratorInterface $url_generator
   *   The URL generator.
   */
  public function __construct(Client $http_client, CasHelper $cas_helper, ConfigFactoryInterface $config_factory, UrlGeneratorInterface $url_generator) {
    parent::__construct($http_client, $cas_helper, $config_factory, $url_generator);
    $this->ecasSettings = $config_factory->get('oe_authentication.settings');
  }

  /**
   * {@inheritdoc}
   */
  private function validateVersion2($data) {
    $dom = new \DOMDocument();
    $dom->preserveWhiteSpace = FALSE;
    $dom->encoding = "utf-8";

    // Suppress errors from this function, as we intend to throw our own
    // exception.
    if (@$dom->loadXML($data) === FALSE) {
      throw new CasValidateException("XML from CAS server is not valid.");
    }

    $failure_elements = $dom->getElementsByTagName('authenticationFailure');
    if ($failure_elements->length > 0) {
      // Failed validation, extract the message and throw exception.
      $failure_element = $failure_elements->item(0);
      $error_code = $failure_element->getAttribute('code');
      $error_msg = $failure_element->nodeValue;
      throw new CasValidateException("Error Code " . trim($error_code) . ": " . trim($error_msg));
    }

    $success_elements = $dom->getElementsByTagName("authenticationSuccess");
    if ($success_elements->length === 0) {
      // All responses should have either an authenticationFailure
      // or authenticationSuccess node.
      throw new CasValidateException("XML from CAS server is not valid.");
    }

    // There should only be one success element, grab it and extract username.
    $success_element = $success_elements->item(0);
    $user_element = $success_element->getElementsByTagName("user");
    if ($user_element->length == 0) {
      throw new CasValidateException("No user found in ticket validation response.");
    }
    $username = $user_element->item(0)->nodeValue;
    $this->casHelper->log(
      LogLevel::DEBUG,
      "Extracted username %user from validation response.",
      ['%user' => $username]
    );
    $property_bag = new CasPropertyBag($username);

    // ECAS provides all atributes as children of the success_element.
    $property_bag->setAttributes($this->parseAttributes($success_element));

    // Look for a proxy chain, and if it exists, validate it against config.
    $proxy_chain = $success_element->getElementsByTagName("proxy");
    if ($this->settings->get('proxy.can_be_proxied') && $proxy_chain->length > 0) {
      $this->verifyProxyChain($proxy_chain);
    }

    if ($this->settings->get('proxy.initialize')) {
      // Extract the PGTIOU from the XML.
      $pgt_element = $success_element->getElementsByTagName("proxyGrantingTicket");
      if ($pgt_element->length == 0) {
        throw new CasValidateException("Proxy initialized, but no PGTIOU provided in response.");
      }
      $pgt = $pgt_element->item(0)->nodeValue;
      $this->casHelper->log(
        LogLevel::DEBUG,
        "Extracted PGT %pgt from validation response.",
        ['%pgt' => $pgt]
      );
      $property_bag->setPgt($pgt);
    }
    return $property_bag;
  }

  /**
   * {@inheritdoc}
   */
  private function verifyProxyChain(\DOMNodeList $proxy_chain) {
    $proxy_chains_raw = $this->settings->get('proxy.proxy_chains');
    $allowed_proxy_chains = $this->parseAllowedProxyChains($proxy_chains_raw);
    $server_chain = $this->parseServerProxyChain($proxy_chain);
    $this->casHelper->log(LogLevel::DEBUG, "Attempting to verify supplied proxy chain: %chain", ['%chain' => print_r($server_chain, TRUE)]);

    // Loop through the allowed chains, checking the supplied chain for match.
    foreach ($allowed_proxy_chains as $chain) {
      // If the lengths mismatch, cannot be a match.
      if (count($chain) != count($server_chain)) {
        continue;
      }

      // Loop through regex in the chain, matching against supplied URL.
      $flag = TRUE;
      foreach ($chain as $index => $regex) {
        if (preg_match('/^\/.*\/[ixASUXu]*$/s', $regex)) {
          if (!(preg_match($regex, $server_chain[$index]))) {
            $flag = FALSE;
            $this->casHelper->log(
              LogLevel::DEBUG,
              "Failed to match %regex with supplied %chain",
              ['%regex' => $regex, '%chain' => $server_chain[$index]]
            );
            break;
          }
        }
        else {
          if (!(strncasecmp($regex, $server_chain[$index], strlen($regex)) == 0)) {
            $flag = FALSE;
            $this->casHelper->log(
              LogLevel::DEBUG,
              "Failed to match %regex with supplied %chain",
              ['%regex' => $regex, '%chain' => $server_chain[$index]]
            );
            break;
          }
        }
      }

      // If we have a match, return.
      if ($flag == TRUE) {
        $this->casHelper->log(
          LogLevel::DEBUG,
          "Matched allowed chain: %chain",
          ['%chain' => print_r($chain, TRUE)]
        );
        return;
      }
    }

    // If we've reached this point, no chain was validated, so throw exception.
    throw new CasValidateException("Proxy chain did not match allowed list.");
  }

  /**
   * {@inheritdoc}
   */
  private function parseAllowedProxyChains($proxy_chains) {
    $chain_list = [];

    // Split configuration string on vertical whitespace.
    $chains = preg_split('/\v/', $proxy_chains, NULL, PREG_SPLIT_NO_EMPTY);

    // Loop through chains, splitting out each URL.
    foreach ($chains as $chain) {
      // Split chain string on any whitespace character.
      $list = preg_split('/\s/', $chain, NULL, PREG_SPLIT_NO_EMPTY);

      $chain_list[] = $list;
    }
    return $chain_list;
  }

  /**
   * {@inheritdoc}
   */
  private function parseServerProxyChain(\DOMNodeList $xml_list) {
    $proxies = [];
    // Loop through the DOMNodeList, adding each proxy to the list.
    foreach ($xml_list as $node) {
      $proxies[] = $node->nodeValue;
    }
    return $proxies;
  }

  /**
   * {@inheritdoc}
   */
  private function parseAttributes(\DOMElement $node) {
    $attributes = [];
    // @var \DOMElement $child
    foreach ($node->childNodes as $child) {
      $name = $child->localName;
      if ($child->hasAttribute('number')) {
        $value = $this->parseAttributes($child);
      }
      else {
        $value = $child->nodeValue;
      }
      $attributes[$name] = $value;
    }
    $this->casHelper->log(
      LogLevel::DEBUG,
      "Parsed the following attributes from the validation response: %attributes",
      ['%attributes' => print_r($attributes, TRUE)]
    );
    return $attributes;
  }

  /**
   * Return the validation URL used to validate the provided ticket.
   *
   * @param string $ticket
   *   The ticket to validate.
   * @param array $service_params
   *   An array of query string parameters to add to the service URL.
   *
   * @return string
   *   The fully constructed validation URL.
   */
  public function getServerValidateUrl($ticket, array $service_params = []) {
    $validate_url = $this->casHelper->getServerBaseUrl();
    $path = '';
    switch ($this->settings->get('server.version')) {
      case "1.0":
        $path = 'validate';
        break;

      case "2.0":
        if ($this->settings->get('proxy.can_be_proxied')) {
          $path = 'proxyValidate';
        }
        else {
          // Custom ECAS validation path.
          $path = 'TicketValidationService';
        }
        break;

      case "3.0":
        if ($this->settings->get('proxy.can_be_proxied')) {
          $path = 'p3/proxyValidate';
        }
        else {
          $path = 'p3/serviceValidate';
        }
        break;
    }
    $validate_url .= $path;

    $params = [];
    $params['service'] = $this->urlGenerator->generate('cas.service', $service_params, UrlGeneratorInterface::ABSOLUTE_URL);
    $params['ticket'] = $ticket;
    // We add the necessary ECAS parameters.
    $params['assuranceLevel'] = $this->ecasSettings->get('assurance_level');
    $params['ticketTypes'] = $this->ecasSettings->get('ticket_types');
    if ($this->settings->get('proxy.initialize')) {
      $params['pgtUrl'] = $this->formatProxyCallbackUrl();
    }
    return $validate_url . '?' . UrlHelper::buildQuery($params);
  }

  /**
   * Format the pgtCallbackURL parameter for use with proxying.
   *
   * We have to do a str_replace to force https for the proxy callback URL,
   * because it must use https, and setting the option 'https => TRUE' in the
   * options array won't force https if the user accessed the login route over
   * http and mixed-mode sessions aren't allowed.
   *
   * @return string
   *   The pgtCallbackURL, fully formatted.
   */
  private function formatProxyCallbackUrl() {
    return str_replace('http://', 'https://', $this->urlGenerator->generateFromRoute('cas.proxyCallback', [], [
      'absolute' => TRUE,
    ]));
  }

}
