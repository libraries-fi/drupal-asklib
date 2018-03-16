<?php

namespace Drupal\asklib\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AdminController extends ControllerBase {
  protected $config;

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory')
    );
  }

  public function __construct(ConfigFactoryInterface $config) {
    $this->config = $config->get('asklib.settings');
  }

  public function index() {
    $help = check_markup($this->config->get('help'), $this->config->get('help_format'));

    if (isset($_GET['create_index'])) {
      $query = \Drupal\Core\Database\Database::getConnection()->select('asklib_questions', 'a')
        ->distinct()
        ->fields('a', ['id'])
        ->condition('published', 1)
        ->condition('state', 2)
        ->condition('i.qid', NULL, 'IS')
        ->orderBy('a.id')
        ->range(0, 1000);


      $query->join('asklib_question__tags', 't', 'a.id = t.entity_id');
      $query->leftJoin('asklib_question_index', 'i', 'a.id = i.qid');

      $result = $query->execute();
      $qids = $result->fetchAll(\PDO::FETCH_COLUMN);

      if ($qids) {
        $entities = \Drupal::entityTypeManager()->getStorage('asklib_question')->loadMultiple($qids);

        foreach ($entities as $question) {
          $question->save();
        }

        header('Location: /admin/asklib');
        exit;
      }
    }

    return [
      '#title' => t('Librarian front page'),
      'container' => [
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Information regarding Ask a Librarian'),
          '#attributes' => [
            'class' => ['panel__title'],
          ]
        ],
        'help' => [
          '#markup' => $help,
        ]
      ]
    ];
  }
}
