<?php

namespace Civi\Electoral;
use CRM_Electoral_ExtensionUtil as E;

abstract class AbstractApi {

  /**
   * @var int
   * How many records to update at once.
   */
  public $limit = 0;

  /**
   * @var bool
   * Overwrite existing records' electoral data.
   */
  public $update = FALSE;

  /**
   * @var bool
   * Cache lookups so we don't repeatedly hit the upstream API
   */
  public $cache = FALSE;

  /**
   * @var string
   * Limit addresses to contacts in the given pipe (|) 
   * separated list of group titles.
   */
  public $groups = '';

  /**
   * The following properties are stored in settings and
   * populated from settings when initialized. 
   */
  protected $allStates;
  protected $statesProvinces;
  protected $allCountries;
  protected $countries;
  protected $allCounties;
  protected $counties;
  protected $allCities;
  protected $cities;

  protected $addressLocationType;
  protected $districtTypes;
  protected $apiKey;

  /**
   * @var string 
   * Get future date for query.
   */
  protected $futureDate;

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
   * delay
   *
   * Set the minimum delay between requests. Useful if the API
   * provider has restrictions on number of requests per minute.
   * For example, set to 2 to ensure there's a minimum of 2 seconds
   * between requests, which will restrict to 30 requests per minute.
   * Set to 0 to indicate no delay.
   */
  protected $delay = 0;

  /**
   * @var array
   *
   * results hold an array indicating the status of the
   * given request. It's keys are: 
   *  status => (success|failure)
   *  message =>  the error message if it's an error.
  */
  protected $results = [];

  /**
   * includeOfficials 
   *
   * Should info about the elected officials representing the district be
   * included? With some providers (cicero) this will require an extra query
   * and an extra cost. This switch is only exposed via the API so extensions
   * such as the petitionemail extension can use it. 
   */
  public $includeOfficials = FALSE;

  /**
   * includeDistricts
   *
   * Whether or not district info should be included. Only available via API. This is
   * useful if you only want officials and don't want to pay for district lookups. Keep
   * in mind that futureDate won't work with officials, only districts.
   */
  public $includeDistricts = TRUE;

  /**
   * Constructor class.
   */
  public function __construct(int $limit = 0, bool $update = FALSE, bool $cache = FALSE, string $groups = '') {
    $this->limit = $limit;
    $this->update = $update;
    $this->cache = $cache;
    $this->groups = $groups;
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
    $lastLookupCompleted = NULL;
    $sleepTime = 0;
    foreach ($addresses as $address) {
      if ($this->delay && $lastLookupCompleted) {
        $elapsed = time() - $lastLookupCompleted;
        if ($elapsed < $this->delay) {
          $remaining = $this->delay - $elapsed;
          $sleepTime += $remaining;
          sleep($remaining);
        }
      }
      $totalAddresses++;
      $this->address = $address;
      $data = $this->lookup();
      $lastLookupCompleted = time();
      if ($data['district']) {
        $this->writeData($data);
        $totalProcessed++;
      }
      // Always write electoral status.
      $this->writeElectoralStatus();
    }
    return "$totalAddresses addresses found. $totalProcessed addresses processed, $sleepTime seconds spent sleeping.";
  }


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
        'electoralApiFutureDate')
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
      $cities = $settings['includedCities']['value'][0] ?? NULL;
      // Get the "includedCities" setting, trim out space around commas, and put quotation marks in where needed.
      if ($cities) {
        $this->cities = explode(',', preg_replace('/\s*,\s*/', ',', $settings['includedCities']['value']));
      }
    }

    $this->addressLocationType = $settings['addressLocationType']['value'][0];
    $this->districtTypes = $settings['electoralApiDistrictTypes']['value'];
    $futureDate = $settings['electoralApiFutureDate']['value'];
    if ($futureDate) {
      $timestamp = strtotime($futureDate);
      if ($timestamp && $timestamp > time()) {
        $this->futureDate = $futureDate;
      }
      else {
        \Civi::log()->debug("Warning: future date invalid or in the past: " . $futureDate);
      }
    }
    $this->apiKey = $this->getApiKey();
  }

  /**
   * Write values (typically errors) to the electoral status fields.
   * @param $error
   *   An array consisting of up to three elements, keyed with "status" (success or failure) and "message".
   */
  protected function writeElectoralStatus() {
    //Retain the error, so we can filter out the address on future runs until it's corrected
    $contactId = $this->address['contact_id'] ?? NULL;
    $status = $this->results['status'] ?? NULL;
    $message = $this->results['message'] ?? NULL;
    if ($contactId && $status) {
      \Civi\Api4\Contact::update()
        ->setCheckPermissions(FALSE)
        ->addWhere('id', '=', $contactId)
        ->addValue('electoral_update_status.electoral_last_status', $status)
        ->addValue('electoral_update_status.electoral_error_message', substr($message, 0, 2048))
        ->addValue('electoral_update_status.electoral_last_updated', date('Y-m-d H:i:s'))
        ->execute();
    }
  }

  /**
   *
   * processSingleAddress
   *
   * A public function to process a single address' data.  Used for real-time
   * update on postCommit. Writes district data for the contact.
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
    $this->writeElectoralStatus();
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
      'country_id' => NULL,
      'country_id.name' => NULL,
      'county_id' => NULL,
      'county_id.name' => NULL,
      'geo_code_1' => NULL,
      'geo_code_2' => NULL
    ];

    // Populate our normalized with the initial values.
    foreach ($normalized as $key => $value) {
      $normalized[$key] = $this->address[$key] ?? NULL;
    }

    // Check for country.
    if (empty($normalized['country_id.name'])) {
      if ($this->address['country'] ?? NULL) {
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
          $normalized['country_id'] = $countryResult->first()['id'];
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
        $normalized['country_id'] = \Civi\Api4\Setting::get()
          ->setCheckPermissions(FALSE)
          ->addSelect('defaultContactCountry')
          ->execute()->first()['value'];
      }
    } 
    // If we don't have a state_province_id.name, we have to look it up.
    if (empty($normalized['state_province_id.name'])) {
      $stateProvince = NULL;
      if ($this->address['state_province'] ?? NULL) {
        // We have been passed a bare state province name. Lookup the details in
        // the state province table.
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
      }
      elseif ($normalized['state_province_id']) {
        // We have been passed a state province id. Lookup the details in
        // the state province table.
        $stateProvince = \Civi\Api4\StateProvince::get()
            ->setCheckPermissions(FALSE)
            ->addSelect('id', 'abbreviation', 'name')
            ->addWhere('id', '=', $normalized['state_province_id'])
            ->execute()->first();
      }
      if ($stateProvince) {
        $normalized['state_province_id'] = $stateProvince['id'];
        $normalized['state_province_id.abbreviation'] = $stateProvince['abbreviation'];
        $normalized['state_province_id.name'] = $stateProvince['name'];
      }
    }
    // We only need county_id so we know if we should reject this address based
    // on county_id restrictions.
    if (empty($normalized['county_id'])) {
      $county = NULL;
      if (isset($this->address['county'])) {
        // We have been passed a bare county name. Lookup the details in
        // the county table.
        $county = \Civi\Api4\County::get()
          ->setCheckPermissions(FALSE)
          ->addSelect('id')
          ->addWhere('name', '=', $this->address['county'])
          ->addWhere('state_province_id', '=', $normalized['state_province_id'])
          ->execute()->first();
      }
      if ($county) {
        $normalized['county_id'] = $county['id'];
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
        'country_id', 
        'country_id.name', 
        'county_id.name', 
        'county_id', 
        'geo_code_1', 
        'geo_code_2')
      ->addJoin('Custom_electoral_districts AS custom_electoral_districts', 'LEFT', ['custom_electoral_districts.entity_id', '=', 'contact_id'])
      ->setGroupBy(['contact_id'])
      ->addWhere('street_address', 'IS NOT NULL')
      ->addWhere('contact_id.is_deceased', '!=', TRUE)
      ->addWhere('contact_id.is_deleted', '!=', TRUE)
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
    if ($this->groups) {
      // Convert Group titles to group ids.
      $groupIds = \Civi\Api4\Group::get()
        ->addWhere('title', 'IN', explode('|', $this->groups))
        ->addSelect('id')
        ->execute()->column('id');
      if (!$groupIds) {
        throw new \Exception(E::ts("Failed to find a group Id with the name: %1.", [ 1 => $this->groups ]));
      }
      $addressQuery->addWhere('contact_id.groups', 'IN', $groupIds );
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
      // Don't update, only get records that have never been coded.
      $addressQuery->addWhere('contact_id.electoral_update_status.electoral_last_updated', 'IS EMPTY');
    }
    else {
      // If we are updating, first get the ones that have never been updated, followed
      // by the ones updated the longest time in the past. This way if we run in batches
      // we will be sure not to update the same ones over and over again.
      $addressQuery->addOrderBy('contact_id.electoral_update_status.electoral_last_updated', 'ASC');
    }
    // Let 'er rip.
    $addresses = $addressQuery->execute();
    return $addresses;
  }

  /**
   * Helper function to create or update electoral districts custom data
   */
  protected function writeDistrictData($data) : void {
    $id = $this->matchDistrictData($data);
    if ($id) {
      $district = \Civi\Api4\CustomValue::update('electoral_districts')
        ->addWhere('id', '=', $id);
    }
    else {
      $district = \Civi\Api4\CustomValue::create('electoral_districts');
    }
    $district->setCheckPermissions(FALSE)
      ->addValue('entity_id', $data['contactId'])
      ->addValue('electoral_level', $data['level'])
      ->addValue('electoral_states_provinces', $data['state_province_id'] ?? NULL)
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

    $this->results[ 'status'] = 'success';
  }

  /**
   * Match existing district
   *
   * We may be updating, so we have to be sure we update rather then simply
   * append new district data. For the given data, return a 0 if there is
   * no match or the id of a matching record. 
   */
  private function matchDistrictData($data) : int {
    $district = \Civi\Api4\CustomValue::get('electoral_districts')
      ->setCheckPermissions(FALSE)
      ->addWhere('entity_id', '=', $data['contactId'])
      ->addWhere('electoral_level', '=', $data['level'])
      ->addWhere('electoral_chamber', '=', $data['chamber'])
      ->execute()->first();

    if ($district) {
      return $district['id'];
    }
    return 0;
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

  /**
   * Lookup URL. Given an API URL, return the results
   *
   * All subclasses should call this function and overload
   * the ApiUrlLookup function.
   */
  protected function lookupUrl($url) {
    if ($this->cache) {
      $cacheKey = hash('sha256', $url);
      $json = \Civi::cache('long')->get($cacheKey);
      if ($json) {
        \Civi::log()->debug("Retrieved electoral values from cache using key: $cacheKey.");
        return $json;
      }
    }
    $guzzleClient = $this->getGuzzleClient();
    try {
      $json = $guzzleClient->request('GET', $url)->getBody()->getContents();
    }
    catch (\GuzzleHttp\Exception\RequestException $e) {
      \Civi::log()->debug("Failed to retrieve data via url: $url");
      if ($e->hasResponse()) {
        $statusCode = $e->getResponse()->getStatusCode();
        \Civi::log()->debug("Got response code $statusCode");
      }
      $this->results['status'] = 'failure';
      $this->results['message'] = "Received response code $statusCode when doing address lookup.";
      return NULL;
    }
    if ($this->cache && $json) {
      \Civi::log()->debug("setting cache on key $cacheKey");
      \Civi::cache('long')->set($cacheKey, $json);
    }
    return $json;
  }

  public function lookup() : array {
    $this->normalizeAddress();
    if (!$this->addressIsCompleteEnough()) {
      $this->results['status'] = 'failure';
      $this->results['message'] = 'Failed to find enough address parameters to justify a lookup.';
      return [];
    }
    if ($this->countries) {
      if (!in_array($this->address['country_id'], $this->countries)) {
        $msg = E::ts('Country ID (%1) is not in list of allowed countries. No lookup made.', [ 1 => $this->address['country_id']]);
        $this->results['status'] = 'failure';
        $this->results['message'] = $msg;
        \Civi::log()->debug($msg);
        return [];
      }
    }
    if ($this->statesProvinces) {
      if (!in_array($this->address['state_province_id'], $this->statesProvinces)) {
        $msg = E::ts('State/Province ID (%1) is not in list of allowed state/provinces. No lookup made.', [ 1 => $this->address['state_province_id']]);
        $this->results['status'] = 'failure';
        $this->results['message'] = $msg;
        \Civi::log()->debug($msg);
        return [];
      }
    }
    if ($this->cities) {
      if (!in_array($this->address['city'], $this->cities)) {
        $msg = E::ts('City (%1) is not in list of allowed cities. No lookup made.', [ 1 => $this->address['city']]);
        $this->results['status'] = 'failure';
        $this->results['message'] = $msg;
        \Civi::log()->debug($msg);
        return [];
      }
    }

    return $this->apiLookup();
  }

  /**
   *
   * Provider-specific lookup for a single address.
   *
   * Returns an array of processed district data keyed to "district".
   *
   */
  abstract protected function apiLookup() : array;

  /**
   * Check to ensure address is complete enough
   *
   * Should return TRUE if the available address has enough parameters
   * to justify a lookup or FALSE.
   */
  abstract protected function addressIsCompleteEnough() : bool;

  /**
   * Get the API key of this data provider.
   */
  abstract protected function getApiKey() : string;

}
