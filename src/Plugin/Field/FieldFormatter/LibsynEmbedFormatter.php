<?php

namespace Drupal\media_entity_libsyn\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_entity\MediaTypeInterface;
use Drupal\media_entity_libsyn\Plugin\MediaEntity\Type\Libsyn;

/**
 * Plugin implementation of the 'libsyn_embed' formatter.
 *
 * @FieldFormatter(
 *   id = "libsyn_embed",
 *   label = @Translation("Libsyn embed"),
 *   field_types = {
 *     "link", "string", "string_long"
 *   }
 * )
 */
class LibsynEmbedFormatter extends FormatterBase {

  /**
   * @inheritDoc
   */
  public static function defaultSettings() {
    return array(
        'width' => '700',
        'height' => '90',
        'custom_color' => '87A93A',
        'theme' => 'custom', // ?
        'direction' => 'forward', // ?
        'options' => [],
      ) + parent::defaultSettings();
  }

  /**
   * @inheritDoc
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['width'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width'),
      '#default_value' => $this->getSetting('width'),
      '#min' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Width of embedded player. Suggested value: 700'),
    ];

    $elements['height'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Height'),
      '#default_value' => $this->getSetting('height'),
      '#min' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Height of embedded player. Suggested value: 90'),
    ];

    $elements['theme'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Theme'),
      '#default_value' => $this->getSetting('theme'),
      '#required' => TRUE,
      '#description' => $this->t('Theme name'),
    ];

    $elements['direction'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Direction'),
      '#default_value' => $this->getSetting('direction'),
      '#required' => TRUE,
      '#description' => $this->t('Direction'),
    ];

    $elements['custom_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom color'),
      '#default_value' => $this->getSetting('custom_color'),
      '#required' => TRUE,
      '#description' => $this->t('Custom color (6-character hex code)'),
    ];

    $elements['options'] = [
      '#title' => $this->t('Options'),
      '#type' => 'checkboxes',
      '#default_value' => $this->getSetting('options'),
      '#options' => $this->getEmbedOptions(),
    ];

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = [
      $this->t('Width: @width', [
        '@width' => $this->getSetting('width'),
      ]),
      $this->t('Height: @height', [
        '@height' => $this->getSetting('height'),
      ]),
    ];

    $options = $this->getSetting('options');
    if (count($options)) {
      $summary[] = $this->t('Options: @options', [
        '@options' => implode(', ', array_intersect_key($this->getEmbedOptions(), array_flip($this->getSetting('options')))),
      ]);
    }

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\media_entity\MediaInterface $media_entity */
    $media_entity = $items->getEntity();

    $element = [];
    if (($type = $media_entity->getType()) && $type instanceof Libsyn) {
      /** @var MediaTypeInterface $item */
      foreach ($items as $delta => $item) {
        if ($episode_id = $type->getField($media_entity, 'episode_id')) {
          $element[$delta] = [
            '#theme' => 'media_libsyn_embed',
            '#episode_id' => $episode_id,
            '#width' => $this->getSetting('width'),
            '#height' => $this->getSetting('height'),
            '#embed_theme' => $this->getSetting('theme'),
            '#custom_color' => $this->getSetting('custom_color'),
            '#direction' => $this->getSetting('direction'),
            '#options' => $this->getSetting('options'),
          ];
        }
      }
    }

    return $element;
  }

  /**
   * Returns an array of options for the embedded player.
   *
   * @return array
   */
  protected function getEmbedOptions() {
    return [
      'autonext' => $this->t('Auto Next'),
      'thumbnail' => $this->t('Show thumbnail'),
      'autoplay' => $this->t('Autoplay'),
      'preload' => $this->t('Preload'),
      'no_addthis' => $this->t('No Addthis'),
      'render_playlist' => $this->t('Render playlist'),
    ];
  }
}
