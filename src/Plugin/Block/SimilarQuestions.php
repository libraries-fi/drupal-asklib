<?php

namespace Drupal\asklib\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Url;
use Drupal\asklib\QuestionInterface;
use Drupal\asklib\QuestionStorage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Display questions similar to the selected one.
 *
 * @Block(
 *   id = "asklib_similar_block",
 *   admin_label = @Translation("Similar questions"),
 *   category = @Translation("Ask a Librarian")
 * )
 */
class SimilarQuestions extends BlockBase implements ContainerFactoryPluginInterface {
  protected $storage;
  protected $routeMatch;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('asklib_question'),
      $container->get('current_route_match')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, QuestionStorage $storage, RouteMatchInterface $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->storage = $storage;
    $this->routeMatch = $route_match;
  }

  public function build() {
    $elements = [];

    foreach ($this->findSimilarQuestions() as $delta => $question) {
      $elements[$delta] = [
        '#prefix' => '<li>',
        '#suffix' => '</li>',
        'link' => [
          '#type' => 'link',
          '#title' => $question->label(),
          '#url' => $question->urlInfo(),
        ]
      ];
    }

    if (!empty($elements)) {
      $elements += [
        '#prefix' => '<ul>',
        '#suffix' => '</ul>',
      ];
    }

    return $elements;
  }

  protected function findSimilarQuestions() {
    $question = $this->routeMatch->getParameter('asklib_question');

    if ($question) {
      return $this->storage->findSimilarQuestions($question, $this->configuration['block_count']);
    } else {
      return [];
    }
  }

  /**
   * Copied from ForumBlockBase.
   *
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route.asklib_question', 'languages']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'block_count' => 10,
    ];
  }
}
