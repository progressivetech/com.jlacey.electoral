<?php

namespace Civi\Api4\Action\Electoral;
use CRM_Electoral_ExtensionUtil as E;

/**
 * Lookup electoral info for a given Contact ID. 
 *
 */
class Lookup extends \Civi\Api4\Generic\AbstractAction {

  /**
   * Contact Id to lookup.
   *
   * @var int 
   * @required
   */

  protected $contactId;

  public function _run(\Civi\Api4\Generic\Result $result) {
    $locationType = \Civi\Api4\Setting::get(FALSE)
      ->addSelect('addressLocationType')
      ->execute()->first()['value'];

    // Lookup the address id we should use for this contact.
    $addressQuery = \Civi\Api4\Address::get()
      ->addSelect('id')
      ->addWhere('contact_id', '=', $this->getContactId());

    if ($locationType == 0) {
      $addressQuery->addWhere('is_primary', '=', TRUE);
    }
    else {
      $addressQuery->addWhere('location_type_id', '=', $locationType);
    }
    $addressId = $addressQuery->execute()->first()['id'];
    if (empty($addressId)) {
      throw new \API_Exception(E::ts("Failed to find an address for that contact."));
    }

    $enabledProviders = \Civi::settings()->get('electoralApiProviders');
    foreach ($enabledProviders as $enabledProvider) {
      $className = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('name')
        ->addWhere('option_group_id:name', '=', 'electoral_api_data_providers')
        ->addWhere('value', '=', $enabledProvider)
        ->execute()
        ->column('name')[0];
      $provider = new $className();
      $provider->singleAddressLookup($addressId);
    }

    // Now we hopefully have some electoral data to report.
    $districts = \Civi\Api4\Contact::get()
      ->addSelect('electoral_districts.*')
      ->addWhere('id', '=', $this->getContactId())
      ->execute();
    foreach($districts as $district) {
      $result[] = $district;
    }
  }
}
?>
