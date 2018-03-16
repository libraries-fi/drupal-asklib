<?php

namespace Drupal\asklib;

use Drupal\views\EntityViewsData;

class LockViewsData extends EntityViewsData
{
    public function getViewsData()
    {
        $data = parent::getViewsData();
        $data['asklib_lock']['table']['base']['help'] = t('Ask a Librarian locks');
        $data['asklib_lock']['table']['base']['defaults']['field'] = 'id';
        $data['asklib_lock']['answer']['help'] = t('Question lock');

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
