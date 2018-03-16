<?php

namespace Drupal\asklib;

use Drupal\views\EntityViewsData;

class AnswerViewsData extends EntityViewsData
{
    public function getViewsData()
    {
        $data = parent::getViewsData();
        $data['asklib_answers']['table']['base']['help'] = t('Ask a Librarian answers');
        $data['asklib_answers']['table']['base']['defaults']['field'] = 'body';
        $data['asklib_answers']['table']['wizard_id'] = 'asklib_answers';

        $data['asklib_answers']['answer']['help'] = t('Answer content');

//         $data['asklib_answers']['municipality']['field']['id'] = 'taxonomy';
//         $data['asklib_answers']['target_library']['field']['id'] = 'taxonomy';
//
//         $data['asklib_answers']['qid']['field']['id'] = 'question';
//         $data['asklib_answers']['qid']['argument'] = [
//             'id' => 'question_qid',
//             'name field' => 'question',
//             'numeric' => true,
//         ];
//
//         print_r($data);

        return $data;
    }
}
