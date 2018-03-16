<?php

namespace Drupal\asklib\Controller;

use Drupal\asklib\QuestionInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

class KeywordIndexController extends ControllerBase {
  protected $db;
  protected $language_manager;

  public static function create(ContainerInterface $container) {
    return new static($container->get('database'), $container->get('language_manager'));
  }

  public function __construct(Connection $database, LanguageManagerInterface $language_manager) {
    $this->db = $database;
    $this->languageManager = $language_manager;
  }

  public function index() {
    $langcode = $this->langcode();

    $query = $this->db->select('taxonomy_term_field_data', 't')
      ->distinct()
      ->condition('t.vid', ['asklib_tags', 'finto'], 'IN')
      ->condition('t.langcode', $langcode)
      ->condition('q.langcode', $langcode)
      ->condition('q.state', QuestionInterface::STATE_ANSWERED)
      ->condition('q.published', 1)
      ->groupBy('letter')
      ->orderBy('letter');

    $query->innerJoin('asklib_question__tags', 'x', 'x.tags_target_id = t.tid');
    $query->innerJoin('asklib_questions', 'q', 'q.id = x.entity_id');
    $query->addExpression('LOWER(SUBSTRING(t.name, 1, 1))', 'letter');
    $query->addExpression('COUNT(DISTINCT t.tid)', 'total');
    $query->addExpression('MIN(t.name)', 'first_word');
    $query->addExpression('MAX(t.name)', 'last_word');

    $result = $query->execute()->fetchAll();
    $items = [
      'misc' => (object)[
        'letter' => '0-9',
        'total' => 0,
        'first_word' => null,
        'last_word' => null,
      ]
    ];

    foreach ($result as $row) {
      $code = ord($row->letter);
      $special = [ord('å'), ord('ä'), ord('ö')];
      if (($code >= 97 && $code <= 122) || in_array($code, $special)) {
        $items[$row->letter] = $row;
      } else {
        $items['misc']->total += $row->total;
        $items['misc']->last_word = $row->last_word;

        if (is_null($items['misc']->first_word)) {
          $items['misc']->first_word = $row->first_word;
        }
      }
    }

    return [
      '#theme' => 'asklib_keyword_index',
      '#items' => $items,
      '#langcode' => $langcode,
      '#cache' => [
        'contexts' => ['languages:language_content']
      ]
    ];

    // $result =
    exit('INDEX');
  }

  public function letter($letter) {
    $langcode = $this->langcode();

    $query = $this->db->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid', 'name'])
      ->condition('t.vid', ['asklib_tags', 'finto'], 'IN')
      ->condition('t.langcode', $langcode)
      ->condition('q.langcode', $langcode)
      ->condition('q.state', QuestionInterface::STATE_ANSWERED)
      ->condition('q.published', 1)
      ->where('SUBSTRING(t.name, 1, 1) = :letter', ['letter' => $letter])
      ->groupBy('t.tid')
      ->groupBy('t.name')
      ->orderBy('t.name');

    $query->addExpression('COUNT(distinct q.id)', 'total');
    $query->innerJoin('asklib_question__tags', 'x', 'x.tags_target_id = t.tid');
    $query->innerJoin('asklib_questions', 'q', 'q.id = x.entity_id');

    $result = $query->execute()->fetchAll();

    return [
      '#theme' => 'asklib_keyword_subpage',
      '#letter' => mb_strtoupper($letter),
      '#terms' => $result,
      '#langcode' => $langcode,
      '#cache' => [
        'contexts' => ['languages:language_content']
      ]
    ];
  }

  public function misc() {
    $langcode = $this->langcode();

    $query = $this->db->select('taxonomy_term_field_data', 't')
      ->fields('t', ['tid', 'name'])
      ->condition('t.vid', ['asklib_tags', 'finto'], 'IN')
      ->condition('t.langcode', $langcode)
      ->condition('q.langcode', $langcode)
      ->condition('q.state', QuestionInterface::STATE_ANSWERED)
      ->condition('q.published', 1)
      ->where('t.name NOT REGEXP :regexp', ['regexp' => '^[a-zåäö]'])
      ->groupBy('t.tid')
      ->groupBy('t.name')
      ->orderBy('t.name');

    $query->addExpression('COUNT(distinct q.id)', 'total');
    $query->innerJoin('asklib_question__tags', 'x', 'x.tags_target_id = t.tid');
    $query->innerJoin('asklib_questions', 'q', 'q.id = x.entity_id');

    $result = $query->execute()->fetchAll();

    return [
      '#theme' => 'asklib_keyword_subpage',
      '#letter' => '0-9',
      '#misc' => true,
      '#terms' => $result,
      '#langcode' => $langcode,
      '#cache' => [
        'contexts' => ['languages:language_content']
      ]
    ];
  }

  public function letterTitle(Request $request) {
    $letter = mb_strtoupper($request->attributes->get('letter'));
    return sprintf('%s – %s', t('Keyword index'), $letter);
  }

  public function miscTitle() {
    return sprintf('%s – 0–9', t('Keyword index'));
  }

  private function langcode() {
    return $this->languageManager->getCurrentLanguage()->getId();
  }
}
