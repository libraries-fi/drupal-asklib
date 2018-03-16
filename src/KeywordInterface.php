<?php

namespace Drupal\asklib;

interface KeywordInterface {
  public function getName();
  public function getUrl();
  public function isCustomKeyword();
}
