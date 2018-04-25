<?php

namespace Drupal\commerce_shipping;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Entity\Currency;
use Drupal\commerce_price\NumberFormatterFactoryInterface;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for shipments.
 */
class ShipmentListBuilder extends EntityListBuilder {

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
   * Constructs a new ShipmentListBuilder object.
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
    return 'commerce_shipments';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityIds() {
    $order_id = $this->routeMatch->getParameter('commerce_order');
    $query = $this->getStorage()->getQuery()
      ->condition('order_id', $order_id)
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
      'label' => $this->t('Shipment'),
      'tracking' => $this->t('Tracking'),
      'amount' => $this->t('Amount'),
      'state' => $this->t('State'),
    ];
    return $header + parent::buildHeader();
  }

  /**
  * {@inheritdoc}
  */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface $entity */
    $amount = $entity->getAmount();
    $currency = Currency::load($amount->getCurrencyCode());

    $row['label']['data'] = [
      '#type' => 'link',
      '#title' => $entity->label(),
    ] + $entity->toUrl()->toRenderArray();
    $row['tracking'] = $entity->getTrackingCode();
    $row['amount'] = $this->numberFormatter->formatCurrency($amount->getNumber(), $currency);
    $row['state'] = $entity->getState()->getLabel();

    return $row + parent::buildRow($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    $order_id = $this->routeMatch->getParameter('commerce_order');
    $order = Order::load($order_id);
    $order_item_usage = [];
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    foreach ($order->getItems() as $order_item) {
      $order_item_usage[$order_item->id()] = [
        'quantity' => (int)$order_item->getQuantity(),
        'item' => $order_item,
      ];
    }
    /** @var \Drupal\commerce_shipping\Entity\ShipmentInterface[] $shipments */
    $shipments = $this->load();
    foreach ($shipments as $shipment) {
      foreach ($shipment->getItems() as $shipment_item) {
        $order_item_usage[$shipment_item->getOrderItemId()]['quantity'] -= $shipment_item->getQuantity();
      }
    }

    foreach ($order_item_usage as $order_item_id => $values) {
      if ($values['quantity'] > 0) {
        // @TODO Build section notifying that there are unshipped items
      }
    }

    $build =  parent::render();

    return $build;
  }

}