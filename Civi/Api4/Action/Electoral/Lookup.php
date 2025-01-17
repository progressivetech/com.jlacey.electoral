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

  /**
   * Include officials in output?
   */
  protected $includeOfficials = FALSE;

  /*
   * Include districts in output?
   */
  protected $includeDistricts = TRUE;

  /**
   * Cache
   *
   * Use the cache to avoid additional lookups?
   *
   */
  protected $cache = TRUE;

  /*
   * Electoral API Provider
   *
   * Provide the name of the API provider (Cicero, GoogleCivic or OpenStates)
   *
   * If left blank we will pick the first one that is configured.
   *
   */
  protected $apiProvider = NULL;

  /*
   * guzzleClient
   *
   * Only used for testing purposes.
   */
  protected $guzzleClient = NULL;

  /**
   * geocodeProviderClass
   *
   * Only used for testing purposes.
   */
  protected $geocodeProviderClass = NULL;

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

    $apiProvider = $this->getApiProvider();
    $className = NULL;
    if ($apiProvider) {
      // In the option value table the "name" is the class name, e.g.
      // \Civi\Electroal\Api\Cicero so we do a like search to match Cicero
      $className = \Civi\Api4\OptionValue::get(FALSE)
        ->addSelect('name')
        ->addWhere('option_group_id:name', '=', 'electoral_api_data_providers')
        ->addWhere('name', 'LIKE', "%{$apiProvider}%")
        ->execute()
        ->column('name')[0] ?? NULL;
    }
    else {
      $enabledProviders = \Civi::settings()->get('electoralApiProviders', []);
      if ($enabledProviders) {
        // Pop off the first provider.
        $apiProvider = array_pop($enabledProviders);
        $className = \Civi\Api4\OptionValue::get(FALSE)
          ->addSelect('name')
          ->addWhere('option_group_id:name', '=', 'electoral_api_data_providers')
          ->addWhere('value', '=', $apiProvider)
          ->execute()
          ->column('name')[0] ?? NULL;
      }
    }
    if (!$className) {
      throw new \API_Exception(E::ts("Failed to locate Electoral API Provider: %1.", [ 1 => $apiProvider ]));
    }
    $limit = 0;
    $update = TRUE;
    $provider = new $className($limit, $update, $this->getCache());
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
    $provider->includeOfficials = $this->getIncludeOfficials();
    $provider->includeDistricts = $this->getIncludeDistricts();
    $provider->setGuzzleClient($this->getGuzzleClient());
    $provider->setGeocodeProviderClass($this->getGeocodeProviderClass());
    $out = $provider->lookup();
    if ($write && count($out['district']) > 0) {
      // We should end up using the cached results, so for simplicity just re-run
      // the query.
      $provider->processSingleAddress($addressId);
    }
    $result[] = [
      $className => $out
    ];  
  }


}
?>
