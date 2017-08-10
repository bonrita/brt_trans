<?php

namespace Drupal\gf_trans;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface TranslateEntity.
 *
 * @package Drupal\gf_trans
 */
interface TranslateEntity {

  /**
   * Acts when creating a new entity translation or on entity pre-save.
   *
   * This will be called after a new entity translation object has just been
   * instantiated.
   *
   * @param \Drupal\Core\Entity\EntityInterface $translation
   *   The entity about to be translated.
   */
  public function translate(EntityInterface $translation);

}
