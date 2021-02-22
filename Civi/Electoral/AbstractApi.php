<?php

namespace Civi\Electoral;

abstract class AbstractApi {

  /**
   * @var int
   * How many records to update at once.
   */
  private $limit;

  /**
   * @var bool
   * Overwrite existing records' electoral data.
   */
  private $update;

  /**
   * Constructor class.
   */
  public function __construct(int $limit, bool $update) {
    $this->limit = $limit;
    $this->update = $update;
    return $this;
  }

  public function districts() {
    // Set variables.
    $addressesDistricted = $addressesWithErrors = 0;

    $addresses = $this->getAddresses();

    foreach ($addresses as $address) {
      $success = $this->addressDistrictLookup($address);
      if ($success) {
        $addressesDistricted++;
      }
      else {
        $addressesWithErrors++;
      }
    }

    $edDistrictReturn = "$addressesDistricted addresses districted.";
    if ($addressesWithErrors > 0) {
      $edDistrictReturn .= " $addressesWithErrors addresses with errors.";
    }
    return $edDistrictReturn;
  }

  abstract public function reps();

  /**
   * Provider-specific lookup for a single address.  Give an address, the contact will get districts.
   * Array contains the address id, street_address, city, state, and contact_id.
   */
  abstract protected function addressDistrictLookup(array $address);

  /**
   * Helper function to assemble address district query
   */
  protected function getAddresses() {
    // Populate the settings.
    $settings = \Civi\Api4\Setting::get(FALSE)
      ->addSelect('includedStatesProvinces', 'allCounties', 'includedCounties', 'includedCities', 'addressLocationType')
      ->execute()
      ->indexBy('name');

    $includedStatesProvinces = $settings['includedStatesProvinces']['value'];
    $allCounties = $settings['allCounties']['value'];
    if (!$allCounties) {
      $counties = $settings['includedCounties']['value'];
    }

    $cities = $settings['cities']['value'][0];
    // Get the "includedCities" setting, trim out space around commas, and put quotation marks in where needed.
    if ($cities) {
      $cities = explode(',', preg_replace('/\s*,\s*/', ',', $settings['includedCities']['value']));
    }

    //Location Types
    $addressLocationType = $settings['addressLocationType']['value'][0];

    // Construct the API call to get the addresses.
    $addressQuery = \Civi\Api4\Address::get(FALSE)
      ->addSelect('id', 'street_address', 'city', 'state_province_id:name', 'contact_id')
      ->setGroupBy(['id'])
      ->addWhere('street_address', 'IS NOT NULL')
      ->addWhere('state_province_id', 'IN', $includedStatesProvinces)
      ->addWhere('country_id:name', '=', 'US')
      ->addWhere('contact.is_deceased', '!=', TRUE)
      ->addWhere('contact.is_deleted', '!=', TRUE)
      ->addOrderBy('id', 'DESC')
      ->setLimit($this->limit);

    if ($cities) {
      // This is sanitized above.
      $addressQuery->addWhere('street_address', 'IN', $cities);
    }
    if ($counties) {
      $addressQuery->addWhere('county_id', 'IN', $counties);
    }
    if ($addressLocationType == 0) {
      $addressQuery->addWhere('is_primary', '=', TRUE);
    }
    else {
      $addressQuery->addWhere('location_type_id', '=', $addressLocationType);
    }
    if (!$this->update) {
      $addressQuery->addWhere('electoral_status.electoral_status_error_code', 'IS NULL');
    }
    // Let 'er rip.
    $addresses = $addressQuery->execute();
    return $addresses;
  }

}
