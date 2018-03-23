<?php

namespace Drupal\commerce_shipping\Entity;

use Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface as PackageTypePluginInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\ShipmentItem;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\physical\Weight;

interface PackageInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the package type.
   *
   * @return \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface|null
   *   The shipment package type, or NULL if unknown.
   */
  public function getPackageType();

  /**
   * Sets the package type.
   *
   * @param \Drupal\commerce_shipping\Plugin\Commerce\PackageType\PackageTypeInterface $package_type
   *   The package type.
   *
   * @return $this
   */
  public function setPackageType(PackageTypePluginInterface $package_type);

  /**
   * Gets the package title.
   *
   * @return string
   *   The package title.
   */
  public function getTitle();

  /**
   * Sets the package title.
   *
   * @param string $title
   *   The package title.
   *
   * @return $this
   */
  public function setTitle($title);

  /**
   * Gets the shipment items.
   *
   * @return \Drupal\commerce_shipping\ShipmentItem[]
   *   The shipment items.
   */
  public function getItems();

  /**
   * Sets the shipment items.
   *
   * @param \Drupal\commerce_shipping\ShipmentItem[] $shipment_items
   *   The shipment items.
   *
   * @return $this
   */
  public function setItems(array $shipment_items);

  /**
   * Gets whether the package has items.
   *
   * @return bool
   *   TRUE if the shipment has items, FALSE otherwise.
   */
  public function hasItems();

  /**
   * Adds a shipment item.
   *
   * @param \Drupal\commerce_shipping\ShipmentItem $shipment_item
   *   The shipment item.
   *
   * @return $this
   */
  public function addItem(ShipmentItem $shipment_item);

  /**
   * Removes a shipment item.
   *
   * @param \Drupal\commerce_shipping\ShipmentItem $shipment_item
   *   The shipment item.
   *
   * @return $this
   */
  public function removeItem(ShipmentItem $shipment_item);

  /**
   * Gets the package weight.
   *
   * Calculated by adding the weight of each item to the
   * weight of the package type.
   *
   * @return \Drupal\physical\Weight|null
   *   The package weight, or NULL if unknown.
   */
  public function getWeight();

  /**
   * Sets the package weight.
   *
   * @param \Drupal\physical\Weight $weight
   *   The package weight.
   *
   * @return $this
   */
  public function setWeight(Weight $weight);

  /**
   * Gets the package declared value.
   *
   * Represents the cost of all items within the package.
   *
   * @return \Drupal\commerce_price\Price|null
   *   The package declared value, or NULL if unknown.
   */
  public function getDeclaredValue();

  /**
   * Sets the package declared value.
   *
   * @param \Drupal\commerce_price\Price $declared_value
   *   The package declared value.
   *
   * @return $this
   */
  public function setDeclaredValue(Price $declared_value);

  /**
   * Gets the package tracking code.
   *
   * @return string|null
   *   The package tracking code, or NULL if unknown.
   */
  public function getTrackingCode();

  /**
   * Sets the package tracking code.
   *
   * @param string $tracking_code
   *   The package tracking code.
   *
   * @return $this
   */
  public function setTrackingCode($tracking_code);

  /**
   * Gets a package data value with the given key.
   *
   * Used to store temporary data.
   *
   * @param string $key
   *   The key.
   * @param mixed $default
   *   The default value.
   *
   * @return array
   *   The package data.
   */
  public function getData($key, $default = NULL);

  /**
   * Sets a package data value with the given key.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  public function setData($key, $value);

  /**
   * Gets the package creation timestamp.
   *
   * @return int
   *   The package creation timestamp.
   */
  public function getCreatedTime();

  /**
   * Sets the package creation timestamp.
   *
   * @param int $timestamp
   *   The package creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);
}