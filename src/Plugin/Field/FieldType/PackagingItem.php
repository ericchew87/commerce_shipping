<?php

namespace Drupal\commerce_shipping\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'packaging' field type.
 *
 * @FieldType(
 *   id = "commerce_packaging",
 *   label = @Translation("Packaging"),
 *   description = @Translation("This field stores package type and minimum and maximum quantity integers"),
 *   category = @Translation("Commerce"),
 *   default_widget = "commerce_packaging_default",
 *   default_formatter = "commerce_packaging_view"
 * )
 */
class PackagingItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties['min'] = DataDefinition::create('string')
      ->setLabel(t('Minimum Quantity'))
      ->setRequired(TRUE);
    $properties['max'] = DataDefinition::create('string')
      ->setLabel(t('Maximum Quantity'))
      ->setRequired(TRUE);
    $properties['package_type'] = DataDefinition::create('string')
      ->setLabel(t('Package Type'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function getConstraints() {
    $constraint_manager = \Drupal::typedDataManager()->getValidationConstraintManager();
    $constraints = parent::getConstraints();

    $constraints[] = $constraint_manager->create('ComplexData', [
      'min' => [
        'Regex' => [
          'pattern' => '/^[+-]?((\d+(\.\d*)?)|(\.\d+))$/i',
        ]
      ],
      'max' => [
        'Regex' => [
          'pattern' => '/^[+-]?((\d+(\.\d*)?)|(\.\d+))$/i',
        ]
      ],
    ]);

    return $constraints;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'min' => [
          'type' => 'numeric',
          'precision' => 17,
          'scale' => 2,
        ],
        'max' => [
          'type' => 'numeric',
          'precision' => 17,
          'scale' => 2,
        ],
        'package_type' => [
          'type' => 'varchar_ascii',
          'length' => 255,
          'not null' => TRUE,
        ],
      ],
    ];
  }


  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    if (!isset($this->min) || !isset($this->max) || empty($this->package_type)) {
      return TRUE;
    }
    return FALSE;
  }

}
