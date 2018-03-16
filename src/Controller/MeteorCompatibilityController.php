<?php

namespace Drupal\asklib\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class MeteorCompatibilityController extends ControllerBase {
  protected $config;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config->get('asklib.settings');
  }

  public function redirectToQuestion($uuid) {
    $result = \Drupal::entityTypeManager()->getStorage('asklib_question')->loadByproperties([
      'uuid' => strtolower($uuid)
    ]);

    $question = reset($result);

    if ($question && $question->access('view')) {
      header('Location: ' . $question->url());
      exit;
    } else {
      throw new NotFoundHttpException;
    }
  }
}
