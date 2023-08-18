<?php

namespace Civi\Electoral\Api;

use CRM_Electoral_ExtensionUtil as E;
use CRM_Electoral_Official;

// use \GuzzleHttp\Client;

/**
 * Open States Data Provider class.
 */
class Openstates extends \Civi\Electoral\AbstractApi {

  const OPENSTATES_QUERY_URL = 'https://v3.openstates.org/people.geo';
  private $levelMap = [
    'state' => 'administrativeArea1',
    'country' => 'country',
  ];

  /*
   * Delay by 4 seconds.
   *
   * The default bronze tier limits to 40 lookups per minute,
   * so we ensure a 4 second gap between lookups which should ensure
   * we do no more then 15 per minute. Also, the bronze allows only 
   * 1000 lookups per day.
   */
  protected $delay = 4;

  /**
   * @inheritDoc
   */
  protected function getApiKey() : string {
    $key = \Civi::settings()->get('openstatesAPIKey');
    if (!$key) {
      throw new \Exception('Open States API Key is not set.');
    }
    return $key;
  }

  /**
   * @inheritDoc
   */
  protected function apiLookup() : array {
    $this->setGeoCoordinates();
    if (empty($this->address['geo_code_1']) || empty($this->address['geo_code_2'])) {
      $msg = "Failed to lookup geo coordinates. Ensure geo lookup is enabled and working.";
      \Civi::log()->debug($msg);
      // Don't set results - this doesn't count as a lookup since we didn't actually look
      // it up.
      return [];
    }
    $queryString = $this->buildAddressQueryString();
    $resp_obj = NULL;
    $response = [];
    // Only run if we have an appropriate district type.
    $openstatesDistrictTypes = [
      'country',
      'administrativeArea1'
    ];
    $districtTypeMatch = FALSE;
    foreach ($this->districtTypes as $districtType) {
      if (in_array($districtType, $openstatesDistrictTypes)) {
        $districtTypeMatch = TRUE;
      }
    }
    if (!$districtTypeMatch) {
      \Civi::log()->debug("Open States enabled, but neither country nor administrativeArea1 is selected.");
      return [];
    }

    $url = self::OPENSTATES_QUERY_URL . $queryString;
    $json = $this->lookupUrl($url);
    if ($json) {
      $resp_obj = $this->processLookupResults($json);
    }
    
    // successful lookup.
    if ($resp_obj) {
      // We previously used the district API endpoint to get legislative district info, but now we use the "officials" endpoint to get both district and official in one lookup.
      foreach ($resp_obj->results as $official) {
        $response['district'][] = $this->parseDistrictData($official);
        $response['official'][] = $this->parseOfficialData($official);
      }
    }
    return $response;
  }

  /**
   * Format address array into a query string.
   */
  private function buildAddressQueryString() : string {
    $lat = $this->address['geo_code_1'];
    $lng = $this->address['geo_code_2'];
    $apiKey = $this->apiKey;
    $query = "?lat=${lat}&lng=${lng}&apikey={$apiKey}";
    return $query;
  }

  /**
   * Ensure that the address that has been returned is complete
   * enough to justify attempting a lookup. Really that means
   * it has enough parameters for a successful geo code lookup.
   */
  protected function addressIsCompleteEnough() : bool {
    $country = $this->address['country_id.name'] ?? NULL;
    if (empty($country)) {
      \Civi::log()->debug("Rejecting address without a country set. Please set a default country in your CiviCRM database or use a country field in your form.");
      return FALSE;
    }
    if ($country != 'United States') {
      \Civi::log()->debug("Rejecting address that is in the country:" . $this->address['country_id.name'] . ". Address must be in US for Open States to work.");
      return FALSE;
    }
    if (empty($this->address['street_address']) || empty($this->address['state_province_id.name']) || empty($this->address['city'])) {
      \Civi::log()->debug("Rejecting address without a street address, state province name or city. All are required..");
      \Civi::log()->debug(print_r($this->address, TRUE));
      return FALSE;
    }
    return TRUE;
  }


  /**
   * Get Respsonse.
   *
   * Function to do API calls.
   *
   * @param $url
   *   The url of the cicero page you are getting aresponse from. Defaults to
   *   'http://cicero.azavea.com/token/new.json'.
   *
   * @return $json
   *   Decoded JSON PHP object object returned by the Cicero API or FALSE on error.
   */
  protected function processLookupResults($json) {
    if ($json) {
      $json_decoded = json_decode($json);
      if (!is_object($json_decoded)) {
        $message = "Open States did not return an object.";
        \Civi::log()->debug($message);
        $this->results['status'] = 'failed';
        $this->results['message'] = $message;
        return FALSE;
      }
      
      // Success.
      return $json_decoded;
    }
    elseif ($json === FALSE) {
      \Civi::log()->debug("open states url: $url returned false. Giving up.");
      return FALSE;
    }
  }

  /**
   * Convert the Open States raw data to the format writeDistrictData expects.
   */
  private function parseDistrictData($districtDatum) : array {
    $county = NULL;
    $city = NULL;
    $contactId = $this->address['contact_id'] ?? NULL;
    $level = $this->levelMap[$districtDatum->jurisdiction->classification];
    $stateProvinceId = $this->address['state_province_id'] ?? '';
    $chamber = $this->parseChamber($districtDatum->current_role->org_classification);
    $district = $districtDatum->current_role->district;
    $note = NULL;
    $valid_from = NULL;
    $valid_to = NULL;
    $ocd_id = $districtDatum->current_role->division_id;
    // $this->writeDistrictData($contactId, $level, $stateProvinceId, $county, $city, $chamber, $district, FALSE, NULL, $note, $valid_from, $valid_to, $ocd_id);
    return [
      'contactId' => $contactId, 
      'level' => $level, 
      'state_province_id' => $stateProvinceId, 
      'county' => $county, 
      'city' => $city, 
      'chamber' => $chamber, 
      'district' => $district, 
      'inOffice' => FALSE, 
      'officeName' => NULL, 
      'note' => $note, 
      'valid_from' => $valid_from, 
      'valid_to' => $valid_to, 
      'ocd_id' => $ocd_id,
    ];
  }

  /**
   * Given an officials from the API, returns an object that can be saved (or not).
   */
  private function parseOfficialData($officialInfoObject) : \CRM_Electoral_Official {
    // Check if we already have this contact in the database.
    $externalIdentifier = 'openstates_' . $officialInfoObject->id;
    // Get the basic info.
    // Sometimes given_name and family_name are empty so we have to parse
    // the full name.
    $givenName = $officialInfoObject->given_name; 
    $familyName = $officialInfoObject->family_name; 
    $names = $this->parseName($officialInfoObject->name);
    if (empty($givenName)) {
      $givenName = $names['first_name'];
    }
    if (empty($familyName)) {
      $familyName = $names['last_name'];
    }
    $level = $this->levelMap[$officialInfoObject->jurisdiction->classification];
    $chamber = $this->parseChamber($officialInfoObject->current_role->org_classification);

    $official = new CRM_Electoral_Official();
    $official
      ->setFirstName($givenName)
      ->setLastName($familyName)
      ->setExternalIdentifier($externalIdentifier)
      ->setOcdId($officialInfoObject->jurisdiction->id)
      ->setPoliticalParty($officialInfoObject->party)
      ->setChamber($chamber)
      ->setLevel($level);
    // Note - we have the image url but civi doesn't render remote images
    //  ->setImageUrl($officialInfoObject->image);
    // We're only supporting two addresses/phones/emails at this time due to how Civi handles location types.
    
    $official->setEmailAddress($officialInfoObject->email, 'Main');
    return $official;
  }

  /**
   * Parse chamber.
   *
   * Should be either "lower" or "upper" but sometimes (Nebraska) it's legislature which we
   * will parse as upper
   */
  private function parseChamber($chamber) {
    $allowed = [ 'upper', 'lower' ];

    if ($chamber == 'legislature') {
      $chamber = 'upper';
    }

    if (!in_array($chamber, $allowed)) {
      \Civi::log()->debug("Unknown chamber: $chamber");
    }
    return $chamber;
  }
}
