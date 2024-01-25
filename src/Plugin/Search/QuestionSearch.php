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

use Drupal\kifisearch\Plugin\Search\ContentSearch;
use Drupal\asklib\QuestionIndexer;
use Ehann\RediSearch\Query\BuilderInterface;

/**
 * Search and indexing for asklib_question and asklib_answer entities.
 *
 * @SearchPlugin(
 *   id = "asklib_search",
 *   title = @Translation("Ask a Librarian")
 * )
 */
class QuestionSearch extends ContentSearch {
  public const SEARCH_ID = 'asklib_search';

  /**
   * @param $result Elasticsearch response.
   */
  protected function prepareResults(array $result) {

    $total = $result['total'];
    $rows = $result['hits'];

    $prepared = [];

    $cache = $this->loadMatchedEntities($result);

    \Drupal::service('pager.manager')->createPager($total, 10);

    foreach ($result['hits'] as $hit) {
      $entity_type = $hit['entity_type'];
      $entity_id = $hit['entity_id'];

      if (!isset($cache[$entity_type][$entity_id])) {
        user_error(sprintf('Stale search entry: %s #%d does not exist', $entity_type, $entity_id));
        continue;
      }

      $question = $cache[$entity_type][$entity_id];

      $build = [
        'link' => $question->toUrl('canonical', ['absolute' => TRUE, 'language' => $question->language()])->toString(),
        'asklib_question' => $question,
        'title' => $question->label(),
        'score' => $hit['asklib_score'],
        'date' => $hit['created'],
        'langcode' => $question->language()->getId(),
        'snippet' => $this->processSnippet($hit),
      ];

      $build['extra']['rating'] = [
        '#type' => 'kifiform_stars',
        '#value' => $hit['_source']['fields']['asklib_question']['score'],
      ];

      $prepared[] = $build;

      $this->addCacheableDependency($question);
    }

    return $prepared;
  }

  public function updateIndex() {
    $storage = $this->entityManager->getStorage('asklib_question');
    $batch_size = $this->searchSettings->get('index.cron_limit');
    $indexer = new QuestionIndexer($this->database, $storage, $this->kifi_index, [], $batch_size);
    $indexer->updateIndex();
  }

  public function indexStatus() {
    $storage = $this->entityManager->getStorage('asklib_question');
    $batch_size = $this->searchSettings->get('index.cron_limit');
    $indexer = new QuestionIndexer($this->database, $storage, $this->kifi_index, [], $batch_size);
    return $indexer->indexStatus();
  }

  protected function compileSearchQuery(BuilderInterface &$search_query, $keywords) {

    parent::compileSearchQuery($search_query, $keywords);

    // Apply ordering
    if ($this->getParameter('order') == 'newest')
    {
      $search_query->sortBy('created', 'DESC');
    }

    // Apply channel/feeds filtering
    if (!empty($this->searchParameters['feeds'])) {
      $search_query->tagFilter('terms', Tags::explode($this->searchParameters['feeds']));
    }

  }

  public function searchFormAlter(array &$form, FormStateInterface $form_state) {
    $parameters = $this->getParameters() ?: [];
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    if (isset($parameters['tags']) && $tags = $parameters['tags']) {
      $tags = $this->entityManager->getStorage('taxonomy_term')->loadMultiple(Tags::explode($tags));
    } else {
      $tags = [];
    }

    $form['sorting'] = [
        '#type' => 'container',
        'order' => [
            '#type' => 'radios',
            '#title' => $this->t('Order'),
            '#options' => [
                '' => $this->t('Relevance'),
                'newest' => $this->t('Newest first')
            ],
            '#default_value' => empty($parameters['order']) ? '' : $parameters['order'],
        ]
    ];

    $form['advanced'] = [
      '#type' => 'details',
      '#title' => $this->t('Advanced search'),
      '#open' => count(array_diff(array_keys($parameters), ['page', 'keys'])) > 1,
      'anylang' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Search all languages'),
        '#default_value' => !empty($parameters['anylang'])
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
      $form['anylang'] = $form['advanced']['anylang'];
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

      // Skip unblished channels
      if (!$term->isPublished()) {
        continue;
      }

      $options[$term->id()] = (string)$term->label();
    }

    asort($options);
    return $options;
  }

  public function buildSearchUrlQuery(FormStateInterface $form_state) {
    $query = parent::buildSearchUrlQuery($form_state);

    if ($form_state->getValue('anylang')) {
      $query['anylang'] = '1';
    }

    if ($tags = $form_state->getValue('tags')) {
      $query['tags'] = Tags::implode(array_map(fn($t) => $t['target_id'], $tags));
    }

    if ($feeds = array_filter($form_state->getValue('feeds', []))) {
      $feeds = array_keys(array_filter($feeds));
      $query['feeds'] = Tags::implode($feeds);
    }

    if ($order = $form_state->getValue('order')) {
      $query['order'] = $order;
    }

    return $query;
  }
}
