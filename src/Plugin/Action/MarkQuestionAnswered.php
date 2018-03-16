<?php

namespace Drupal\asklib\Plugin\Action;

use DateTime;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\asklib\UserMailGroupHelper;
use Drupal\asklib\QuestionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Changes question status to answered.
 *
 * @Action(
 *   id = "asklib_mark_question_answered",
 *   label = @Translation("Mark question answered"),
 *   type = "asklib_question"
 * )
 */
class MarkQuestionAnswered extends ActionBase implements ContainerFactoryPluginInterface {
  protected $termStorage;
  protected $currentUser;
  protected $mailGroups;

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('asklib.user_mail_group_helper')
    );
  }

  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_manager, AccountInterface $current_user, UserMailGroupHelper $mail_groups) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->termStorage = $entity_manager->getStorage('taxonomy_term');
    $this->currentUser = $current_user;
    $this->mailGroups = $mail_groups;
  }

  /**
   * {@inheritdoc}
   */
  public function execute($question = NULL) {
    if (!$question->getAnsweredTime()) {
      $question->setAnsweredTime(new DateTime);
    }
    $question->setState(QuestionInterface::STATE_ANSWERED);
    $question->release();

    $answer = $question->getAnswer();

    if (!$answer->getLibrary() && $library = $this->getStatisticsLibrary()) {
      $question->setTargetLibrary($library);
      $question->getAnswer()->setLibrary($library);
      $question->getAnswer()->save();
    }

    $question->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

  protected function getStatisticsLibrary() {
    return $this->mailGroups->getUserMainGroup($this->currentUser->id());
  }
}
