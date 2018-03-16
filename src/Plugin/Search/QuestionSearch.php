<?php

namespace Drupal\asklib\Plugin\Search;

use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\Config;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\asklib\AnswerInterface;
use Drupal\asklib\QuestionInterface;
use Drupal\search\Plugin\SearchIndexingInterface;
use Drupal\search\Plugin\SearchPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Search and indexing for asklib_question and asklib_answer entities.
 *
 * @SearchPlugin(
 *   id = "asklib_search",
 *   title = @Translation("Ask a Librarian (legacy)")
 * )
 */
class QuestionSearch extends SearchPluginBase implements AccessibleInterface, SearchIndexingInterface {
  protected $entityManager;
  protected $database;
  protected $searchSettings;

  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('database'),
      $container->get('renderer'),
      $container->get('config.factory')->get('search.settings')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_manager, Connection $database, RendererInterface $renderer, Config $search_settings) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->database = $database;
    $this->renderer = $renderer;
    $this->searchSettings = $search_settings;
  }

  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIfHasPermission($account, 'access content');
    return $return_as_object ? $result : $result->isAllowed();
  }

  public function execute() {
    // var_dump('exec');

  }




  public function updateIndex() {
    $limit = $this->searchSettings->get('index.cron_limit');

    /*
     * NOTE: Node search uses 'replica' connection instead of 'default'.
     */
    $query = $this->database->select('asklib_questions', 'q');
    $query->leftJoin('search_dataset', 's', 's.sid = q.id AND s.type = :type', [':type' => $this->getPluginId()]);
    $query->fields('q', ['id', 'answer']);
    $query->addExpression('CASE MAX(s.reindex) WHEN NULL THEN 0 ELSE 1 END', 'ex');
    $query->addExpression('MAX(s.reindex)', 'ex2');
    $query->condition('q.published', TRUE);
    $query->condition('q.state', QuestionInterface::STATE_ANSWERED);
    $query->condition('q.answer', NULL, 'IS NOT');
    $query->condition($query->orConditionGroup()->where('s.sid IS NULL')->condition('s.reindex', 0, '<>'));
    $query->orderBy('ex', 'DESC')
      ->orderBy('ex2')
      ->orderBy('q.id')
      ->groupBy('q.id')
      ->groupBy('q.answer')
      ->range(0, $limit);

    $result = $query->execute()->fetchAll();
    $qids = array_column($result, 'id');
    $aids = array_column($result, 'answer');

    if (!empty($result)) {
      $questions = $this->entityManager->getStorage('asklib_question')->loadMultiple($qids);
      $answers = $this->entityManager->getStorage('asklib_answer')->loadMultiple($aids);

      foreach ($questions as $question) {
        $aid = $question->get('answer')->target_id;
        $this->indexQuestion($question, $answers[$aid]);
      }
    }
  }

  public function indexStatus() {
    $total = $this->database->query('
      SELECT COUNT(*)
      FROM {asklib_questions} q
      WHERE q.answer IS NOT NULL AND q.published = 1 AND q.state = :state
    ', [':state' => QuestionInterface::STATE_ANSWERED])->fetchField();

    $ready = $this->database->query('
      SELECT COUNT(DISTINCT s.sid)
      FROM search_dataset s
      WHERE s.type = :type
    ', [':type' => $this->getPluginId()])->fetchField();

    return ['remaining' => $total - $ready, 'total' => $total];
  }

  public function markForReindex() {

  }

  public function indexClear() {

  }

  protected function indexQuestion(QuestionInterface $question, AnswerInterface $answer) {
    $builder = $this->entityManager->getViewBuilder('asklib_question');

    foreach ($question->getTranslationLanguages() as $language) {
      $build = $builder->view($question, 'search_index', $language->getId());
      $build['search_title'] = [
        '#plain_text' => $question->label(),
        '#prefix' => '<h1>',
        '#suffix' => '</h1>',
        '#weight' => -1000,
      ];

      $output = $this->renderer->renderPlain($build);

      search_index($this->getPluginId(), $question->id(), $language->getId(), $output);
    }
  }
}
