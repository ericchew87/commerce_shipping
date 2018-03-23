<?php

namespace Drupal\commerce_shipping\Plugin\Field\FieldFormatter;

use Drupal\commerce_price\NumberFormatterFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\DecimalFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use CommerceGuys\Intl\Formatter\NumberFormatterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @FieldFormatter(
 *   id = "commerce_packaging_view",
 *   label = @Translation("Packaging"),
 *   description = @Translation("Packaging"),
 *   field_types = {
 *     "commerce_packaging"
 *   }
 * )
 */
class PackagingFormatter extends DecimalFormatter implements ContainerFactoryPluginInterface{

  /**
   * The currency storage.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct($plugin_id, $plugin_definition, FieldDefinitionInterface $field_definition, array $settings, $label, $view_mode, array $third_party_settings, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($plugin_id, $plugin_definition, $field_definition, $settings, $label, $view_mode, $third_party_settings);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $settings = $this->getFieldSettings();

    foreach ($items as $delta => $item) {
      $output = $this->numberFormat($item->min);

      // Account for prefix and suffix.
      if ($this->getSetting('prefix_suffix')) {
        $prefixes = isset($settings['prefix']) ? array_map(['Drupal\Core\Field\FieldFilteredMarkup', 'create'], explode('|', $settings['prefix'])) : [''];
        $suffixes = isset($settings['suffix']) ? array_map(['Drupal\Core\Field\FieldFilteredMarkup', 'create'], explode('|', $settings['suffix'])) : [''];
        $prefix = (count($prefixes) > 1) ? $this->formatPlural($item->min, $prefixes[0], $prefixes[1]) : $prefixes[0];
        $suffix = (count($suffixes) > 1) ? $this->formatPlural($item->min, $suffixes[0], $suffixes[1]) : $suffixes[0];
        $output = $prefix . $output . $suffix;
      }
      // Output the raw value in a content attribute if the text of the HTML
      // element differs from the raw value (for example when a prefix is used).
      if (isset($item->_attributes) && $item->min != $output) {
        $item->_attributes += ['content' => $item->min];
      }

      $elements[$delta] = ['#markup' => $output];
    }

    return $elements;
  }

}