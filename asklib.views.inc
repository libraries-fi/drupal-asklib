<?php

use Drupal\field\FieldStorageConfigInterface;
//
function asklib_views_data_alter(array &$data) {
  // var_dump($data);
  // exit('AAAAALTER');

  // $langcode = \Drupal::languageManager()->getCurrentLanguage()->getId();
  //
  // $data['taxonomy_term_data']['asklib_question_tag'] = [
  //   'title' => t('Related questions'),
  //   'help' => t('Relate terms to questions that are tagged using the term.'),
  //   'relationship' => [
  //     'id' => 'standard',
  //     'label' => t('Questionssss'),
  //     'field' => 'tid',
  //     'base' => 'asklib_question__tags',
  //     'base field' => 'tags_target_id',
  //     'extra' => [
  //       [
  //         'field' => 'langcode',
  //         // 'value' => $langcode,
  //         'left_field' => 'langcode',
  //       ],
  //       // [
  //       //   // 'table' => 'asklib_question__tags',
  //       //   'field' => 'entity_id',
  //       //   'left_table' => 'asklib_questions',
  //       //   'left_field' => 'id',
  //       //   'type' => 'INNER',
  //       // ]
  //     ]
  //   ],
  //   'field' => [
  //     'title' => t('All questions'),
  //     'help' => t('Display all questions tagged using a term'),
  //     'id' => 'asklib_question_tag_tid',
  //     'no group by' => TRUE,
  //     'click sortable' => FALSE
  //   ],
  // ];
  //
  // $data['asklib_question__tags']['question'] = [
  //   'title' => t('Reverse join from tag to question'),
  //   'help' => t('asdasdasd'),
  //   'relationship' => [
  //     'id' => 'standard',
  //     'label' => t('MOAR QUESTIONS'),
  //     'field' => 'entity_id',
  //     'base' => 'asklib_questions',
  //     'base field' => 'id',
  //     'extra' => [
  //       [
  //         'field' => 'state',
  //         'value' => 2,
  //         'numeric' => TRUE,
  //       ],
  //       [
  //         'field' => 'published',
  //         'value' => 1,
  //         'numeric' => TRUE,
  //       ]
  //     ]
  //   ]
  // ];
}







//
// function asklib_field_views_data_views_data_alter(array &$data, FieldStorageConfigInterface $field_storage) {
//   exit('alter');
//
//
//   $field_name = $field->getName();
//   $data_key = 'field_data_' . $field_name;
//   $entity_type_id = $field->entity_type;
//   $entity_type = \Drupal::entityManager()->getDefinition($entity_type_id);
//   $pseudo_field_name = 'reverse_' . $field_name . '_' . $entity_type_id;
//   list($label) = views_entity_field_label($entity_type_id, $field_name);
//   $table_mapping = \Drupal::entityManager()->getStorage($entity_type_id)->getTableMapping();
//
//   // Views data for this field is in $data[$data_key].
//   $data[$data_key][$pseudo_field_name]['relationship'] = [
//     'title' => t('@entity using @field', ['@entity' => $entity_type->getLabel(), '@field' => $label]),
//     'help' => t('Relate each @entity with a @field set to the term.', ['@entity' => $entity_type->getLabel(), '@field' => $label]),
//     'id' => 'entity_reverse',
//     'field_name' => $field_name,
//     'entity_type' => $entity_type_id,
//     'field table' => $table_mapping->getDedicatedDataTableName($field),
//     'field field' => $field_name . '_target_id',
//     'base' => $entity_type->getBaseTable(),
//     'base field' => $entity_type->getKey('id'),
//     'label' => $field_name,
//     'join_extra' => [
//       0 => [
//         'field' => 'deleted',
//         'value' => 0,
//         'numeric' => TRUE,
//       ],
//     ],
//   ];
//
// }
