<?php

namespace Drupal\asklib\Statistics;

use PDO;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\asklib\QuestionInterface;

class PersonOverview extends StatisticsBase {
  public function getId() {
    return 'asklib_person_overview';
  }

  public function alterForm(array &$form, FormStateInterface $form_state) {
    parent::alterForm($form, $form_state);

    $form['options'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Search'),
      'name' => [
        '#type' => 'textfield',
        '#title' => $this->t('User name'),
        '#default_value' => isset($this->parameters['n']) ? $this->parameters['n'] : '',
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
      'by_user' => $this->countByUser(),
      'pager' => [
        '#type' => 'pager'
      ]
    ];

    return $view;
  }

  protected function countByUser() {
    $table = [
      '#type' => 'table',
      '#empty' => $this->t('No results'),
      '#header' => [
        ['data' => $this->t('Username'), 'field' => 'u.name', 'class' => ['title']],
        ['data' => $this->t('Last answer'), 'field' => 'last_answer', 'class' => ['last-answer']],
        ['data' => $this->t('Answers'), 'field' => 'total', 'sort' => 'desc', 'class' => ['total-answers']],
      ],
      '#rows' => [],
    ];

    $query = $this->getQuery()
      ->extend('Drupal\Core\Database\Query\PagerSelectExtender')
      ->extend('Drupal\Core\Database\Query\TableSortExtender');
    $query->innerJoin('users_field_data', 'u', 'a.user = u.uid');
    $query->addExpression('COUNT(*)', 'total');
    $query->addExpression('MAX(a.answered)', 'last_answer');
    $query->fields('u', ['name', 'uid']);
    $query->groupBy('u.uid');
    $query->groupBy('u.name');
    $query->orderByHeader($table['#header']);
    $query->limit(20);

    if (!empty($this->parameters['n'])) {
      $query->condition('u.name', '%' . $this->parameters['n'] . '%', 'LIKE');
    }

    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);

    foreach ($result as $row) {
      $last_answer = $row['last_answer'] ? $this->dateFormatter->format($row['last_answer'], 'date_only') : NULL;
      $table['#rows'][] = [
        'name' => [
          'data' => [
            '#type' => 'link',
            '#url' => Url::fromRoute('entity.user.canonical', ['user' => $row['uid']]),
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
