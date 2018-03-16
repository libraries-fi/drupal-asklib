<?php

namespace Drupal\asklib;

interface LockInterface {
  public function getQuestion();
  public function setQuestion(QuestionInterface $question = NULL);
}
