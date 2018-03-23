<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\EntityTrait;

use Drupal\entity\BundleFieldDefinition;
use Drupal\commerce\Plugin\Commerce\EntityTrait\EntityTraitBase;

/**
 * Provides the "purchasable_entity_packaging" trait.
 *
 * @CommerceEntityTrait(
 *   id = "purchasable_entity_packaging",
 *   label = @Translation("Manual Packaging"),
 *   entity_types = {"commerce_product_variation"}
 * )
 */
class PurchasableEntityPackaging extends EntityTraitBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = [];
    $fields['packaging'] = BundleFieldDefinition::create('commerce_packaging')
      ->setLabel(t('Manual Packaging'))
      ->setDescription(t('Manual Packaging'))
      ->setCardinality(BundleFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayOptions('form', array(
        'type' => 'commerce_packaging_default',
        'weight' => 92,
      ))
      ->setDisplayConfigurable('form', TRUE);

    return $fields;
  }

}
