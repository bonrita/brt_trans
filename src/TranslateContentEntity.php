<?php

namespace Drupal\gf_trans;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Statickidz\GoogleTranslate;

/**
 * Class TranslateContentEntity.
 *
 * @package Drupal\gf_trans
 */
class TranslateContentEntity implements TranslateEntity {

  /**
   * The translator.
   *
   * @var \Statickidz\GoogleTranslate
   */
  protected $googleTranslator;

  /**
   * The field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $fieldManager;

  /**
   * TranslateContentEntity constructor.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The field manager service.
   */
  public function __construct(EntityFieldManagerInterface $entity_field_manager) {
    $this->googleTranslator = new GoogleTranslate();
    $this->fieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function translate(EntityInterface $translation) {
    $target = $translation->language();
    $source = $translation->getUntranslated()->language();

    if ($target && !($translation instanceof ContentEntityBase) || !$translation->isNewTranslation() || ($target->getId() == $source->getId()) || !$translation->isTranslatable()) {
      return;
    }

    $fields = $this->fieldManager
      ->getFieldDefinitions($translation->getEntityTypeId(), $translation->bundle());

    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field */
    foreach ($fields as $field) {
      $field_name = $field->getName();

      if ($field instanceof BaseFieldDefinition && !in_array($field->getName(), $this->getTranslatableBaseFields())) {
        continue;
      }

      if ($field->isTranslatable() && in_array($field->getType(), $this->getTransltableTypes()) && !$translation->{$field_name}->isEmpty()) {
        $translatable_field_value_properties = $this->getTranslatableFieldValueProperties();
        $values = $translation->{$field_name}->getValue();

        array_walk($values, function (&$value) use ($translatable_field_value_properties, $source, $target) {
          array_walk($value, function (&$v, $key) use ($translatable_field_value_properties, $source, $target) {
            $v = in_array($key, $translatable_field_value_properties) && !empty($v) ? $this->googleTranslator->translate($source->getId(), $target->getId(), $v) : $v;
          });
        });

        // Add translated value.
        $translation->set($field_name, $values);
      }
    }

  }

  /**
   * Translatable field types.
   *
   * @return array
   *   A translatable list.
   */
  protected function getTransltableTypes() {
    return [
      'string',
      'string_long',
      'text_long',
      'image',
      'link',
    ];
  }

  /**
   * Translatable field value properties.
   *
   * @return array
   *   A translatable list.
   */
  protected function getTranslatableFieldValueProperties() {
    return [
      'value',
      'alt',
      'title',
    ];
  }

  /**
   * Translatable base fields.
   *
   * @return array
   *   A translatable list.
   */
  protected function getTranslatableBaseFields() {
    return [
      'title',
    ];
  }

}
