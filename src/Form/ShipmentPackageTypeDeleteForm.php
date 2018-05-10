<?php

namespace Drupal\commerce_shipping\Form;

use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form to delete a shipment type.
 */
class ShipmentPackageTypeDeleteForm extends EntityDeleteForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $package_query = $this->entityTypeManager->getStorage('commerce_package')->getQuery();
    $package_count = $package_query
      ->condition('type', $this->entity->id())
      ->count()
      ->execute();
    if ($package_count) {
      $caption = '<p>' . $this->formatPlural($package_count, '%type is used by 1 package on your site. You can not remove this shipment package type until you have removed all of the %type packages.', '%type is used by @count packages on your site. You may not remove %type until you have removed all of the %type packages.', ['%type' => $this->entity->label()]) . '</p>';
      $form['#title'] = $this->getQuestion();
      $form['description'] = ['#markup' => $caption];
      return $form;
    }

    return parent::buildForm($form, $form_state);
  }

}
