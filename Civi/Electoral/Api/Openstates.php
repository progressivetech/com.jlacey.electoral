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
  public function reps() : array {
    $officials = $this->parseOfficialData($rawOfficialData);
    // FIXME: Temporary line
    foreach ($officials as $official) {
      $official->createOfficial();
    }
    return $officials;
  }

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
  protected function addressDistrictLookup() : array {
    $address = $this->address;
    if (!$this->addressIsCompleteEnough($address)) {
      $error = [
        'reason' => 'Failed to find enough address parameters to justify a lookup.',
        'message' => 'Open States lookup not attempted.',
        'code' => '',
      ];
      $this->writeElectoralStatus($error, $address['id']);
      return [];
    }

    $geoCoordinates = $this->getGeoCoordinates($address);
    if (empty($geoCoordinates)) {
      return [];
    }
    $queryString = $this->buildAddressQueryString($address, $geoCoordinates);
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
        $response['district'][] = $official;
        $response['official'][] = $official;
      }
    }
    return $response;
  }

  /**
   * Format address array into a query string.
   */
  private function buildAddressQueryString(array $address, array $geoCoordinates) : string {
    $lat = $geoCoordinates[0];
    $lng = $geoCoordinates[1];
    $apiKey = $this->apiKey;
    $query = "?lat=${lat}&lng=${lng}&apikey={$apiKey}";
    return $query;
  }

  /**
   * Ensure that the address that has been returned is complete
   * enough to justify attempting a cicero lookup, which will
   * cost money even if no matches are made.
   */
  private function addressIsCompleteEnough(array $address) : bool {
    if ($address['country_id:name'] != 'US') {
      \Civi::log()->debug("Rejecting address that is in " . $address['country_id:name'] . ". Address must be in US for Open States to work.");
      return FALSE;
    }
    if (empty($address['street_address']) || empty($address['state_province_id:name']) || empty($address['city'])) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * Return geo coordinates array for address.
   *
   */
  private function getGeoCoordinates(array $address): array {
    if ($address['geo_code_1'] && $address['geo_code_2']) {
      return [ $address['geo_code_1'], $address['geo_code_2'] ];
    }
    // Try to do a lookup
    $class = $this->getGeocodeProviderClass();
    if (empty($class)) {
      // No geocode method set.
      // TODO: give a notice that this is important.
      \Civi::log()->debug("Failed to find usable geocoding class.");
      return array();
    }

    $params = array(
      // Country must be United States for the API to work.
      'country' => 'United States',
      'street_address' => $address['street_address'],
      'city' => $address['city'],
      'state_province_id' => $address['state_province_id'],
      'postal_code' => $address['postal_code'],
    );
    $success = $class::format($params);
    if (!$success || empty($params['geo_code_1']) || empty($params['geo_code_2'])) {
      \Civi::log()->debug("Failed to lookup geo info using $class.");
      return array();
    }
    return [ $params['geo_code_1'], $params['geo_code_2'] ];
  }

  /**
   * Get Respsonse from Cicero.
   *
   * Function to do Cicero API calls.
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
   * Convert the Open States raw data to the format writeDistrictData expects and write it.
   */
  protected function parseDistrictData(array $districtData) : bool {
    if (!$districtData['district']) {
      return FALSE;
    }
    foreach ($districtData['district'] as $districtDatum) {
      $county = NULL;
      $city = NULL;
      $contactId = $this->address['contact_id'];
      $level = $this->levelMap[$districtDatum->jurisdiction->classification];
      $stateProvinceId = $this->address['state_province_id'] ?? '';
      $chamber = $districtDatum->current_role->org_classification;
      $district = $districtDatum->current_role->district;
      $note = NULL;
      $valid_from = NULL;
      $valid_to = NULL;
      $ocd_id = $districtDatum->current_role->division_id;
      $this->writeDistrictData($contactId, $level, $stateProvinceId, $county, $city, $chamber, $district, FALSE, NULL, $note, $valid_from, $valid_to, $ocd_id);
    }
    if (\Civi::settings()->get('electoralApiCreateOfficialOnDistrictLookup')) {
      $officials = $this->parseOfficialData($districtData['official']);
      foreach ($officials as $official) {
        $official->createOfficial();
      }
    }
    return TRUE;
  }

  /**
   * Given an array of officials from Cicero's API, returns an array where all elements are of type CRM_Electoral_Official.
   */
  private function parseOfficialData($officialData) : array {
    foreach ($officialData as $officialInfoObject) {
      // Check if we already have this contact in the database.
      $externalIdentifier = 'openstates_' . $officialInfoObject->id;
      $contactExists = \Civi\Api4\Contact::get(FALSE)
        ->addWhere('external_identifier', '=', $externalIdentifier)
        ->execute()
        ->count();
      if ($contactExists) {
        continue;
      }
      // Get the basic info.
      $official = new CRM_Electoral_Official();
      $official
        ->setFirstName($officialInfoObject->given_name)
        ->setLastName($officialInfoObject->family_name)
        ->setExternalIdentifier($externalIdentifier)
        ->setOcdId($officialInfoObject->jurisdiction->id)
        ->setPoliticalParty($officialInfoObject->party);
      // Note - we have the image url but civi doesn't render remote images
      //  ->setImageUrl($officialInfoObject->image);
      // We're only supporting two addresses/phones/emails at this time due to how Civi handles location types.
      
      $official->setEmailAddress($officialInfoObject->email, 'Main');
      $officials[] = $official;
    }
    return $officials;
  }

}
