<?php

namespace Drupal\asklib;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;
use Drupal\Core\Field\FieldStorageDefinitionInterface;

class QuestionStorageSchema extends SqlContentEntityStorageSchema {
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);

    $schema['asklib_question_index'] = [
      'description' => 'Maintains denormalized information about question/term relationships',
      'fields' => [
        'qid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'tid' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'status' => [
          'description' => 'Indicates whether or not the question is available to public access.',
          'type' => 'int',
          'size' => 'tiny',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'default' => 0,
        ],
        'created' => [
          'description' => 'The UNIX timestamp when the question was created',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
        ],
        'answered' => [
          'description' => 'The UNIX timestamp when the question was answered',
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => FALSE,
        ]
      ],
      'primary key' => ['qid', 'tid'],
      'foreign keys' => [
        'tracked_question' => [
          'table' => 'asklib_questions',
          'columns' => ['qid' => 'id'],
        ],
        'term' => [
          'table' => 'taxonomy_term_data',
          'columns' => ['tid' => 'tid'],
        ]
      ],
      'indexes' => [
        'term_question' => ['tid', 'status'],
      ]
    ];

    return $schema;
  }
}
