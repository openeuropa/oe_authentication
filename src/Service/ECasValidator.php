<?php

declare(strict_types = 1);

namespace Drupal\oe_authentication\Service;

use Drupal\cas\Service\CasValidator;
use Drupal\cas\Service\CasHelper;
use Drupal\cas\Exception\CasValidateException;
use GuzzleHttp\Exception\RequestException;
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
   * {@inheritdoc}
   */
  public function validateTicket($ticket, array $service_params = []) {
    $options = [];
    $verify = $this->settings->get('server.verify');
    switch ($verify) {
      case CasHelper::CA_CUSTOM:
        $cert = $this->settings->get('server.cert');
        $options['verify'] = $cert;
        break;

      case CasHelper::CA_NONE:
        $options['verify'] = FALSE;
        break;

      case CasHelper::CA_DEFAULT:
      default:
        // This triggers for CasHelper::CA_DEFAULT.
        $options['verify'] = TRUE;
    }

    $options['timeout'] = $this->settings->get('advanced.connection_timeout');

    $validate_url = $this->getServerValidateUrl($ticket, $service_params);
    $this->casHelper->log(
      LogLevel::DEBUG,
      'Attempting to validate service ticket %ticket by making request to URL %url',
      ['%ticket' => $ticket, '%url' => $validate_url]
    );

    try {
      $response = $this->httpClient->get($validate_url, $options);
      $response_data = $response->getBody()->__toString();
      $this->casHelper->log(LogLevel::DEBUG, "Validation response received from CAS server: %data", ['%data' => $response_data]);
    }
    catch (RequestException $e) {
      throw new CasValidateException("Error with request to validate ticket: " . $e->getMessage());
    }

    return $this->validateEcas($response_data);
  }

  /**
   * {@inheritdoc}
   */
  private function validateEcas($data) {
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

}
