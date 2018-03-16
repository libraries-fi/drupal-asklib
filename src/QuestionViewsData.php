<?php

namespace Drupal\asklib;

use Drupal\views\EntityViewsData;

class QuestionViewsData extends EntityViewsData {
  public function getViewsData() {
    $data = parent::getViewsData();
    $data['asklib_questions']['table']['base']['help'] = t('Ask a Librarian questions');
    $data['asklib_questions']['table']['base']['defaults']['field'] = 'body';
    $data['asklib_questions']['table']['wizard_id'] = 'asklib_questions';

    $data['asklib_questions']['id']['argument'] = [
      'id' => 'asklib_question_id',
      'name field' => 'title',
      'numeric' => TRUE,
      'validate type' => 'id'
    ];

    $data['asklib_questions']['question']['help'] = t('Question content');
    $data['asklib_questions']['municipality']['help'] = t('Municipality selected for the question');
    $data['asklib_questions']['target_library']['help'] = t('Library responsible for answering the question');
    $data['asklib_questions']['channel']['help'] = t('Channel this question was submitted to.');

    $data['asklib_question__tags']['tags']['relationship']['real field'] = 'tags_target_id';

    $data['asklib_question_index']['table']['group'] = $this->t('Taxonomy term');

    $data['asklib_question_index']['table']['join'] = [
      'taxonomy_term_field_data' => [
        'left_field' => 'tid',
        'field' => 'tid'
      ],
      'taxonomy_term_hierarchy' => [
        'left_field' => 'tid',
        'field' => 'tid',
      ],
      'asklib_questions' => [
        'left_field' => 'qid',
        'field' => 'id',
      ]
    ];

    $data['asklib_question_index']['qid'] = [
      'title' => $this->t('Questions with term'),
      'help' => $this->t('Relate all questions tagged with a term.'),
      'relationship' => [
        'id' => 'standard',
        'base' => 'asklib_questions',
        'base field' => 'id',
        'label' => $this->t('Question'),
        'skip base' => 'asklib_questions'
      ]
    ];

    // $data['asklib_question_index']['tid'] = [
    //   'group' => $this->t('Ask a Librarian'),
    //   'title' => $this->t('Has taxonomy term ID'),
    //   'help' => $this->t('Display question if it has the selected taxonomy terms.'),
    //   'argument' => [
    //     'id' => 'asklib_question_index_tid',
    //     'name table' => 'taxonomy_term_field_data',
    //     'name field' => 'name',
    //     'empty field name' => $this->t('Uncategorized'),
    //     'numeric' => TRUE,
    //     'skip base' => 'taxonomy_term_field_data'
    //   ],
    //   'filter' => [
    //     'title' => $this->t('Has taxonomy term'),
    //     'id' => 'asklib_question_index_tid',
    //     'hierarchy table' => 'taxonomy_term_hierarchy',
    //     'numeric' => TRUE,
    //     'skip base' => 'taxonomy_term_field_data',
    //     'allow empty' => TRUE
    //   ]
    // ];

    $data['asklib_question_index']['created'] = [
      'title' => $this->t('Post date'),
      'help' => $this->t('The date the content related to a term was posted.'),
      'sort' => [
        'id' => 'date',
      ],
      'filter' => [
        'id' => 'date'
      ]
    ];

    // $type = 'asklib_question';
    // $entity_type = \Drupal::entityManager()->getDefinition($type);
    //
    // // Copied from CommentViewsData.
    // $data['comment_field_data']['asklib_question'] = [
    //   'relationship' => [
    //     'title' => $entity_type->getLabel(),
    //     'help' => $this->t('The @entity_type to which the comment is a reply to.', ['@entity_type' => $entity_type->getLabel()]),
    //     'base' => $entity_type->getDataTable() ?: $entity_type->getBaseTable(),
    //     'base field' => $entity_type->getKey('id'),
    //     'relationship field' => 'entity_id',
    //     'id' => 'standard',
    //     'label' => $entity_type->getLabel(),
    //     'extra' => [
    //       [
    //         'field' => 'entity_type',
    //         'value' => $type,
    //         'table' => 'comment_field_data'
    //       ],
    //     ],
    //   ],
    // ];



    return $data;
  }
}
