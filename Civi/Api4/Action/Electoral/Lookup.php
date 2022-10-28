<?php

namespace Civi\Api4\Action\Electoral;
use CRM_Electoral_ExtensionUtil as E;

/**
 * Lookup electoral info for a given Contact ID. 
 *
 * No data is written to the database.
 *
 */
class Lookup extends \Civi\Api4\Generic\AbstractAction {

  /**
   * Contact Id to lookup.
   *
   * @var int 
   */

  protected $contactId;

  /**
   * Address string to lookup
   *
   * $var string
   */
  protected $address;

  /**
   * Write
   *
   * By default, we only lookup the value, we don't write any
   * data to the database. If you pass a contactId, you can 
   * set write=1 to also write the results we find to the specified
   * id.
   */
  protected $write = FALSE;

  public function _run(\Civi\Api4\Generic\Result $result) {
    $contactId = $this->getContactId();
    $address = $this->getAddress();
    $write = $this->getWrite();

    if ($write && !$contactId) {
      $msg = E::ts("When specifying that we should write the results to the databse, 
        you must provide a contactId so we know which contact to write to.");
      throw new \Exception($msg);
    }

    if (empty($contactId) && empty($address)) {
      throw new \Exception("Please include either a contactId or address to lookup.");
    }

    $enabledProviders = \Civi::settings()->get('electoralApiProviders');
    // Pop off the first provider.
    $enabledProvider = array_pop($enabledProviders);
    $className = \Civi\Api4\OptionValue::get(FALSE)
      ->addSelect('name')
      ->addWhere('option_group_id:name', '=', 'electoral_api_data_providers')
      ->addWhere('value', '=', $enabledProvider)
      ->execute()
      ->column('name')[0];
    $limit = 0;
    $update = TRUE;
    $provider = new $className($limit, $update);
    if ($contactId) {
      $locationType = \Civi\Api4\Setting::get()
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

      $addresses = $provider->getAddresses($addressId);
      if (count($addresses) == 0) {
        throw new \API_Exception(E::ts("Failed to find an address for that contact (addressId $addressId) that matches the Electoral settings."));
      }
      $address = $addresses->first();
    }
    else {
      $address = electoral_parse_address($this->getAddress());
    }
    $provider->setAddress($address);
    $out = $provider->lookup();
    if ($write && count($out['district']) > 0) {
      // We should end up using the cached results, so for simplicity just re-run
      // the query.
      $provider->processSingleAddress($addressId);
    }
    $massaged = [
      'district' => [],
      'official' => [],
    ];
    // We have to massage the data so it's more presentable.
    $massaged['district'] = $out['district'];
    foreach($out['official'] as $official) {
      $massaged['official'][] = [
        'name' => $official->getName(),
      ];
    }
    $result[] = [
      $className => $massaged
    ];  
  }


}
?>
