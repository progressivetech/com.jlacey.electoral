<?php

namespace Civi\Electoral;

abstract class Api {

  /**
   * @var int
   * How many records to update at once.
   */
  private $limit;

  /**
   * @var bool
   * Overwrite existing records' electoral data.
   */
  private $update;

  /**
   * Constructor class.
   */
  public function __construct(int $limit, bool $update) {
    $this->limit = $limit;
    $this->update = $update;
    return $this;
  }

  abstract public function districts();

  abstract public function reps();

}
