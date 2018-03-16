<?php

namespace Drupal\asklib\EmailVariables;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxy;
use Drupal\asklib\UserMailGroupHelper;
use Drupal\kifimail\VariablesProcessorInterface;
/**
 * Extract variables from questions.
 *
 * @VariablesProcessor(
 *   id = "asklib_question_processor",
 *   class = "Drupal\asklib\QuestionInterface"
 * )
 */
class QuestionVariables implements VariablesProcessorInterface {
  protected $user_storage;
  protected $mail_groups;

  public function __construct(EntityTypeManagerInterface $entity_manager, AccountProxy $current_user, UserMailGroupHelper $mail_groups) {
    $this->user_storage = $entity_manager->getStorage('user');
    $this->current_user = $current_user;
    $this->mail_groups = $mail_groups;
  }

  public function process(EntityInterface $question, array &$variables) {
    $variables['question'] = [
      'title' => $question->getTitle(),
      'body' => $question->getBody(),
      'details' => $question->getDetails(),
    ];

    $variables['user'] = [
      // User's real name
      'name' => $question->getName(),
      'email' => $question->getEmail(),

      // Might be null if user is not logged in
      'account' => $question->getUser(),
    ];

    if ($question->getAnswer()) {
      $answer = $question->getAnswer();
      $answerer = $this->loadUser($this->current_user->id());
      $group = $this->mail_groups->getUserMainGroup($this->current_user->id());

      $variables['answer'] = [
        'body' => $answer->getBody(),
        'details' => $answer->getDetails(),
      ];

      if (!$group) {
        $library_name = null;
      } elseif ($group->hasField('field_asklib_library_name')) {
        $library_name = $group->get('field_asklib_library_name')->value;
      } else {
        $library_name = $group->getName();
      }

      $variables['answerer']['is_admin'] = in_array('toimitus', $this->current_user->getRoles());
      $variables['answerer']['library'] = $library_name;
      $variables['answerer']['name'] = $answerer->hasField('field_real_name')
        ? $answerer->field_real_name->value
        : NULL;
    }
  }

  protected function loadUser($user_id) {
    return $this->user_storage->load($user_id);
  }
}
