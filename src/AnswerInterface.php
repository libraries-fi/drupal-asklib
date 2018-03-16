<?php

namespace Drupal\asklib;

interface AnswerInterface {
  public function getQuestion();
  public function setQuestion($question);
  public function getBody();
  public function setBody($body);
  public function getDetails();
  public function setDetails($details);
  public function getAttachments();
}
