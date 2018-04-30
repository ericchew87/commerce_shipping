<?php

namespace Drupal\commerce_shipping;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Entity\Currency;
use Drupal\commerce_price\NumberFormatterFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for packages.
 */
class PackageListBuilder extends EntityListBuilder {

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * @var \Drupal\commerce_price\NumberFormatterFactoryInterface
   */
  protected $numberFormatter;

  /**
   * {@inheritdoc}
   */
  protected $entitiesKey = 'shipments';

  /**
   * Constructs a new PackageListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage class.
   * @param \Drupal\commerce_price\NumberFormatterFactoryInterface $number_formatter_factory
   *   The number formatter factory.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The current route match.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, NumberFormatterFactoryInterface $number_formatter_factory, RouteMatchInterface $route_match) {
    parent::__construct($entity_type, $storage);

    $this->numberFormatter = $number_formatter_factory->createInstance();
    $this->routeMatch = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.manager')->getStorage($entity_type->id()),
      $container->get('commerce_price.number_formatter_factory'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'commerce_packages';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $shipment_id = $this->routeMatch->getParameter('commerce_shipment');
    $query = $this->getStorage()->getQuery()
      ->condition('shipment_id', $shipment_id)
      ->sort($this->entityType->getKey('id'));

    // Only add the pager if a limit is specified.
    if ($this->limit) {
      $query->pager($this->limit);
    }
    return $query->execute();
  }

  /**
  * {@inheritdoc}
  */
  public function buildHeader() {
    $header = [
      'label' => $this->t('Package'),
      'tracking' => $this->t('Tracking'),
      'declared_value' => $this->t('Declared Value'),
    ];
    return $header + parent::buildHeader();
  }

  /**
  * {@inheritdoc}
  */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_shipping\Entity\PackageInterface $entity */
    $amount = $entity->getDeclaredValue();
    $currency = Currency::load($amount->getCurrencyCode());

    $row['label']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label(),
    ] + $entity->toUrl()->toRenderArray();
    $row['tracking'] = $entity->getTrackingCode();
    $row['declared_value'] = $this->numberFormatter->formatCurrency($amount->getNumber(), $currency);

    return $row + parent::buildRow($entity);
  }

}