<?php

namespace Drupal\gf_trans\Form;

use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\gf_trans\TranslateEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Class Translate.
 *
 * @package Drupal\gf_trans
 */
class GfTranslateContentForm extends FormBase {

  const TRANSLATE_ALL = '-';

  /**
   * The entity manager.
   *
   * @var EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The translate service.
   *
   * @var \Drupal\gf_trans\TranslateEntity
   */
  protected $translate;

  /**
   * Translate constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\gf_trans\TranslateEntity $gf_translate
   *   The translate service.
   */
  public function __construct(LanguageManagerInterface $language_manager, EntityTypeManager $entity_type_manager, ModuleHandlerInterface $module_handler, TranslateEntity $gf_translate) {
    $this->languageManager = $language_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->translate = $gf_translate;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('language_manager'),
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('gf_trans.translate_content_entity')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'gf_translate_content';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $language_options = $this->getLanguageOptions();
    $this->getDomains($form);

    $form['source'] = [
      '#type' => 'select',
      '#title' => t('Source'),
      '#options' => $language_options,
      '#required' => TRUE,
      '#default_value' => $this->languageManager->getCurrentLanguage(LanguageInterface::LANGCODE_SYSTEM)
        ->getId(),
      '#description' => $this->t('Usually the site default language in which the nodes were originally created.'),
    ];

    $form['target'] = [
      '#type' => 'select',
      '#title' => t('Target'),
      '#options' => $language_options,
      '#required' => TRUE,
      '#default_value' => '',
      '#description' => $this->t('The language to which you want to translate.'),
    ];

    $form['entity_type'] = [
      '#type' => 'select',
      '#title' => t('Entity type'),
      '#options' => [
        '' => $this->t('Select'),
        'node' => $this->t('Content (nodes)'),
      ],
      '#required' => TRUE,
      '#default_value' => 'node',
      '#description' => $this->t('The entity type whose content to translate.'),
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Translate'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $values = $form_state->getValues();

    $options = [
      'source' => $values['source'],
      'target' => $values['target'],
      'entity_type' => $values['entity_type'],
    ];

    if ($this->moduleHandler->moduleExists('domain')) {
      $options['domain'] = $values['domain'];
    }

    $batch = [
      'title' => t('Translating content.'),
      'operations' => [
        [
          [$this, 'translateContentEntities'],
          [$options],
        ],
      ],
      'finished' => [$this, 'translateBatchFinished'],
      'progress_message' => '',
    ];
    batch_set($batch);
  }

  /**
   * Translate content entities.
   *
   * @param array $options
   *   A list of user options to be used by the batch process.
   * @param array|\ArrayAccess $context
   *   The batch context array, passed by reference.
   *
   * @internal
   *   This batch callback is only meant to be used by this form.
   */
  public function translateContentEntities(array $options, &$context) {
    $limit = 10;

    // Set up user options so we can access them in the batch finished callback.
    $context['results']['options'] = $options;

    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;
      $context['sandbox']['current'] = 0;
      $context['sandbox']['max'] = $this->getEntitiesToTranslate($context['sandbox']['current'], true, $limit, $options);
    }

    $entities = $this->getEntitiesToTranslate($context['sandbox']['current'], false, $limit, $options);
    $gg =0;
    if ($entities) {
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      foreach ($entities as $entity) {
        $gg = $entity->hasTranslation($options['target']);
          $hh = $entity->hasTranslation($options['target']) ? $entity->getTranslation($options['target']) : '';
          $ff =0;
        if (!$entity->hasTranslation($options['target']) && $entity->isTranslatable()) {
          $translated_entity = $entity->addTranslation($options['target']);
          $translated_entity->save();
        }
      }

      $last_entity = end($entities);
      $context['sandbox']['current'] = $last_entity->id();
      $context['sandbox']['progress'] = $context['sandbox']['max'] - $this->getEntitiesToTranslate($context['sandbox']['current'], true, $limit, $options);
      $hh =0;
    }

    // Inform the batch engine that we are not finished and provide an
    // estimation of the completion level we reached.
    if (count($entities) > 0 && $context['sandbox']['progress'] != $context['sandbox']['max']) {
      $context['finished'] = $context['sandbox']['progress'] / $context['sandbox']['max'];
      $context['message'] = t('Translating content... Completed @percentage% (@current of @total).', [
        '@percentage' => round(100 * $context['sandbox']['progress'] / $context['sandbox']['max']),
        '@current' => $context['sandbox']['progress'],
        '@total' => $context['sandbox']['max']
      ]);
    }
    else {
      $context['finished'] = 1;
    }


  }

  /**
   * Implements callback_batch_finished().
   *
   * Finishes the translate content batch, redirect to the translate page and
   * output the successful content translate message.
   */
  public function translateBatchFinished($success, $results, $operations) {
    drupal_set_message($this->t('All Content has been translated.'));
    return new RedirectResponse(Url::fromRoute('gf_trans.translate_content')
      ->setAbsolute()
      ->toString());
  }

  /**
   * Nodes to replicate.
   *
   * @param int $current
   *   The current node id.
   * @param array $options
   *   A list of options.
   * @param bool $count
   *   To count what is to be replicated.
   * @param int $limit
   *   The limit.
   *
   * @return array|int
   *   Either a count or the nodes to be replicated.
   */
  public function getEntitiesToTranslate($current, $count = false, $limit = 10, $options) {
    $entity_type_id = $options['entity_type'];

    $storage = $this->entityTypeManager->getStorage($entity_type_id);
    $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);

    $query = $storage->getQuery();
    $query->condition($entity_type->getKey('id'), $current, '>');
    $query->condition($entity_type->getKey('langcode'), $options['source']);

    // Skip product pages for now. Something weird happens with them when translated.
    $query->condition($entity_type->getKey('bundle'), ['product_page'], 'NOT IN');


    if (!empty($options['domain']) && $options['domain'] <> self::TRANSLATE_ALL) {
      $query->condition('field_domain_access', $options['domain']);

      // @todo Add the ability to translate entities that are meant for all domains.
      // $query->condition('field_domain_all_affiliates', 0);
    }

    $query->sort($entity_type->getKey('id'), 'ASC');

    if ($count) {
      $query->count();
      return $query->execute();
    }
    else {
      $query->range(0, $limit);
      $entity_ids = $query->execute();
      return $storage->loadMultiple($entity_ids);
    }

  }

  /**
   * Get available domains.
   *
   * @param array $form
   *   A list of the form structure.
   */
  protected function getDomains(array &$form) {
    if ($this->moduleHandler->moduleExists('domain')) {
      $domains = $this->entityTypeManager->getStorage('domain')->loadMultiple();
      $domains = array_map(function ($val) {
        return $val->label();
      }, $domains);

      $domain_options = array_merge([
        '' => $this->t('Select'),
        self::TRANSLATE_ALL => $this->t('All'),
      ], $domains);

      $form['domain'] = [
        '#type' => 'select',
        '#title' => t('Domain'),
        '#options' => $domain_options,
        '#default_value' => '',
        '#required' => TRUE,
        '#description' => $this->t('The domain to translate content from.'),
      ];

    }
  }

  /**
   * Get language options.
   *
   * @return array
   *   A list of language options.
   */
  protected function getLanguageOptions() {
    $languages = $this->languageManager->getLanguages();
    $languages = array_map(function ($val) {
      return $val->getName();
    }, $languages);

    $language_options = array_merge([
      '' => $this->t('Select'),
    ], $languages);
    return $language_options;
  }

}
