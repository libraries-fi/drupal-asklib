<?php

namespace Drupal\asklib\Form;

use Drupal\asklib\QuestionInterface;

/**
 * Provides method for loading a specified Action inside an entity form.
 */
trait ProvideEntityFormActionGetter {
  public function action($id) {
    return $this->entityTypeManager->getStorage('action')->load($id);
  }

  public function executeAction($action_id, QuestionInterface $question) {
    return $this->action($action_id)->execute([$question]);
  }
}
