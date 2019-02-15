<?php

namespace Drupal\asklib\Statistics;

use PDO;
use Drupal\Core\Form\FormStateInterface;
use Drupal\asklib\QuestionInterface;

class Overview extends StatisticsBase {
  public function getId() {
    return 'asklib_overview';
  }

  public function execute() {
    $view = [
      '#attributes' => [
        'class' => ['asklib-statistics'],
      ],
      '#attached' => [
        'library' => ['asklib/statistics-overview']
      ],
      'by_answerer' => [
        'data' => $this->countByAnswerer(),
      ],
      'by_archive' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['layout-column', 'layout-column--d']
        ],
        'data' => $this->countByArchive(),
      ],
      'by_delay' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['layout-column', 'layout-column--quadrter']
        ],
        'data' => $this->countByDelay(),
      ],
      // 'by_language' => [
      //   '#type' => 'container',
      //   '#attributes' => [
      //     'class' => ['layout-column', 'layout-column--half']
      //   ],
      //   'data' => $this->countByLanguage()
      // ],
      'by_channel' => [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['layout-column', 'layout-column--quadrter'],
        ],
        'date' => $this->countByChannel(),
      ],
    ];

    // $langtotal = array_reduce($view['by_language']['#rows'], function($total, $row) { return $total + $row['total']; }, 0);
    // $view['by_language']['#footer'] = [[$this->t('Total'), $langtotal]];

    return $view;
  }

  protected function countByAnswerer() {

    $header = [
      'total' => $this->t('Questions'),
      'users' => $this->t('Answerers'),
      'libraries' => $this->t('Answerer libraries'),
    ];

    $query = $this->getQuery();
    $query->condition('q.state', QuestionInterface::STATE_ANSWERED);
    $query->addExpression('COUNT(DISTINCT a.user)', 'users');
    $query->addExpression('COUNT(DISTINCT library)', 'libraries');
    $query->addExpression('COUNT(*)', 'total');
    $result = $query->execute()->fetch(PDO::FETCH_ASSOC);

    $table = [
      '#type' => 'table',
      '#attributes' => [
        'class' => ['table-asklib-statistics-overview'],
      ],
      '#header' => [[
        'data' => $this->t('Overview'),
        'colspan' => 2,
      ]],
      '#rows' => [
        [$this->t('Questions'), $result['total']],
        [],
        [],
        [],
        [$this->t('Answerers'), $result['users']],
        [$this->t('Libraries'), $result['libraries']],
      ],
    ];

    $query = $this->getQuery();
    $query->condition('q.state', QuestionInterface::STATE_ANSWERED);
    $query->fields('q', ['langcode']);
    $query->addExpression('COUNT(*)', 'total');
    $query->groupBy('q.langcode');
    $query->orderBy('total', 'DESC');
    $query->orderBy('q.langcode');
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);

    foreach ($result as $i => &$row) {
      $row['langcode'] = $this->languages->getLanguage($row['langcode'])->getName();

      $table['#rows'][$i+1] = [
        'class' => ['nested-data'],
        'data' => $row
      ];
    }

    return $table;
  }

  protected function countByDelay() {
    $query = $this->getQuery();
    $query->condition('q.state', QuestionInterface::STATE_ANSWERED);
    $query->where('a.answered IS NOT NULL');
    $query->addExpression('GREATEST((DATEDIFF(FROM_UNIXTIME(a.answered), FROM_UNIXTIME(q.created))) -
      ((WEEK(FROM_UNIXTIME(a.answered)) - WEEK(FROM_UNIXTIME(q.created))) * 2) -
      (CASE WHEN WEEKDAY(FROM_UNIXTIME(a.answered)) = 6 THEN 1 ELSE 0 END) -
      (CASE WHEN WEEKDAY(FROM_UNIXTIME(q.created)) = 5 THEN 1 ELSE 0 END), 1)', 'delay');
    $query->addExpression('COUNT(*)', 'total');
    $query->groupBy('delay');
    $query->orderBy('delay');

    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);
    $rows = [];
    $rest = ['delay' => $this->t('@days days', ['@days' => '4+']), 'total' => 0];
    $total = array_reduce($result, function($total, $row) { return $total + $row['total']; }, 0);

    foreach ($result as $row) {
      if ($row['delay'] <= 3) {
        $row['delay'] = $this->formatPlural($row['delay'], '1 day', '@days days', ['@days' => $row['delay']]);
        $row['total'] .= sprintf(' (%d %%)', ($row['total'] / $total) * 100);
        $rows[] = $row;
      } else {
        $rest['total'] += $row['total'];
      }
    }

    if ($rest['total']) {
      $rest['total'] .= sprintf(' (%d %%)', ($rest['total'] / $total) * 100);;
      $rows[] = $rest;
    }

    $table = [
      '#type' => 'table',
      '#caption' => $this->t('Response time'),
      '#header' => [$this->t('Time for response'), $this->t('Total')],
      '#rows' => $rows,
    ];

    return $table;
  }

  protected function countByLanguage() {
    $query = $this->getQuery();
    $query->condition('q.state', QuestionInterface::STATE_ANSWERED);
    $query->fields('q', ['langcode']);
    $query->addExpression('COUNT(*)', 'total');
    $query->groupBy('q.langcode');
    $query->orderBy('total', 'DESC');
    $query->orderBy('q.langcode');
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);

    foreach ($result as &$row) {
      $row['langcode'] = $this->languages->getLanguage($row['langcode'])->getName();
    }

    $table = [
      '#type' => 'table',
      '#caption' => $this->t('Questions by language'),
      '#header' => [$this->t('Language'), $this->t('Total')],
      '#rows' => $result,
    ];

    return $table;
  }

  protected function countByArchive() {
    $query = $this->getQuery();
    $query->condition('q.state', QuestionInterface::STATE_ANSWERED);
    $query->fields('q', ['published']);
    $query->addExpression('COUNT(*)', 'total');
    $query->groupBy('q.published');
    $query->orderBy('total', 'DESC');
    $query->orderBy('q.published', 'DESC');
    $result = $query->execute()->fetchAll(PDO::FETCH_ASSOC);

    foreach ($result as $i => $row) {
      $result[$i]['published'] = $row['published'] ? $this->t('Public') : $this->t('Closed');
    }

    $table = [
      '#type' => 'table',
      '#caption' => $this->t('Questions by status'),
      '#header' => [$this->t('Archive'), $this->t('Total')],
      '#rows' => $result,
    ];

    return $table;
  }

  protected function countByChannel() {
    $query = $this->getQuery();
    $query->fields('q', ['channel']);
    $query->addExpression('COUNT(*)', 'total');
    $query->groupBy('q.channel');

    $channels = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid' => 'asklib_channels']);

    $result = $query->execute()->fetchAll(PDO::FETCH_UNIQUE);

    $data = array_map(function($c) use ($result) {
      return [
        'name' => $c->label(),
        'total' => isset($result[$c->id()]) ? $result[$c->id()]->total : 0,
      ];
    }, $channels);

    usort($data, function($a, $b) {
      return strcasecmp($a['name'], $b['name']);
    });

    $table = [
      '#type' => 'table',
      '#caption' => $this->t('Questions by channel'),
      '#header' => [$this->t('Channel'), $this->t('Total')],
      '#rows' => $data,
    ];

    return $table;
  }
}
