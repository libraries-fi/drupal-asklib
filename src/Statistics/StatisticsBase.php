<?php

namespace Drupal\asklib\Statistics;

use Drupal\Core\Database\Connection;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\kifistats\StatisticsBase as KifiStatisticsBase;

/**
 * Base class for asklib statistics.
 */
abstract class StatisticsBase extends KifiStatisticsBase {
  protected $db;
  protected $languages;
  protected $dateFormatter;

  public function __construct(Connection $database, LanguageManagerInterface $languages, DateFormatterInterface $date_formatter) {
    $this->db = $database;
    $this->languages = $languages;
    $this->dateFormatter = $date_formatter;
  }

  public function getTitle() {
    return $this->t('Statistics');
  }

  public function alterForm(array &$form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'asklib/statistics-form';

    $form['year_wrapper'] = [
      '#type' => 'fieldset',
      '#type' => 'fieldset',
      '#title' => $this->t('Select year'),
      'year' => [
        '#type' => 'select',
        '#title' => $this->t('Year'),
        '#options' => array_combine(range(date('Y'), 2000), range(date('Y'), 2000)),
        '#empty_option' => $this->t('- Any -'),
        '#default_value' => $this->getParameter('year'),
      ]
    ];

    $form['date_range'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Date range'),
      '#attributes' => [
        'class' => ['form--inline']
      ],
      'date_from' => [
        '#type' => 'date',
        '#title' => $this->t('First date'),
        '#default_value' => $this->getParameter('df'),
      ],
      'date_to' => [
        '#type' => 'date',
        '#title' => $this->t('Last date'),
        '#default_value' => $this->getParameter('dt'),
      ],
    ];
    return $form;
  }

  public function buildUrlQuery(FormStateInterface $form_state) {
    $query = [];
     if (($from = $form_state->getValue('date_from')) && ($to = $form_state->getValue('date_to'))) {
      $query['df'] = $from;
      $query['dt'] = $to;
    } else if ($year = $form_state->getValue('year')) {
      $query['year'] = $year;
    }

    return $query;
  }

  /**
   * Construct base SQL query.
   */
  protected function getQuery() {
    $values = $this->parameters;

    if (!empty($values['year'])) {
      $values['df'] = sprintf('%d-01-01', $values['year']);
      $values['dt'] = sprintf('%d-12-31', $values['year']);
    }

    $query = $this->db->select('asklib_questions', 'q');
    $query->innerJoin('asklib_answers', 'a', 'q.answer = a.id');

    if (isset($values['df'], $values['dt'])) {
      $query->where('DATE(FROM_UNIXTIME(a.answered)) BETWEEN :date_from AND :date_to', [
        'date_from' => $values['df'],
        'date_to' => $values['dt']
      ]);
    }

    return $query;
  }
}
