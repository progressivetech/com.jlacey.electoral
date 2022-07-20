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
   * The address being operated on.
  */
  protected $address;

  /**
   * @var object
   *
   * guzzleClient - by allowing us to inject a guzzle client
   * we can more effectively run automated test.
   */
  protected $guzzleClient;

  /**
   * @var str
   *
   * geocodeProviderClass - by allowing us to define the 
   * geocodeProvider class we can more easily run automated
   * tests.
   */
  protected $geocodeProviderClass;

  /**
   * Constructor class.
   */
  public function __construct(int $limit = 0, bool $update = FALSE) {
    $this->limit = $limit;
    $this->update = $update;
    $this->settingsToProperties();
    return $this;
  }

  public function setAddress($address) {
    $this->address = $address;
  }

  // Enable test suite to inject a mock guzzle client.
  public function setGuzzleClient($guzzleClient) {
    $this->guzzleClient = $guzzleClient;
  }

  protected function getGuzzleClient() {
    if (is_null($this->guzzleClient)) {
      $this->guzzleClient = new \GuzzleHttp\Client();
    }
    return $this->guzzleClient;
  }

  // Enable test suite to inject a mock geocode provider
  // class name.
  public function setGeocodeProviderClass($class) {
    $this->geocodeProviderClass = $class;
  }

  protected function getGeocodeProviderClass() {
    if (is_null($this->geocodeProviderClass)) {
      return \CRM_Utils_GeocodeProvider::getUsableClassName();
    }
    return $this->geocodeProviderClass;
  }

  /**
   *
   * Starting point for the Electoral.districts API.  Given a number of
   * addresses to look up, finds those without district data
   *
   */
  public function processBatch() {
    // Set variables.
    $totalAddresses = $totalProcessed = 0;
    $addresses = $this->getAddresses();
    foreach ($addresses as $address) {
      $totalAddress++;
      $this->address = $address;
      $data = $this->lookup();
      if ($data) {
        $this->writeData($data);
        $totalProcessed++;
      }
    }
    return "$totalAddresses addresses found. $totalProcessed addresses processed.";
  }

  /**
   *
   * Provider-specific lookup for a single address.
   *
   * Returns an array of processed district data keyed to "district" and an
   * array of official data keyed to "official" returned by the address
   * set.
   *
   */
  abstract public function lookup() : array;

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
      ->addSelect(
        'includedStatesProvinces', 
        'allCounties', 
        'includedCounties', 
        'includedCities', 
        'addressLocationType', 
        'electoralApiAllStates', 
        'electoralApiDistrictTypes', 
        'electoralApiIncludedCountries', 
        'electoralApiAllCities', 
        'electoralApiAllCountries', 
        'electoralApiIncludeRedistricted')
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
   */
  protected function writeElectoralStatus(array $error) {
    //Retain the error, so we can filter out the address on future runs until it's corrected
    $addressId = $this->address['id'] ?? NULL;
    if ($addressId) {
      civicrm_api3('CustomValue', 'create', [
        'entity_id' => $this->address['id'],
        'custom_electoral_status:Error Code' => substr($error['code'], 0, 11),
        'custom_electoral_status:Error Reason' => substr($error['reason'], 0, 255),
        'custom_electoral_status:Error Message' => substr($error['message'], 0, 255),
      ]);
    }
  }

  /**
   *
   * processSingleAddress
   *
   * A public function to process a single address' data.  Used for real-time
   * update on postCommit. Writes district data for the contact and, if configured
   * inserts elected officials as well.
   *
   * @var int addressId - the addressId to process.
   *
   */
  public function processSingleAddress(int $addressId) : void {
    // This won't return an address if it wouldn't be found by Electoral API settings limiting the address.
    $address = $this->getAddresses($addressId)[0] ?? NULL;
    if ($address) {
      $this->address = $address;
      $data = $this->lookup();
      $this->writeData($data);
    }
  }

  /**
   * writeData
   *
   * Given data from the address lookup, write the results to the
   * database.
   */
  protected function writeData(array $data): void {
    foreach ($data['district'] as $district) {
      $this->writeDistrictData($district);
    }
    if (\Civi::settings()->get('electoralApiCreateOfficialOnDistrictLookup')) {
      foreach ($data['official'] as $official) {
        $official->createOfficial();
      }
    }
  }

  /** 
   * Normalize address
   *
   * Update $this->address to ensure it has all the fields returned by
   * getAddresses, e.g. state province and country are provided along with
   * their respective ids and abbreviations.
   *
   */

  protected function normalizeAddress() {
    $normalized = [
      'id' => NULL,
      'street_address' => NULL,
      'city' => NULL,
      'state_province_id' => NULL,
      'state_province_id.name' => NULL,
      'state_province_id.abbreviation' => NULL,
      'contact_id' => NULL, 
      'postal_code' => NULL,
      'country_id.name' => NULL,
      'geo_code_1' => NULL,
      'geo_code_2' => NULL
    ];

    // Populate our normalized with the initial values.
    foreach ($normalized as $key => $value) {
      $normalized[$key] = $this->address[$key] ?? NULL;
    }

    // Check for country.
    if (empty($normalized['country_id.name'])) {
      if (isset($this->address['country'])) {
        // Let's ensure it's a valid country.
        $countryResult = \Civi\Api4\Country::get()
          ->setCheckPermissions(FALSE)
          ->addClause('OR',
            ['name', '=', $this->address['country'] ],
            ['iso_code', '=', $this->address['country'] ]
          )
          ->execute();
        if ($countryResult->count() == 1) {
          $normalized['country_id.name'] = $this->address['country'];
        }
        else {
          throw new \Exception("Unknown country: " . $this->address['country']);
        }
      }
      else {
        // Use the default country.
        $normalized['country_id.name'] = \Civi\Api4\Setting::get()
          ->setCheckPermissions(FALSE)
          ->addSelect('defaultContactCountry:name')
          ->execute()->first()['value'];
      }
    } 
    // Check for state province lookup fields. All of these fields should be
    // populated. If they are not populated and we have a state_province set,
    // then we'll lookup the values.
    $stateProvinceFields = [
      'state_province_id',
      'state_province_id.name',
      'state_province_id.abbreviation',
    ];
    if (isset($this->address['state_province'])) {
      $stateProvinceLookup = FALSE;
      foreach ($stateProvinceFields as $field) {
        $value = $this->address[$field] ?? NULL;
        if (empty($value)) {
          $stateProvinceLookup = TRUE;
        }
      }
      if ($stateProvinceLookup) {
        $stateProvince = \Civi\Api4\StateProvince::get()
          ->setCheckPermissions(FALSE)
          ->addSelect('id', 'abbreviation', 'name')
          ->addClause(
            'OR', 
            [ 'name', '=', $this->address['state_province']],
            [ 'abbreviation', '=', $this->address['state_province']]
          )
          ->addWhere('country_id.name', '=', $normalized['country_id.name'])
          ->execute()->first();
        if ($stateProvince) {
          $normalized['state_province_id'] = $stateProvince['id'];
          $normalized['state_province_id.abbreviation'] = $stateProvince['abbreviation'];
          $normalized['state_province_id.name'] = $stateProvince['name'];

        }
        else {
          throw new \Exception("Unknown state province: " . $this->address['state_province'] . " in country: " . $normalized['country_id.name']);
        }
      }
    }
    $this->address = $normalized;
  }

  /**
   * Helper function to assemble address district query
   * @var int $addressId
   *   If this is set, only consider this particular address ID.
   */
  public function getAddresses(?int $addressId = NULL) {
    // Construct the API call to get the addresses.
    $addressQuery = \Civi\Api4\Address::get(FALSE)
      ->addSelect(
        'id', 
        'street_address', 
        'city', 
        'state_province_id', 
        'state_province_id.name', 
        'state_province_id.abbreviation', 
        'contact_id', 
        'postal_code', 
        'country_id.name', 
        'geo_code_1', 
        'geo_code_2')
      ->addJoin('Custom_electoral_districts AS custom_electoral_districts', 'LEFT', ['custom_electoral_districts.entity_id', '=', 'contact_id'])
      ->setGroupBy(['contact_id'])
      ->addWhere('street_address', 'IS NOT NULL')
      ->addWhere('contact_id.is_deceased', '!=', TRUE)
      ->addWhere('contact_id.is_deleted', '!=', TRUE)
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
      $addressQuery->addWhere('custom_electoral_districts.electoral_level', 'IS NULL');
    }
    // Let 'er rip.
    $addresses = $addressQuery->execute();
    return $addresses;
  }

  /**
   * Helper function to create or update electoral districts custom data
   */
  protected function writeDistrictData($data) : void {
    //Check if this level exists already
    $contactEdExists = $this->districtDataExists($data);
    
    if ($contactEdExists['count'] == 1) {
      $edTableNameId = $this->getDistrictTableNameId();
      $edId = $contactEdExists['values'][$data['contactId']][$edTableNameId];
      $record = \Civi\Api4\CustomValue::update('electoral_districts', FALSE)->addWhere('id', '=', $edId);
    }
    else {
      $record = \Civi\Api4\CustomValue::create('electoral_districts', FALSE);
    }

    $record
      ->addValue('entity_id', $data['contactId'])
      ->addValue('electoral_level', $data['level'])
      ->addValue('electoral_states_provinces', $data['stateProvinceId'] ?? NULL)
      ->addValue('electoral_counties', $data['countyId'] ?? NULL)
      ->addValue('electoral_cities', $data['city'] ?? NULL)
      ->addValue('electoral_chamber', $data['chamber'] ?? NULL)
      ->addValue('electoral_district', $data['district'] ?? NULL)
      // This needs to be a string - see core #2461.
      ->addValue('electoral_in_office', (string) $data['inOffice'] ?? NULL)
      ->addValue('electoral_note', $data['note'] ?? NULL)
      ->addValue('electoral_modified_date', (new \DateTime('now'))->format('Y-m-d H:i:s'))
      ->addValue('electoral_ocd_id_district', $data['ocd_id'] ?? NULL)
      ->addValue('electoral_valid_from', $data['valid_from'] ?? NULL)
      ->addValue('electoral_valid_to', $data['valid_to'] ?? NULL)
      ->execute();
  }

  /**
   * Helper function to check if Electoral Districts custom data already exists
   * FIXME: This would be a LOT more efficient in API4.
   */
  private function districtDataExists($data) {
    $contactId = $data['contactId'] ?? NULL;
    $level = $data['level'] ?? NULL;
    $chamber = $data['chamber'] ?? NULL;
    $county = $data['county'] ?? NULL; 
    $city = $data['city'] ?? NULL; 
    $valid_to = $data['valid_to'] ?? NULL;
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

  /**
   * Set geo coordinates for address.
   *
   */
  protected function setGeoCoordinates(): void {
    if ($this->address['geo_code_1'] && $this->address['geo_code_2']) {
      // Nothing to do.
      return;
    }
    // Try to do a lookup
    $class = $this->getGeocodeProviderClass();
    if (empty($class)) {
      // No geocode method set.
      throw new \Exception("Failed to find usable geocoding class.");
    }

    $params = array(
      // Country must be United States for the API to work.
      'country' => 'United States',
      'street_address' => $this->address['street_address'],
      'city' => $this->address['city'],
      'state_province_id' => $this->address['state_province_id'],
      'postal_code' => $this->address['postal_code'],
    );
    $success = $class::format($params);
    if (!$success || empty($params['geo_code_1']) || empty($params['geo_code_2'])) {
      \Civi::log()->debug("Failed to lookup geo info using $class.");
      return;
    }
    $this->address['geo_code_1'] = $params['geo_code_1'];
    $this->address['geo_code_2'] = $params['geo_code_2'];
  }

  /**
   * Parse Name
   *
   * Helper function to break a single name into
   * first, middle and last name.
   *
   * @var str name
   * @return array keyed to first_name, middle_name, last_name
   */
  protected function parseName($name) {
    $namePieces = explode(' ', $name);
    $count = count($namePieces);
    $names = [
      'first_name' => NULL,
      'middle_name' => NULL,
      'last_name' => NULL,
    ];
    if ($count == 0) {
      return $names;
    }
    elseif($count == 1) {
      $names['first_name'] = $namePieces[0];
    }
    elseif($count == 2) {
      $names['first_name'] = $namePieces[0];
      $names['last_name'] = $namePieces[1];
    }
    elseif($count == 3) {
      // Probably not right in many cases but what else to do?
      $names['first_name'] = $namePieces[0];
      $names['middle_name'] = $namePieces[1];
      $names['last_name'] = $namePieces[2];
    }
    else {
      // Now we will just flail.
      $names['last_name'] = array_pop($namePieces);
      $names['middle_name'] = array_pop($namePieces);
      $names['first_name'] = implode(' ', $namePieces);
    }
    return $names;
  }

}
