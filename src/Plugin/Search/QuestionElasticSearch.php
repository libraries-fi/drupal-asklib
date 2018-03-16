<?php

namespace Drupal\asklib\Plugin\Search;

use DateTime;
use InvalidArgumentException;
use Drupal\Component\Utility\Tags;
use Drupal\Core\Access\AccessibleInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\Config;
use Drupal\Core\Database\Connection;
use Drupal\Core\DateTime\DateFormatterInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\asklib\AnswerInterface;
use Drupal\asklib\QuestionInterface;
use Drupal\search\Plugin\SearchIndexingInterface;
use Drupal\search\Plugin\SearchPluginBase;
use Elasticsearch\Common\Exceptions\BadRequest400Exception;
use Elasticsearch\Common\Exceptions\NoNodesAvailableException;
use Html2Text\Html2Text;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Search and indexing for asklib_question and asklib_answer entities.
 *
 * @SearchPlugin(
 *   id = "asklib_search_elastic",
 *   title = @Translation("Ask a Librarian")
 * )
 */
class QuestionElasticSearch extends SearchPluginBase implements AccessibleInterface, SearchIndexingInterface {
  protected $entityManager;
  protected $languageManager;
  protected $fieldManager;
  protected $dates;
  protected $database;
  protected $searchSettings;
  protected $state;
  protected $renderer;
  protected $client;

  static public function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('language_manager'),
      $container->get('date.formatter'),
      $container->get('state'),
      $container->get('database'),
      $container->get('renderer'),
      $container->get('config.factory')->get('search.settings')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_manager, EntityFieldManagerInterface $field_manager, LanguageManagerInterface $languages, DateFormatterInterface $date_formatter, StateInterface $state, Connection $database, RendererInterface $renderer, Config $search_settings) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityManager = $entity_manager;
    $this->fieldManager = $field_manager;
    $this->languageManager = $languages;
    $this->dates = $date_formatter;
    $this->state = $state;
    $this->database = $database;
    $this->renderer = $renderer;
    $this->searchSettings = $search_settings;

    $this->client = \Elasticsearch\ClientBuilder::create()->build();
  }

  public function access($operation, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = AccessResult::allowedIfHasPermission($account, 'access content');
    return $return_as_object ? $result : $result->isAllowed();
  }

  public function execute() {
    try {
      if ($this->isSearchExecutable() && $results = $this->findResults()) {
        return $this->prepareResults($results);
      }
    } catch (BadRequest400Exception $error) {
      drupal_set_message(t('Query contained errors.'), 'error');
    } catch (NoNodesAvailableException $error) {
      drupal_set_message(t('Could not connect to database'), 'error');
    }
    return [];
  }

  protected function findResults() {
    $query = $this->compileSearchQuery($this->keywords);

    $parameters = $this->getParameters();
    $skip = empty($parameters['page']) ? 0 : $parameters['page'] * 10;

    $result = $this->client->search([
      'index' => 'kirjastot_fi',
      'type' => 'asklib_question',
      'body' => $query,
      'from' => $skip,
    ]);

    return $result;
  }

  /**
   * @param $result Elasticsearch response.
   */
  protected function prepareResults(array $result) {
    $total = $result['hits']['total'];
    $time = $result['took'];
    $rows = $result['hits']['hits'];

    $ids = array_map(function($row) { return $row['_source']['id']; }, $rows);
    $questions = $this->entityManager->getStorage('asklib_question')->loadMultiple($ids);
    $prepared = [];

    pager_default_initialize($total, 10);

    foreach ($result['hits']['hits'] as $item) {
      if (!isset($questions[$item['_source']['id']])) {
        user_error(sprintf('Indexed question #%d does not exist', $data['id']));
        continue;
      }

      $data = $item['_source'];
      $question = $questions[$data['id']];

      $build = [
        'link' => $question->url('canonical', ['absolute' => TRUE, 'language' => $question->language()]),
        'asklib_question' => $question,
        'title' => $question->label(),
        'score' => $item['_score'],
        'date' => $item['_source']['created'],

        'langcode' => $question->language()->getId(),

        'snippet' => search_excerpt($this->keywords, implode(' ', [$data['body'], $data['answer']]), $data['langcode']),
      ];

      $prepared[] = $build;

      $this->addCacheableDependency($question);
    }

    return $prepared;
  }

  public function updateIndex() {
    foreach ($this->fetchItemsForIndexing() as $item) {
      $langcode = $item->question->language()->getId();
      $tags = [];

      foreach ($item->question->getTags() as $tag) {
        if ($tag->hasTranslation($langcode)) {
          $tags[(int)$tag->id()] = $tag->getTranslation($langcode)->label();
        } else {
          // All terms should have a value for the language of the question!
          trigger_error(sprintf('Term %d does not have translation for language \'%s\'', $tag->id(), $langcode));
        }
      }

      $library = $item->question->getTargetLibrary();
      $channel = $item->question->getChannel();

      $document = [
        'id' => (int)$item->question->id(),
        'langcode' => $langcode,
        'title' => $item->question->label(),
        'body' => (new Html2Text($item->question->getBody()))->getText(),
        'answer' => (new Html2Text($item->answer->getBody()))->getText(),
        'score' => (int)$item->answer->getRating(),
        'tags' => array_values($tags),
        'created' => (int)$item->question->getCreatedTime(),
        'changed' => (int)$item->answer->getChangedTime(),
        'meta' => [
          'channel_id' => $channel ?(int) $channel->id() : NULL,
          'tag_ids' => array_keys($tags),
        ]
      ];

      $this->client->index([
        'index' => 'kirjastot_fi',
        'type' => 'asklib_question',
        'id' => sprintf('%d::%s', $item->question->id(), $langcode),
        'body' => $document,
      ]);

      // Rely on Drupal's internal index to keep track of indexed items.
      search_index($this->getPluginId(), $item->question->id(), $langcode, '');
    }
  }

  protected function fetchItemsForIndexing() {
    $limit = $this->searchSettings->get('index.cron_limit');
    $last_run = $this->state->get('system.cron_last');

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
    $query->condition($query->orConditionGroup()
      ->where('s.sid IS NULL')
      ->condition('s.reindex', 0, '<>')
      ->condition('q.changed', $last_run, '>')
    );
    $query->orderBy('ex', 'DESC')
      ->orderBy('ex2')
      ->orderBy('q.id')
      ->groupBy('q.id')
      ->groupBy('q.answer')
      ->range(0, $limit);

    $result = $query->execute()->fetchAll();
    // $qids = array_column($result, 'id');
    // $aids = array_column($result, 'answer');

    /*
     * NOTE: Query result is an array of objects; PHP 5.6 does not allow to use
     * array_column() with objects.
     */
    $qids = array_map(function($row) { return $row->id; }, $result);
    $aids = array_map(function($row) { return $row->answer; }, $result);

    if (!empty($result)) {
      $questions = $this->entityManager->getStorage('asklib_question')->loadMultiple($qids);
      $answers = $this->entityManager->getStorage('asklib_answer')->loadMultiple($aids);

      foreach ($questions as $question) {
        $aid = $question->get('answer')->target_id;

        yield (object)[
          'question' => $question,
          'answer' => $answers[$aid],
        ];
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
    search_mark_for_reindex($this->getPluginId());
  }

  public function indexClear() {
    search_index_clear($this->getPluginId());
  }

  protected function indexQuestion(QuestionInterface $question, AnswerInterface $answer) {
    $builder = $this->entityManager->getViewBuilder('asklib_question');

    foreach ($question->getTranslationLanguages() as $language) {
      search_index($this->getPluginId(), $question->id(), $language->getId(), '');
    }
  }

  protected function compileSearchQuery($query_string) {
    /*
     * Elasticsearch will throw an exception when the syntax is invalid, so we
     * do a simple sanity check here.
     */
    $query_string = preg_replace('/^(AND|OR|NOT)/', '', trim($query_string));
    $query_string = preg_replace('/(AND|OR|NOT)$/', '', trim($query_string));

    if (empty($this->searchParameters['all_languages'])) {
      $langcode = $this->languageManager->getCurrentLanguage()->getId();
    } else {
      $langcode = NULL;
    }

    $query = [
      'bool' => [
        // 'must' => [],
        // 'should' => [],
      ]
    ];

    if ($query_string) {
      $query['bool']['should'][] = [
        'query_string' => [
          'query' => $query_string,
          'fields' => ['body', 'answer'],
          'default_operator' => 'AND',
          'boost' => 10,
          // 'fuzziness' => 2,
          // 'analyzer' => 'snowball',
        ]
      ];

      $query['bool']['must'][] = [
        'query_string' => [
          'query' => $query_string,
          'fields' => ['body', 'answer', 'title', 'tags'],
          // 'fuzziness' => 2,
          // 'analyzer' => 'finnish',
          // 'boost' => 10,
        ]
      ];
    }

    if ($langcode) {
      // $query['bool']['should'][] = [
      //   'term' => ['langcode' => [
      //     'value' => $langcode,
      //     'boost' => 100,
      //   ]],
      // ];
      $query['bool']['must'][] = [
        'term' => ['langcode' => [
          'value' => $langcode,
        ]],
      ];
    }

    if (!empty($this->searchParameters['feeds'])) {
      foreach (Tags::explode($this->searchParameters['feeds']) as $fid) {
        $query['bool']['must'][] = [
          // Use the singular 'term' query to require every single term in the result.
          'term' => [
            'meta.channel_id' => $fid
          ]
        ];
      }
    }

    if (!empty($this->searchParameters['tags'])) {
      foreach (Tags::explode($this->searchParameters['tags']) as $tid) {
        $query['bool']['must'][] = [
          // Use the singular 'term' query to require every single term in the result.
          'term' => [
            'meta.tag_ids' => (int)$tid
          ]
        ];
      }
      // $query['bool']['must'][] = [[
      //   'terms' => [
      //     'meta.tag_ids' => Tags::explode($this->searchParameters['tags']),
      //   ]
      // ]];
    }

    return [
      'query' => $query,
      'highlight' => ['fields' => ['body' => (object)[], 'answer' => (object)[]]]
    ];
  }

  public function isSearchExecutable() {
    $params = array_filter($this->searchParameters);
    unset($params['all_languages']);
    return !empty($params);
  }

  public function searchFormAlter(array &$form, FormStateInterface $form_state) {
    $parameters = $this->getParameters() ?: [];
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    if (isset($parameters['tags']) && $tags = $parameters['tags']) {
      $tags = $this->entityManager->getStorage('taxonomy_term')->loadMultiple(Tags::explode($tags));
    } else {
      $tags = [];
    }

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => t('Advanced search'),
      '#open' => count(array_diff(array_keys($parameters), ['page', 'keys'])) > 1,
      'all_languages' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Search all languages'),
        '#default_value' => !empty($parameters['all_languages'])
      ],
      'tags_container' => [
        /*
         * NOTE: Hide until Drupal core fixes issues with term translations not filtered properly
         * in entity queries.
         */
        '#access' => FALSE,

        '#type' => 'fieldset',
        '#title' => $this->t('Tags'),
        'tags' => [
          '#type' => 'entity_autocomplete',
          '#title' => $this->t('Keywords'),
          '#description' => $this->t('Enter a comma-separated list. For example: Amsterdam, Mexico City, "Cleveland, Ohio"'),
          '#default_value' => $tags,

          '#target_type' => 'taxonomy_term',
          '#tags' => TRUE,
          '#selection_settings' => [
            'target_bundles' => [
              'asklib_tags' => 'asklib_tags',
              'finto' => 'finto',
            ]
          ]
        ]
      ],
      'feeds_container' => [
        // '#type' => 'fieldset',
        // '#title' => $this->t('Channels'),
        'feeds' => [
          '#type' => 'checkboxes',
          '#title' => $this->t('Channels'),
          // '#description' => $this->t('Search for questions that are published in selected RSS feeds.'),
          '#options' => $this->getFeedOptions(),
          '#empty_option' => $this->t('- Any -'),
          '#default_value' => isset($parameters['feeds']) ? Tags::explode($parameters['feeds']) : [],
        ]
      ]
    ];

    $form['advanced']['action'] = [
      '#type' => 'container',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Advanced search'),
      ]
    ];

    if ($langcode != 'fi') {
      $form['all_languages'] = $form['advanced']['all_languages'];
      unset($form['advanced']);
    }
  }

  protected function getFeedOptions() {
    $terms = $this->entityManager->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'asklib_channels'
    ]);

    $options = [];

    foreach ($terms as $term) {
      // Children channel is abandoned so hide it.
      if ($term->getTranslation('fi')->label() == 'Lapset') {
        continue;
      }

      $options[$term->id()] = (string)$term->label();
    }

    asort($options);
    return $options;
  }

  public function buildSearchUrlQuery(FormStateInterface $form_state) {
    $query = parent::buildSearchUrlQuery($form_state);

    if ($form_state->getValue('all_languages')) {
      $query['all_languages'] = '1';
    }

    if ($tags = $form_state->getValue('tags')) {
      $query['tags'] = Tags::implode(array_map(function($t) { return $t['target_id']; }, $tags));
    }

    if ($feeds = array_filter($form_state->getValue('feeds', []))) {
      $feeds = array_keys(array_filter($feeds));
      $query['feeds'] = Tags::implode($feeds);
    }

    return $query;
  }
}
