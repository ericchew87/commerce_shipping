<?php

namespace Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShipmentPackagerManager;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_shipping\ShippingService;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the base class for shipping methods.
 */
abstract class ShippingMethodBase extends PluginBase implements ContainerFactoryPluginInterface, ShippingMethodInterface {

  /**
   * The package type manager.
   *
   * @var \Drupal\commerce_shipping\PackageTypeManagerInterface
   */
  protected $packageTypeManager;

  /**
   * The shipment packager manager.
   *
   * @var \Drupal\commerce_shipping\ShipmentPackagerManager
   */
  protected $shipmentPackagerManager;

  /**
   * The shipping services.
   *
   * @var \Drupal\commerce_shipping\ShippingService[]
   */
  protected $services = [];

  /**
   * The shipment.
   *
   * @var  \Drupal\commerce_shipping\Entity\ShipmentInterface
   */
  protected $shipment;

  /**
   * Constructs a new ShippingMethodBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\commerce_shipping\ShipmentPackagerManager $shipment_packager_manager
   *   The shipment packager manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, ShipmentPackagerManager $shipment_packager_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->packageTypeManager = $package_type_manager;
    foreach ($this->pluginDefinition['services'] as $id => $label) {
      $this->services[$id] = new ShippingService($id, (string) $label);
    }
    $this->shipmentPackagerManager = $shipment_packager_manager;
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('plugin.manager.commerce_shipment_packager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel() {
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultPackageType() {
    $package_type_id = $this->configuration['default_package_type'];
    return $this->packageTypeManager->createInstance($package_type_id);
  }

  /**
   * {@inheritdoc}
   */
  public function getServices() {
    // Filter out shipping services disabled by the merchant.
    return array_intersect_key($this->services, array_flip($this->configuration['services']));
  }

  /**
   * {@inheritdoc}
   */
  public function getPackagers() {
    $packagers = [];
    foreach ($this->configuration['packager_config']['packagers'] as $key => $values) {
      if ($this->shipmentPackagerManager->hasDefinition($key) && $values['enabled']) {
        $packagers[] = $this->shipmentPackagerManager->createInstance($key);
      }
    }
    return $packagers;
  }

  /**
   * {@inheritdoc}
   */
  public function packageShipment(ShipmentInterface $shipment) {
    // Don't let the ShipmentOrderProcessor mess with the shipment data.
    $packagers = $this->getPackagers();
    if ($packagers) {
      /** @var \Drupal\commerce_shipping\Plugin\Commerce\ShipmentPackager\ShipmentPackagerInterface $packager */
      foreach ($packagers as $packager) {
        $packager->packageItems($shipment, $this);
        if (empty($shipment->getData('unpackaged_items'))) {
          break;
        }
      }
      $items = $shipment->getData('unpackaged_items', []) + $shipment->getData('packaged_items', []);
      $shipment->setItems($items);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'default_package_type' => 'custom_box',
      'services' => [],
      'packager_config' => [
        'packagers' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $package_types = $this->packageTypeManager->getDefinitionsByShippingMethod($this->pluginId);
    $package_types = array_map(function ($package_type) {
      return $package_type['label'];
    }, $package_types);
    $services = array_map(function ($service) {
      return $service->getLabel();
    }, $this->services);

    $packagers = $this->shipmentPackagerManager->getDefinitions();
    // sort packagers based on their weight so they are ordered correctly in the tabledrag element.
    if (!empty($this->configuration['packager_config']['packagers'])) {
      uasort($packagers, function ($a, $b) {
        if (!empty($this->configuration['packager_config']['packagers'][$a['id']]) && !empty($this->configuration['packager_config']['packagers'][$b['id']])) {
          return ($this->configuration['packager_config']['packagers'][$a['id']]['weight'] < $this->configuration['packager_config']['packagers'][$b['id']]['weight']) ? -1 : 1;
        }
        return 0;
      });
    }

    // Select all services by default.
    if (empty($this->configuration['services'])) {
      $service_ids = array_keys($services);
      $this->configuration['services'] = array_combine($service_ids, $service_ids);
    }

    $form['default_package_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Default package type'),
      '#options' => $package_types,
      '#default_value' => $this->configuration['default_package_type'],
      '#required' => TRUE,
      '#access' => count($package_types) > 1,
    ];
    $form['services'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Shipping services'),
      '#options' => $services,
      '#default_value' => $this->configuration['services'],
      '#required' => TRUE,
      '#access' => count($services) > 1,
    ];
    $form['packager_config'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => t('Packager Configuration'),
    ];
    $form['packager_config']['packagers'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Enabled'),
        $this->t('Name'),
        $this->t('Description'),
        $this->t('Weight'),
      ],
      '#empty' => $this->t('No shipment packager plugins have been defined.'),
      // TableDrag: Each array value is a list of callback arguments for
      // drupal_add_tabledrag(). The #id of the table is automatically
      // prepended; if there is none, an HTML ID is auto-generated.
      '#tabledrag' => [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'table-sort-weight',
        ],
      ],
    ];

    $delta = 0;
    foreach ($packagers as $packager) {

      $weight = !empty($this->configuration['packager_config']['packagers'][$packager['id']]['weight']) ?
        $this->configuration['packager_config']['packagers'][$packager['id']]['weight'] : $delta;
      $enabled = !empty($this->configuration['packager_config']['packagers'][$packager['id']]['enabled']) ?
        $this->configuration['packager_config']['packagers'][$packager['id']]['enabled'] : FALSE;

      $form['packager_config']['packagers'][$packager['id']]['#attributes']['class'][] = 'draggable';
      $form['packager_config']['packagers'][$packager['id']]['#weight'] = $weight;
      $form['packager_config']['packagers'][$packager['id']]['enabled'] = [
        '#type' => 'checkbox',
        '#title' => t('Enabled'),
        '#title_display' => 'invisible',
        '#default_value' => $enabled,
      ];
      $form['packager_config']['packagers'][$packager['id']]['name'] = [
        '#markup' => $packager['label'],
      ];
      $form['packager_config']['packagers'][$packager['id']]['description'] = [
        '#markup' => $packager['description'],
      ];
      $form['packager_config']['packagers'][$packager['id']]['weight'] = [
        '#type' => 'weight',
        '#title' => $this->t('Weight for @title', ['@title' => $packager['label']]),
        '#title_display' => 'invisible',
        '#default_value' => $weight,
        // Classify the weight element for #tabledrag.
        '#attributes' => ['class' => ['table-sort-weight']],
      ];
      $delta++;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      if (!empty($values['services'])) {
        $values['services'] = array_filter($values['services']);

        $this->configuration['default_package_type'] = $values['default_package_type'];
        $this->configuration['services'] = array_keys($values['services']);
      }
      if (!empty($values['packager_config'])) {
        $this->configuration['packager_config'] = $values['packager_config'];
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function selectRate(ShipmentInterface $shipment, ShippingRate $rate) {
    // Plugins can override this method to store additional information
    // on the shipment when the rate is selected (for example, the rate ID).
    $shipment->setShippingService($rate->getService()->getId());
    $shipment->setAmount($rate->getAmount());
  }

}
