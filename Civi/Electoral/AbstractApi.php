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
   * @var bool
   * Search for all states and provinces.
   */
  protected $allStates;
  protected $statesProvinces;
  protected $allCountries;
  protected $countries;
  /**
   * @var bool
   * Search for all counties.
   */
  protected $allCounties;
  protected $counties;
  protected $allCities;
  protected $cities;
  protected $addressLocationType;
  protected $districtTypes;
  protected $apiKey;

  /**
   * @var bool
   * Get district data for upcoming redistricting where applicable.
   */
  protected $includeUpcoming;

  /**
   * @var array
   * The address being operated on (for district lookups).
   * Array contains the following address elements:
   * 'id', 'street_address', 'city', 'state_province_id', 'state_province_id:name', 'state_province.abbreviation', 'contact_id', 'postal_code'
   */
  protected $address;

  /**
   * Constructor class.
   */
  public function __construct(int $limit = 0, bool $update = FALSE) {
    $this->limit = $limit;
    $this->update = $update;
    $this->settingsToProperties();
    return $this;
  }

  /**
   * Starting point for the Electoral.districts API.  Given a number of addresses to look up, finds those without district data
   */
  public function districts() {
    // Set variables.
    $addressesDistricted = $addressesWithErrors = 0;

    $addresses = $this->getAddresses();

    foreach ($addresses as $address) {
      $this->address = $address;
      $districtData = $this->addressDistrictLookup();
      $success = $this->parseDistrictData($districtData);
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

  /**
   * Returns a set of representatives, each represented by a CRM_Electoral_Official object.
   */
  public function reps() : array {
    // Find districts present in Civi that don't have corresponding officials.
    // Get the associated addresses.
    $addresses = $this->getAddressesWithNoAssociatedOfficials();
    // Pass those to an abstract function that's provider-specific.  Get back a list of officials.
    // Write those officials to the database with createOfficial.
  }

  /**
   * Provider-specific lookup for a single address. The contact will get raw district data from the provider for $this->address.
   */
  abstract protected function addressDistrictLookup() : array;

  /**
   * Provider-specific function to parse the results of addressDistrictLookup, including writing to Civi.
   */
  abstract protected function parseDistrictData(array $districtData) : bool;

  /**
   * Get the API key of this data provider.
   */
  abstract protected function getApiKey() : string;

  /**
   * Take the values stored in `civicrm_setting` and populate the corresponding properties.
   */
  private function settingsToProperties() : void {
    // Populate the settings.
    $settings = \Civi\Api4\Setting::get(FALSE)
      ->addSelect('includedStatesProvinces', 'allCounties', 'includedCounties', 'includedCities', 'addressLocationType', 'electoralApiAllStates', 'electoralApiDistrictTypes', 'electoralApiIncludedCountries', 'electoralApiAllCities', 'electoralApiAllCountries', 'electoralApiIncludeRedistricted')
      ->execute()
      ->indexBy('name');

    $this->allCountries = $settings['electoralApiAllCountries']['value'];
    if (!$this->allCountries) {
      $this->countries = $settings['electoralApiIncludedCountries']['value'];
    }
    $this->allStates = $settings['electoralApiAllStates']['value'];
    if (!$this->allStates) {
      $this->statesProvinces = $settings['includedStatesProvinces']['value'];
    }
    $this->allCounties = $settings['allCounties']['value'];
    if (!$this->allCounties) {
      $this->counties = $settings['includedCounties']['value'];
    }
    $this->allCities = $settings['electoralApiAllCities']['value'];
    if (!$this->allCities) {
      $cities = $settings['cities']['value'][0] ?? NULL;
      // Get the "includedCities" setting, trim out space around commas, and put quotation marks in where needed.
      if ($cities) {
        $this->cities = explode(',', preg_replace('/\s*,\s*/', ',', $settings['includedCities']['value']));
      }
    }

    $this->addressLocationType = $settings['addressLocationType']['value'][0];
    $this->districtTypes = $settings['electoralApiDistrictTypes']['value'];
    $this->includeUpcoming = $settings['electoralApiIncludeRedistricted']['value'];
    $this->apiKey = $this->getApiKey();
  }

  /**
   * Write values (typically errors) to the electoral status fields.
   * @param $error
   *   An array consisting of up to three elements, keyed with "code", "reason", and "message".
   * @param $addressId
   *   The address ID.
   */
  protected function writeElectoralStatus(array $error, int $addressId) {
    //Retain the error, so we can filter out the address on future runs until it's corrected
    civicrm_api3('CustomValue', 'create', [
      'entity_id' => $addressId,
      'custom_electoral_status:Error Code' => substr($error['code'], 0, 11),
      'custom_electoral_status:Error Reason' => substr($error['reason'], 0, 255),
      'custom_electoral_status:Error Message' => substr($error['message'], 0, 255),
    ]);
  }

  /**
   * A public function to get a single address' data.  Used for real-time update on postCommit.
   */
  public function singleAddressLookup(int $addressId) : void {
    // This won't return an address if it wouldn't be found by Electoral API settings limiting the address.
    $this->address = $this->getAddresses($addressId)[0] ?? NULL;
    if ($this->address) {
      $districtData = $this->addressDistrictLookup();
      $this->parseDistrictData($districtData);
    }
  }

  /**
   * Helper function to assemble address district query
   * @var int $addressId
   *   If this is set, only consider this particular address ID.
   */
  protected function getAddresses(?int $addressId = NULL) {
    // Use legacy method until is_multiple is supported in APIv4
    // https://lab.civicrm.org/dev/core/-/issues/2842
    return $this->getAddressesLegacy($addressId);

    // Construct the API call to get the addresses.
    $addressQuery = \Civi\Api4\Address::get(FALSE)
      ->addSelect('id', 'street_address', 'city', 'state_province_id', 'state_province_id:name', 'state_province.abbreviation', 'contact_id', 'postal_code', 'country_id:name')
      ->setGroupBy(['contact_id'])
      ->addWhere('street_address', 'IS NOT NULL')
      ->addWhere('contact.is_deceased', '!=', TRUE)
      ->addWhere('contact.is_deleted', '!=', TRUE)
      ->addOrderBy('contact_id', 'DESC')
      ->setLimit($this->limit);

    if ($this->countries) {
      $addressQuery->addWhere('country_id', 'IN', $this->countries);
    }
    if ($this->statesProvinces) {
      $addressQuery->addWhere('state_province_id', 'IN', $this->statesProvinces);
    }
    if ($this->counties) {
      $addressQuery->addWhere('county_id', 'IN', $this->counties);
    }
    if ($this->cities) {
      // This is sanitized above.
      $addressQuery->addWhere('city', 'IN', $this->cities);
    }
    // "0" means the location type is "primary".
    if ($this->addressLocationType == 0) {
      $addressQuery->addWhere('is_primary', '=', TRUE);
    }
    else {
      $addressQuery->addWhere('location_type_id', '=', $this->addressLocationType);
    }
    if ($addressId) {
      $addressQuery->addWhere('id', '=', $addressId);
    }
    if (!$this->update) {
      $addressQuery->addWhere('electoral_status.electoral_status_error_code', 'IS NULL');
      $addressQuery->addWhere('electoral_districts.electoral_level', 'IS NULL');
    }
    // Let 'er rip.
    $addresses = $addressQuery->execute();
    return $addresses;
  }

  /**
   * Legacy helper for versions of CiviCRM prior to fixing
   * this issue: https://lab.civicrm.org/dev/core/-/issues/2842
   */
  protected function getAddressesLegacy(?int $addressId = NULL) {
    $sql =  '
      SELECT
        `a`.`id` AS `id`, `a`.`street_address` AS `street_address`,
        `a`.`city` AS `city`, `a`.`state_province_id` AS `state_province_id`,
        `state_province`.`name` AS `state_province_name`,
        `state_province`.`abbreviation` AS `state_province_abbreviation`,
        `a`.`contact_id` AS `contact_id`, `a`.`postal_code` AS `postal_code`,
        `country`.`iso_code` AS `country_id_name`
      FROM
        civicrm_address a
          INNER JOIN `civicrm_contact` `contact`
            ON `a`.`contact_id` = `contact`.`id`
          LEFT JOIN `civicrm_state_province` `state_province`
            ON a.state_province_id =  state_province.id
          LEFT JOIN `civicrm_country` `country`
            ON a.country_id =  country.id
          LEFT JOIN `civicrm_value_electoral_status_27` `electoral_status`
            ON a.id =  electoral_status.entity_id
          LEFT JOIN `civicrm_value_electoral_districts_26` `electoral_districts`
            ON contact.id =  electoral_districts.entity_id
      WHERE
        ( `a`.`street_address` IS NOT NULL)
        AND
        (`contact`.`is_deceased` != "1")
        AND
        (`contact`.`is_deleted` != "1")
    ';

    $whereClauses = [];
    if ($this->countries) {
      $countries = $this->countries;
      array_walk($countries, 'intval');
      $countries = implode(',', $countries);
      $whereClauses[] = '(`a`.`country_id` IN (' . $countries . '))';
    }
    if ($this->statesProvinces) {
      $statesProvinces = $this->statesProvinces;
      array_walk($statesProvinces, 'intval');
      $statesProvinces = implode(',', $statesProvinces);
      $whereClauses[] = '(`a`.`state_province_id` IN (' . $statesProvinces . '))';
    }
    if ($this->counties) {
      $counties = $this->counties;
      array_walk($counties, 'intval');
      $counties = implode(',', $counties);
      $whereClauses[] = '(`a`.`county_id` IN (' . $counties . '))';
    }
    if ($this->cities) {
      $cities = $this->cities;
      array_walk($cities, 'addslashes');
      $cities = '"' . implode('","', $cities) . '"';
      $whereClauses[] = '(`a`.`city` IN (' . $cities . '))';
    }
    // "0" means the location type is "primary".
    if ($this->addressLocationType == 0) {
      $whereClauses[] = '(`a`.`is_primary` = "1")';
    }
    else {
      $whereClauses[] = '(`a`.`location_type_id` = ' . intval($this->addressLocationType). ')';
    }
    if ($addressId) {
      $whereClauses[] = '(`a`.`id` = ' . intval($addressId) . ')';
    }
    if (!$this->update) {
      $whereClauses[] = '(`electoral_status`.`electoral_status_error_code` IS NULL)';
      $whereClauses[] = '(`electoral_districts`.`electoral_districts_level` IS NULL)';
    }

    if (count($whereClauses) > 0) {
      $sql .= ' AND ' . implode(' AND ', $whereClauses);

    }
    $sql .= ' GROUP BY `a`.`contact_id` ORDER BY `a`.`contact_id` DESC';

    if ($this->limit) {
      $sql .= ' LIMIT 1500 OFFSET 0';
    }

    $dao = \CRM_Core_DAO::executeQuery($sql);
    $addresses = [];
    while($dao->fetch()) {
      $addresses[] = [
        'id' => $dao->id,
        'street_address' => $dao->street_address,
        'city' => $dao->city,
        'state_province_id' => $dao->state_province_id,
        'state_province_id:name' => $dao->state_province_name,
        'state_province_id:abbreviation' => $dao->state_province_abbreviation,
        'contact_id' => $dao->contact_id,
        'postal_code' => $dao->postal_code,
        'country_id:name' => $dao->country_id_name,
      ];
    }
    return $addresses;
  }
  /**
   * Helper function to create or update electoral districts custom data
   */
  protected function writeDistrictData($contactId, $level, $stateProvinceId = '', $countyId = NULL, $city = NULL, $chamber = NULL, $district = NULL, $inOffice = FALSE, $officeName = NULL, $note = NULL, $valid_from = NULL, $valid_to = NULL, $ocd_id = NULL) : void {
    (new \DateTime('now'))->format('Y-m-d');
    //Check if this level exists already
    $contactEdExists = $this->districtDataExists($contactId, "$level", "$chamber", $countyId, $city, $valid_to);
    if ($contactEdExists['count'] == 1) {
      $edTableNameId = $this->getDistrictTableNameId();
      $edId = $contactEdExists['values'][$contactId][$edTableNameId];
      $record = \Civi\Api4\CustomValue::update('electoral_districts', FALSE)->addWhere('id', '=', $edId);
    }
    else {
      $record = \Civi\Api4\CustomValue::create('electoral_districts', FALSE);
    }

    $record
      ->addValue('entity_id', $contactId)
      ->addValue('electoral_level', $level)
      ->addValue('electoral_states_provinces', $stateProvinceId)
      ->addValue('electoral_counties', $countyId)
      ->addValue('electoral_cities', $city)
      ->addValue('electoral_chamber', $chamber)
      ->addValue('electoral_district', $district)
      // This needs to be a string - see core #2461.
      ->addValue('electoral_in_office', (string) $inOffice)
      ->addValue('electoral_note', $note)
      ->addValue('electoral_modified_date', (new \DateTime('now'))->format('Y-m-d H:i:s'))
      ->addValue('electoral_ocd_id_district', $ocd_id);
    if ($valid_from) {
      $record->addValue('electoral_valid_from', $valid_from);
    }
    if ($valid_to) {
      $record->addValue('electoral_valid_to', $valid_to);
    }
    $record->execute();
  }

  /**
   * Helper function to check if Electoral Districts custom data already exists
   * FIXME: This would be a LOT more efficient in API4.
   */
  private function districtDataExists($contactId, $level, $chamber = NULL, $county = NULL, $city = NULL, $valid_to = NULL) {
    $edExistsParams = [
      'return' => "id",
      'id' => $contactId,
    ];
    $edLevelId = civicrm_api3('CustomField', 'getvalue', ['return' => "id", 'custom_group_id' => "electoral_districts", 'name' => "electoral_level"]);
    $edLevelField = 'custom_' . $edLevelId;
    $edExistsParams[$edLevelField] = "$level";
    if (!empty($chamber)) {
      $edChamberId = civicrm_api3('CustomField', 'getvalue', ['return' => "id", 'custom_group_id' => "electoral_districts", 'name' => "electoral_chamber"]);
      $edChamberField = 'custom_' . $edChamberId;
      $edExistsParams[$edChamberField] = "$chamber";
    }
    if (!empty($county)) {
      $edCountyId = civicrm_api3('CustomField', 'getvalue', ['return' => "id", 'custom_group_id' => "electoral_districts", 'name' => "electoral_counties"]);
      $edCountyField = 'custom_' . $edCountyId;
      $edExistsParams[$edCountyField] = "$county";
    }
    if (!empty($city)) {
      $edCityId = civicrm_api3('CustomField', 'getvalue', ['return' => "id", 'custom_group_id' => "electoral_districts", 'name' => "electoral_cities"]);
      $edCityField = 'custom_' . $edCityId;
      $edExistsParams[$edCityField] = "$city";
    }
    if (!empty($valid_to)) {
      $edValidToId = civicrm_api3('CustomField', 'getvalue', ['return' => "id", 'custom_group_id' => "electoral_districts", 'name' => "electoral_valid_to"]);
      $edValidToField = 'custom_' . $edValidToId;
      $edExistsParams[$edValidToField] = "$valid_to";
    }
    $edExists = civicrm_api3('Contact', 'get', $edExistsParams);

    return $edExists;
  }

  /**
   * Helper function to get the table id
   * of the Electoral Districts custom table
   */
  private function getDistrictTableNameId() : string {
    $edTableName = civicrm_api3('CustomGroup', 'getvalue', ['return' => "table_name", 'name' => "electoral_districts"]);
    return $edTableName . "_id";
  }

  private function getAddressesWithNoAssociatedOfficials() {
    \Civi\Api4\CustomField::get(FALSE)
      ->addWhere('name', 'IN', ['electoral_ocd_id_district', 'electoral_ocd_id_official']);
  }

}
