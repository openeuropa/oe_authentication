<?php

namespace Drupal\eu_login\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Routing\RedirectDestination;
use GuzzleHttp\Psr7\Response;
use OpenEuropa\pcas\PCas;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for EU Login routes.
 */
class EuLoginController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  protected $pCas;

  /**
   * Constructs the controller object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   */
  public function __construct(DateFormatterInterface $requestStack, PCas $pCas) {
    $this->requestStack = $requestStack;
    $this->pCas = $pCas;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('pcas')
    );
  }

  public function login() {
    // @todo Inject this.
    $request = \Drupal::request();
    $this->pCas->setSession($request->getSession());
    if ($response = $this->pCas->login()) {
      return $response;
    }
    return $this->redirect('<front>');
  }

  /**
   * Builds the response.
   */
  public function logout() {

    $build['content'] = [
      '#type' => 'item',
      '#title' => $this->t('Content'),
      '#markup' => $this->t('Hello world!'),
    ];

    $build['date'] = [
      '#type' => 'item',
      '#title' => $this->t('Date'),
      '#markup' => $this->dateFormatter->format(REQUEST_TIME),
    ];

    return $build;
  }

}
