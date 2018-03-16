<?php

namespace Drupal\asklib\Plugin\views\row;

use stdClass;
use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\views\Plugin\views\row\RssPluginBase;

/**
 * Format questions as RSS.
 *
 * @ViewsRow(
 *   id = "asklib_question_rss",
 *   title = @Translation("Content"),
 *   help = @Translation("Display the content with standard question view."),
 *   theme = "views_view_row_rss",
 *   register_theme = FALSE,
 *   base = {"asklib_questions"},
 *   display_types = {"feed"}
 * )
 */
class Rss extends RssPluginBase {
  public $base_table = 'asklib_questions';
  public $base_field = 'id';

  // Stores the questions loaded in preRender.
  public $questions = [];
  public $answers = [];

  protected $entityTypeId = 'asklib_question';

  public function buildOptionsForm_summary_options() {
    $options = parent::buildOptionsForm_summary_options();
    $options['title'] = $this->t('Title only');
    $options['default'] = $this->t('Use site default RSS settings');
    return $options;
  }

  public function summaryTitle() {
    $options = $this->buildOptionsForm_summary_options();
    return $options[$this->options['view_mode']];
  }

  public function preRender($values) {
    $qids = [];
    foreach ($values as $row) {
      $qids[] = $row->{$this->field_alias};
    }
    if (!empty($qids)) {
      $this->questions = $this->entityManager->getStorage('asklib_question')->loadMultiple($qids);

      $aids = $this->entityManager->getStorage('asklib_answer')
        ->getQuery()
        ->condition('question', $qids, 'IN')
        ->execute();

      $this->answers = $this->entityManager->getStorage('asklib_answer')->loadMultiple($aids);
    }
  }

  public function render($row) {
    $id = $row->{$this->field_alias};

    if (!is_numeric($id) || !$this->questions[$id]->getAnswer()) {
      return;
    }

    $display_mode = $this->options['view_mode'];

    if ($display_mode == 'default') {
      $display_mode = \Drupal::config('system.rss')->get('items.view_mode');
    }

    $question = $this->questions[$id];
    $answer = $question->getAnswer();
    $library = $answer->getLibrary();

    $question->link = $question->url('canonical', ['absolute' => FALSE]);
    $question->rss_namespaces = [];
    $question->rss_elements = [
      [
        'key' => 'pubDate',
        'value' => gmdate('r', $question->getAnsweredTime())
      ],
      [
        'key' => 'dc:creator',
        'value' => $library ? $library->label() : NULL
      ],
      [
        'key' => 'guid',
        'value' => 'asklib_question#' . $question->id() . ' at ' . 'kirjastot.fi',
        'attributes' => ['isPermaLink' => 'false'],
      ]
    ];

    $build = entity_view($question, $display_mode, $question->language()->getId());
    unset($build['#theme']);

    if (!empty($question->rss_namespaces)) {
      $this->view->style_plugin->namespaces = array_merge($this->view->style_plugin->namespaces, $question->rss_namespaces);
    }

    $item = new stdClass;
    // $item->description = $build;

    $item->description = [
      '#type' => 'processed_text',
      '#format' => $question->getBodyFormat(),
      '#text' => $question->getBody(),
    ];

    $item->title = $question->label();
    $item->link = $question->link;
    $item->elements = &$question->rss_elements;
    $item->id = $question->id();

    $build = [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => $this->options,
      '#row' => $item
    ];

    return $build;
  }
}
