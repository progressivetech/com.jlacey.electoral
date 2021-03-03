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
  /**
   * @var bool
   * Search for all counties.
   */
  protected $allCounties;
  protected $counties;
  protected $cities;
  protected $addressLocationType;
  protected $districtTypes;
  protected $nonlegislativeDistricts;
  protected $apiKey;

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
  public function __construct(int $limit, bool $update) {
    $this->limit = $limit;
    $this->update = $update;
    $this->settingsToProperties();
    return $this;
  }

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

  abstract public function reps();

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
      ->addSelect('includedStatesProvinces', 'allCounties', 'includedCounties', 'includedCities', 'addressLocationType', 'electoralApiAllStates', 'electoralApiDistrictTypes', 'electoralApiNonlegislativeDistricts')
      ->execute()
      ->indexBy('name');

    $this->allStates = $settings['electoralApiAllStates']['value'];
    if (!$this->allStates) {
      $this->statesProvinces = $settings['includedStatesProvinces']['value'];
    }
    $this->allCounties = $settings['allCounties']['value'];
    if (!$this->allCounties) {
      $this->counties = $settings['includedCounties']['value'];
    }

    $cities = $settings['cities']['value'][0];
    // Get the "includedCities" setting, trim out space around commas, and put quotation marks in where needed.
    if ($cities) {
      $this->cities = explode(',', preg_replace('/\s*,\s*/', ',', $settings['includedCities']['value']));
    }

    $this->addressLocationType = $settings['addressLocationType']['value'][0];
    $this->districtTypes = $settings['electoralApiDistrictTypes']['value'];
    $this->nonlegislativeDistricts = $settings['electoralApiNonlegislativeDistricts']['value'][0];
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
   * Helper function to assemble address district query
   */
  protected function getAddresses() {
    // Construct the API call to get the addresses.
    $addressQuery = \Civi\Api4\Address::get(FALSE)
      ->addSelect('id', 'street_address', 'city', 'state_province_id', 'state_province_id:name', 'state_province.abbreviation', 'contact_id', 'postal_code')
      ->setGroupBy(['id'])
      ->addWhere('street_address', 'IS NOT NULL')
      ->addWhere('country_id:name', '=', 'US')
      ->addWhere('contact.is_deceased', '!=', TRUE)
      ->addWhere('contact.is_deleted', '!=', TRUE)
      ->addOrderBy('id', 'DESC')
      ->setLimit($this->limit);

    if ($this->cities) {
      // This is sanitized above.
      $addressQuery->addWhere('street_address', 'IN', $this->cities);
    }
    if ($this->statesProvinces) {
      $addressQuery->addWhere('state_province_id', 'IN', $this->statesProvinces);
    }
    if ($this->counties) {
      $addressQuery->addWhere('county_id', 'IN', $this->counties);
    }
    // "9" means the location type is "primary".
    if ($this->addressLocationType == 0) {
      $addressQuery->addWhere('is_primary', '=', TRUE);
    }
    else {
      $addressQuery->addWhere('location_type_id', '=', $this->addressLocationType);
    }
    if (!$this->update) {
      $addressQuery->addWhere('electoral_status.electoral_status_error_code', 'IS NULL');
    }
    // Let 'er rip.
    $addresses = $addressQuery->execute();
    return $addresses;
  }

  /**
   * Helper function to create or update electoral districts custom data
   */
  protected function writeDistrictData($contactId, $level, $stateProvinceId = NULL, $countyId = NULL, $city = NULL, $chamber = NULL, $district = NULL, $inOffice = 0, $officeName = NULL) : void {
    //Check if this level exists already
    $contactEdExists = $this->districtDataExists($contactId, "$level", "$chamber", $countyId, $city);
    if ($contactEdExists['count'] == 1) {
      //Get the custom value set id
      $edTableNameId = $this->getDistrictTableNameId();
      $edId = $contactEdExists['values'][$contactId][$edTableNameId];
      //Update
      civicrm_api3('CustomValue', 'create', [
        'entity_id' => $contactId,
        "custom_electoral_districts:Level:$edId" => "$level",
        "custom_electoral_districts:States/Provinces:$edId" => "$stateProvinceId",
        "custom_electoral_districts:County:$edId" => "$countyId",
        "custom_electoral_districts:City:$edId" => "$city",
        "custom_electoral_districts:Chamber:$edId" => "$chamber",
        "custom_electoral_districts:District:$edId" => "$district",
        "custom_electoral_districts:In office?:$edId" => $inOffice,
        "custom_electoral_districts:Office:$edId" => $officeName,
      ]);
    }
    else {
      //Create
      civicrm_api3('CustomValue', 'create', [
        'entity_id' => $contactId,
        'custom_electoral_districts:Level' => "$level",
        'custom_electoral_districts:States/Provinces' => "$stateProvinceId",
        "custom_electoral_districts:County" => "$countyId",
        "custom_electoral_districts:City" => "$city",
        'custom_electoral_districts:Chamber' => "$chamber",
        'custom_electoral_districts:District' => "$district",
        'custom_electoral_districts:In office?' => $inOffice,
        'custom_electoral_districts:Office' => $officeName,
      ]);
    }
  }

  /**
   * Helper function to check is Electoral Districts custom data already exists
   */
  private function districtDataExists($contactId, $level, $chamber = NULL, $county = NULL, $city = NULL) {
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
    if (!empty($county)) {
      $edCityId = civicrm_api3('CustomField', 'getvalue', ['return' => "id", 'custom_group_id' => "electoral_districts", 'name' => "electoral_cities"]);
      $edCityField = 'custom_' . $edCityId;
      $edExistsParams[$edCityField] = "$city";
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

}
