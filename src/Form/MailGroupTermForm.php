<?php

namespace Drupal\asklib\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\TermForm;

class MailGroupTermForm extends TermForm
{
    public function form(array $form, FormStateInterface $form_state)
    {
        $form = parent::form($form, $form_state);

        // $users = $this->entityManager->getStorage('user')->getQuery()
        //     ->sort('name')
        //     ->condition('roles', ['asklib_admin'], 'IN')
        //     ->execute();

        $users = $this->entityManager->getStorage('user')->loadByProperties(['roles' => ['asklib_admin']]);

        $sids = [];

        foreach ($this->entity->get('field_asklib_subscribers') as $item) {
            var_dump($item);
        }

        $form['subscribers'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Subscribers'),
            '#options' => $this->termOptions($users),
            '#default_value' => [],
        ];

        return $form;
    }

    protected function termOptions($entities)
    {
        $options = [];
        foreach ($entities as $entity) {
            $options[$entity->id()] = $entity->label();
        }
        return $options;
    }
}
