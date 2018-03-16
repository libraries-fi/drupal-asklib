<?php

namespace Drupal\asklib\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a 'New forum topics' block.
 *
 * @Block(
 *   id = "asklib_forum_new_block",
 *   admin_label = @Translation("New forum topics"),
 *   category = @Translation("Ask a Librarian")
 * )
 */
class NewForumMessages extends BlockBase implements ContainerFactoryPluginInterface {
  protected $configFactory;
  protected $moduleHandler;
  protected $nodeStorage;
  protected $userStorage;
  protected $termStorage;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('config.factory')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_manager, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;

    $this->termStorage = $entity_manager->getStorage('taxonomy_term');
    $this->nodeStorage = $entity_manager->getStorage('node');
    $this->userStorage = $entity_manager->getStorage('user');

    // NOTE: Base constructor requires deps injected above!
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public function build() {
    if (!$this->forum()) {
      return;
    }

    $items = [];
    $cache = [];
    $basedir = \Drupal::moduleHandler()->getModule('asklib')->getPath();

    foreach ($this->content() as $delta => $item) {
      $user_url = $this->userStorage->create(['uid' => $item->user->id])->urlInfo();
      $post_url = $this->nodeStorage->create(['nid' => $item->nid, 'type' => 'forum'])->urlInfo();

      if ($item->type == 'comment') {
        $icon = 'icon-comment.svg';
      } else {
        $icon = 'icon-thread.svg';
      }

      $icon_src = Url::fromUserInput(sprintf('/%s/public/images/%s', $basedir, $icon))->toString();
      $body = $item->body->value;
      $format = $item->body->format;
      $summary = text_summary(check_markup($body, $format, $item->langcode), $format, 120);

      $items[$delta] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['post-summary'],
        ],
        'header' => [
          '#type' => 'container',
          '#attributes' => [
            'class' => ['post-summary-header'],
          ],
          'icon' => [
            '#type' => 'html_tag',
            '#tag' => 'img',
            '#attributes' => [
              'title' => $this->t('New %type', ['%type' => $item->type]),
              'src' => $icon_src,
            ]
          ],
          'title' => [
            '#type' => 'link',
            '#title' => $item->title,
            '#url' => $post_url,
          ],
        ],
        'user' => [
          '#suffix' => ': ',
          '#type' => 'link',
          '#title' => $item->user->name,
          '#url' => $user_url,
        ],
        'body' => [
          '#markup' => $summary,
        ]
      ];
    }

    return [
      'link' => [
        '#prefix' => '» ',
        '#type' => 'link',
        '#title' => $this->t('View the forum'),
        '#url' => new Url('forum.page', ['taxonomy_term' => $this->forum()]),
      ],
      'items' => $items,
      '#attached' => [
        'library' => ['asklib/block-new-forum-posts']
      ],
      '#cache' => [
        // 'max-age' => 0,
        'tags' => ['node_list', 'comment_list'],
      ],
    ];
  }

  /**
   * Copied from ForumBlockBase.
   *
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user.node_grants:view']);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'properties' => [
        'administrative' => TRUE,
      ],
      'block_count' => 5,
      'forums' => [$this->forum()]
    ];
  }

  protected function content() {
    $topics = $this->newTopics();
    $comments = $this->newComments();
    $content = $this->mergeItems($topics, $comments);

    return $content;
  }

  protected function newTopics() {
    $query = db_select('forum_index', 'f')
      ->fields('f')
      ->fields('b', ['body_value', 'body_format'])
      ->fields('n', ['langcode'])
      ->fields('u', ['uid', 'name'])
      ->condition('tid', $this->configuration['forums'], 'IN')
      ->addTag('node_access')
      ->addMetaData('base_table', 'forum_index')
      ->orderBy('f.created', 'DESC')
      ->range(0, $this->configuration['block_count']);

    $query->join('node_field_data', 'n', 'f.nid = n.nid');
    $query->join('node__body', 'b', 'b.entity_id = f.nid');
    $query->join('users_field_data', 'u', 'n.uid = u.uid');

    $result = $query->execute();
    $items = [];

    foreach ($result as $item) {
      // var_dump($item);
      $items[] = (object)[
        'type' => 'topic',
        'nid' => (int)$item->nid,
        'title' => $item->title,
        'created' => (int)$item->created,
        'langcode' => $item->langcode,
        'user' => (object)[
          'id' => (int)$item->uid,
          'name' => $item->name,
        ],
        'body' => (object)[
          'value' => $item->body_value,
          'format' => $item->body_format,
        ]
      ];
    }

    return $items;
  }

  protected function newComments() {
    $query = db_select('comment_field_data', 'c')
      ->fields('c')
      ->fields('b', ['comment_body_value', 'comment_body_format'])
      ->fields('f', ['title'])
      ->fields('u', ['name'])
      ->condition('c.comment_type', 'comment_forum')
      ->condition('f.tid', $this->configuration['forums'], 'IN')
      ->orderBy('c.created', 'DESC')
      ->range(0, $this->configuration['block_count']);

    $query->join('forum_index', 'f', 'f.nid = c.entity_id');
    $query->join('comment__comment_body', 'b', 'b.entity_id = c.cid');
    $query->join('users_field_data', 'u', 'c.uid = u.uid');

    $result = $query->execute();
    $items = [];

    foreach ($result as $item) {
      $items[] = (object)[
        'type' => 'comment',
        'cid' => (int)$item->cid,
        'nid' => (int)$item->entity_id,
        'title' => $item->title,
        'comment_title' => $item->subject,
        'created' => (int)$item->created,
        'langcode' => $item->langcode,
        'user' => (object)[
          'id' => (int)$item->uid,
          'name' => $item->name,
        ],
        'body' => (object)[
          'value' => $item->comment_body_value,
          'format' => $item->comment_body_format,
        ]
      ];
    }

    return $items;
  }

  protected function forum() {
    return $this->configFactory->get('asklib.settings')->get('forum');
  }

  protected function mergeItems(array $topics, array $comments) {
    $items = array_merge($topics, $comments);

    usort($items, function($a, $b) {
      return $b->created - $a->created;
    });

    return array_slice($items, 0, $this->configuration['block_count']);
  }
}
