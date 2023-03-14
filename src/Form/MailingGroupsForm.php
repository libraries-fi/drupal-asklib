<?php

namespace Drupal\asklib\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MailingGroupsForm extends ConfigFormBase
{
    public static function create(ContainerInterface $container)
    {
        return new static(
            $container->get('config.factory'),
            $container->get('entity_type.manager')->getStorage('taxonomy_term')
        );
    }

    public function __construct(ConfigFactoryInterface $config_factory, EntityStorageInterface $terms) {
        parent::__construct($config_factory);
        $this->terms = $terms;
    }

    public function getFormId()
    {
        return 'asklib_admin_email_groups';
    }

    public function buildForm(array $form, FormStateInterface $form_state)
    {
        $mopts = [];
        $config = $this->config('asklib.settings');
        $form = parent::buildForm($form, $form_state);

        $municipalities = $this->terms->loadByProperties(['vid' => ['asklib_municipalities', 'asklib_libraries']]);

        foreach ($municipalities as $term) {
            $mopts[$term->id()] = $term->label();
        }

        asort($mopts);

        $form['municipalities'] = [
            '#type' => 'checkboxes',
            '#title' => $this->t('Municipalities'),
            '#options' => $mopts,
        ];

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state)
    {
        parent::submitForm($form, $form_state);

        $this->config('asklib.settings')
            ->set('reserved_window', $form_state->getValue('reserved_window'))
            ->set('highlight_pending_after', $form_state->getValue('highlight_pending_after'))
            ->set('email.question_received.subject', $form_state->getValue('mail_question_received_subject'))
            ->set('email.question_received.body', $form_state->getValue('mail_question_received_body'))
            ->set('email.answer.subject', $form_state->getValue('mail_answer_subject'))
            ->set('email.answer.body', $form_state->getValue('mail_answer_body'))
            ->save();
    }

    protected function getEditableConfigNames()
    {
        return ['asklib.settings'];
    }
}
