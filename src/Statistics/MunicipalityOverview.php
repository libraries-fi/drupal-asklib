<?php

namespace Drupal\asklib\Statistics;

use Drupal\Core\Database\Query\PagerSelectExtender;
use Drupal\Core\Database\Query\TableSortExtender;
use PDO;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\asklib\QuestionInterface;

class MunicipalityOverview extends StatisticsBase {
  public function getId() {
    return 'asklib_municipality_overview';
  }

  public function alterForm(array &$form, FormStateInterface $form_state) {
    parent::alterForm($form, $form_state);

    $form['options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Search'),
      'name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Name'),
        '#default_value' => $this->parameters['n'] ?? '',
        '#size' => 30,
      ],
    ];
  }

  public function buildUrlQuery(FormStateInterface $form_state) {
    $query = parent::buildUrlQuery($form_state);

    if ($name = $form_state->getValue('name')) {
      $query['n'] = $name;
    }

    return $query;
  }

  public function execute() {
    $view = [
      '#attributes' => [
        'class' => ['asklib-statistics'],
      ],
      '#attached' => [
        'library' => ['asklib/statistics-overview']
      ],
      // 'description' => [
      //   '#plain_text' => $this->t('Questions sorted by questioner\'s municipality.'),
      // ],
      'by_user' => $this->countByMunicipality(),
      'pager' => ['#type' => 'pager']
    ];

    return $view;
  }

  protected function countByMunicipality() {
    $table = [
      '#type' => 'table',
      '#empty' => $this->t('No results'),
      '#header' => [
        ['data' => $this->t('Municipality'), 'field' => 't.name', 'class' => ['title']],
        ['data' => $this->t('Last answer'), 'field' => 'last_answer', 'class' => ['last-answer']],
        ['data' => $this->t('Questions'), 'field' => 'total', 'sort' => 'desc', 'class' => ['total-answers']],
      ],
      '#rows' => [],
      '#caption' => $this->t('Questions sorted by questioner\'s municipality.'),
    ];

    $query = $this->getQuery()
      ->extend(PagerSelectExtender::class)
      ->extend(TableSortExtender::class);
    $query->innerJoin('taxonomy_term_field_data', 't', 'q.municipality = t.tid');
    $query->addExpression('COUNT(*)', 'total');
    $query->addExpression('MAX(a.answered)', 'last_answer');
    $query->fields('t', ['name', 'tid']);
    $query->condition('t.langcode', $this->languages->getCurrentLanguage()->getId());
    $query->groupBy('t.tid');
    $query->groupBy('t.name');
    $query->orderByHeader($table['#header']);
    $query->limit(20);

    if (!empty($this->parameters['n'])) {
      $query->condition('t.name', '%' . $this->parameters['n'] . '%', 'LIKE');
    }

    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);

    foreach ($result as $row) {
      $last_answer = $row['last_answer'] ? $this->dateFormatter->format($row['last_answer'], 'date_only') : NULL;
      $table['#rows'][] = [
        'name' => [
          'data' => [
            '#type' => 'link',
            '#url' => Url::fromRoute('entity.taxonomy_term.canonical', ['taxonomy_term' => $row['tid']]),
            '#title' => $row['name'],
          ]
        ],
        'last_answer' => $last_answer,
        'total' => $row['total'],
      ];
    }

    return $table;
  }
}
