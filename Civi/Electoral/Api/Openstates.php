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
  public function lookup() : array {
    $this->normalizeAddress();
    if (!$this->addressIsCompleteEnough()) {
      $error = [
        'reason' => 'Failed to find enough address parameters to justify a lookup.',
        'message' => 'Open States lookup not attempted.',
        'code' => '',
      ];
      $this->writeElectoralStatus($error);
      return [];
    }

    $this->setGeoCoordinates();
    if (empty($this->address['geo_code_1']) || empty($this->address['geo_code_2'])) {
      return [];
    }
    $queryString = $this->buildAddressQueryString();
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

    try {
      $url = self::OPENSTATES_QUERY_URL . $queryString;
      $resp_obj = $this->get_response($url);
    }
    catch (\GuzzleHttp\Exception\RequestException $e) {
      \Civi::log()->debug("Failed to retrieve data from openstates for contact {$this->address['contact_id']}", ['electoral']);
      if ($e->hasResponse()) {
        $statusCode = $e->getResponse()->getStatusCode();
        \Civi::log()->debug("Got response code $statusCode");
      }
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
  private function addressIsCompleteEnough() : bool {
    if ($this->address['country_id.name'] != 'United States') {
      \Civi::log()->debug("Rejecting address that is in " . $this->address['country_id.name'] . ". Address must be in US for Open States to work.");
      return FALSE;
    }
    if (empty($this->address['street_address']) || empty($this->address['state_province_id.name']) || empty($this->address['city'])) {
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
   * @param $postfields
   *   The posfields to be passed to the page.
   *
   * @return $json
   *   Decoded JSON PHP object object returned by the Cicero API or FALSE on error.
   */
  private function get_response($url) {
    \Civi::log()->debug("Contacting openstates with url: {$url}.", ['electoral']);
    $guzzleClient = $this->getGuzzleClient();
    $response = $guzzleClient->request('GET', $url);
    $json = $response->getBody()->getContents();
    if ($json) {
      $json_decoded = json_decode($json);
      if (!is_object($json_decoded)) {
        \Civi::log()->debug("Open States did not return an object.", ['electoral']);
        return FALSE;
      }
      
      // Success.
      return $json_decoded;
    }
    elseif ($json === FALSE) {
      \Civi::log()->debug("open states url: $url returned false. Giving up.", ['electoral']);
      return FALSE;
    }
  }

  /**
   * Convert the Open States raw data to the format writeDistrictData expects.
   */
  protected function parseDistrictData($districtDatum) : array {
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
    $official = new CRM_Electoral_Official();
    $official
      ->setFirstName($givenName)
      ->setLastName($familyName)
      ->setExternalIdentifier($externalIdentifier)
      ->setOcdId($officialInfoObject->jurisdiction->id)
      ->setPoliticalParty($officialInfoObject->party);
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
  protected function parseChamber($chamber) {
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
