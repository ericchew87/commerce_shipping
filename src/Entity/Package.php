<?php

namespace Drupal\commerce_shipping\Entity;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface as PackageTypePluginInterface;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\physical\Weight;

/**
 * Defines the shipment entity class.
 *
 * @ContentEntityType(
 *   id = "commerce_package",
 *   label = @Translation("Package"),
 *   label_singular = @Translation("package"),
 *   label_plural = @Translation("packages"),
 *   label_count = @PluralTranslation(
 *     singular = "@count package",
 *     plural = "@count packages",
 *   ),
 *   handlers = {
 *     "storage" = "Drupal\commerce\CommerceContentEntityStorage",
 *     "access" = "Drupal\Core\Entity\EntityAccessControlHandler",
 *     "views_data" = "Drupal\views\EntityViewsData"
 *   },
 *   base_table = "commerce_package",
 *   admin_permission = "administer commerce_shipment",
 *   fieldable = TRUE,
 *   entity_keys = {
 *     "id" = "package_id",
 *     "label" = "title",
 *     "uuid" = "uuid",
 *   },
 * )
 */

class Package extends ContentEntityBase implements PackageInterface {

  use EntityChangedTrait;

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return $this->get('title')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTitle($title) {
    $this->set('title', $title);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getItems() {
    return $this->get('items')->getShipmentItems();
  }

  /**
   * {@inheritdoc}
   */
  public function setItems(array $shipment_items) {
    $this->set('items', $shipment_items);
    $this->recalculateWeight();
    $this->recalculateDeclaredValue();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function hasItems() {
    return !$this->get('items')->isEmpty();
  }

  /**
   * {@inheritdoc}
   */
  public function addItem(ShipmentItem $shipment_item) {
    $this->get('items')->appendItem($shipment_item);
    $this->recalculateWeight();
    $this->recalculateDeclaredValue();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function removeItem(ShipmentItem $shipment_item) {
    $this->get('items')->removeShipmentItem($shipment_item);
    $this->recalculateWeight();
    $this->recalculateDeclaredValue();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getPackageType() {
    if (!$this->get('package_type')->isEmpty()) {
      $package_type_id = $this->get('package_type')->value;
      $package_type_manager = \Drupal::service('plugin.manager.commerce_package_type');
      return $package_type_manager->createInstance($package_type_id);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setPackageType(PackageTypePluginInterface $package_type) {
    $this->set('package_type', $package_type->getId());
    $this->recalculateWeight();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTrackingCode() {
    return $this->get('tracking_code')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setTrackingCode($tracking_code) {
    $this->set('tracking_code', $tracking_code);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getDeclaredValue() {
    if (!$this->get('declared_value')->isEmpty()) {
      return $this->get('amount')->first()->toPrice();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setDeclaredValue(Price $declared_value) {
    $this->set('declared_value', $declared_value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  protected function recalculateDeclaredValue() {
    $total_declared_value = NULL;
    foreach ($this->getItems() as $item) {
      $declared_value = $item->getDeclaredValue();
      $total_declared_value = $total_declared_value ? $total_declared_value->add($declared_value) : $declared_value;
    }
    $this->setDeclaredValue($total_declared_value);
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    if (!$this->get('weight')->isEmpty()) {
      return $this->get('weight')->first()->toMeasurement();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight(Weight $weight) {
    $this->set('weight', $weight);
    return $this;
  }

  /**
   * Recalculates the shipment's weight.
   */
  protected function recalculateWeight() {
    if (!$this->hasItems()) {
      // Can't calculate the weight if the items are still unavailable.
      return;
    }

    /** @var \Drupal\physical\Weight $weight */
    $weight = NULL;
    foreach ($this->getItems() as $shipment_item) {
      $shipment_item_weight = $shipment_item->getWeight();
      if (!$weight) {
        $weight = $shipment_item_weight;
      }
      else {
        // @todo ->add() should perform unit conversions automatically.
        $shipment_item_weight = $shipment_item_weight->convert($weight->getUnit());
        $weight = $weight->add($shipment_item_weight);
      }
    }
    if ($package_type = $this->getPackageType()) {
      $package_type_weight = $package_type->getWeight()->convert($weight->getUnit());
      $weight = $weight->add($package_type_weight);
    }

    $this->setWeight($weight);
  }

  /**
   * {@inheritdoc}
   */
  public function getData($key, $default = NULL) {
    $data = [];
    if (!$this->get('data')->isEmpty()) {
      $data = $this->get('data')->first()->getValue();
    }
    return isset($data[$key]) ? $data[$key] : $default;
  }

  /**
   * {@inheritdoc}
   */
  public function setData($key, $value) {
    $this->get('data')->__set($key, $value);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getCreatedTime() {
    return $this->get('created')->value;
  }

  /**
   * {@inheritdoc}
   */
  public function setCreatedTime($timestamp) {
    $this->set('created', $timestamp);
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['title'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Title'))
      ->setDescription(t('The package title.'))
      ->setRequired(TRUE)
      ->setSettings([
        'default_value' => '',
        'max_length' => 255,
      ])
      ->setDisplayOptions('form', [
        'type' => 'string_textfield',
        'weight' => -5,
      ])
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $fields['items'] = BaseFieldDefinition::create('commerce_shipment_item')
      ->setLabel(t('Items'))
      ->setRequired(TRUE)
      ->setCardinality(BaseFieldDefinition::CARDINALITY_UNLIMITED)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['package_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Package type'))
      ->setDescription(t('The package type.'))
      ->setRequired(TRUE)
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('view', TRUE);

    $fields['weight'] = BaseFieldDefinition::create('physical_measurement')
      ->setLabel(t('Weight'))
      ->setRequired(TRUE)
      ->setSetting('measurement_type', 'weight')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['declared_value'] = BaseFieldDefinition::create('commerce_price')
      ->setLabel(t('Declared Value'))
      ->setDescription(t('The package declared value.'))
      ->setReadOnly(TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['tracking_code'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Tracking code'))
      ->setDescription(t('The shipment tracking code.'))
      ->setDefaultValue('')
      ->setSetting('max_length', 255)
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('A serialized array of additional data.'));

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(t('Created'))
      ->setDescription(t('The time when the package was created.'))
      ->setRequired(TRUE);

    $fields['changed'] = BaseFieldDefinition::create('changed')
      ->setLabel(t('Changed'))
      ->setDescription(t('The time when the package was last updated.'))
      ->setRequired(TRUE);

    return $fields;
  }

}