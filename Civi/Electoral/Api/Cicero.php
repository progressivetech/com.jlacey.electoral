<?php

namespace Civi\Electoral\Api;

use CRM_Electoral_ExtensionUtil as E;
use CRM_Electoral_Official;

// use \GuzzleHttp\Client;

/**
 * Cicero Data Provider class.
 */
class Cicero extends \Civi\Electoral\AbstractApi {

  const CIVICRM_CICERO_LEGISLATIVE_QUERY_URL = 'https://cicero.azavea.com/v3.1/official?max=200&';
  const CIVICRM_CICERO_NONLEGISLATIVE_QUERY_URL = 'https://cicero.azavea.com/v3.1/nonlegislative_district?';

  /**
   * @var array
   * Map Cicero district types to Civi's levels.
   */
  private $levelMap = [
    'LOCAL' => 'locality',
    'LOCAL_EXEC' => 'locality',
    'LOCAL_REDISTRICTED' => 'locality',
    'NATIONAL_EXEC' => 'country',
    'NATIONAL_LOWER' => 'country',
    'NATIONAL_LOWER_REDISTRICTED' => 'country',
    'NATIONAL_UPPER' => 'country',
    'STATE_EXEC' => 'administrativeArea1',
    'STATE_LOWER' => 'administrativeArea1',
    'STATE_LOWER_REDISTRICTED' => 'administrativeArea1',
    'STATE_UPPER' => 'administrativeArea1',
    'STATE_UPPER_REDISTRICTED' => 'administrativeArea1',
    'JUDICIAL' => 'judicial',
    'POLICE' => 'police',
    'SCHOOL' => 'school',
    'VOTING' => 'voting',
  ];


  /**
   * @var array
   * Map Cicero district types to Civi's chamber types.
   */
  private $chamberMap = [
    'NATIONAL_LOWER' => 'lower',
    'NATIONAL_LOWER_REDISTRICTED' => 'lower',
    'NATIONAL_UPPER' => 'upper',
    'STATE_LOWER' => 'lower',
    'STATE_LOWER_REDISTRICTED' => 'lower',
    'STATE_UPPER' => 'upper',
    'STATE_UPPER_REDISTRICTED' => 'upper',
  ];

  /**
   * @inheritDoc
   */
  protected function getApiKey() : string {
    $key = \Civi::settings()->get('ciceroAPIKey');
    if (!$key) {
      throw new \Exception('Cicero API Key is not set.');
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
        'message' => 'Cicero lookup not attempted.',
        'code' => '',
      ];
      $this->writeElectoralStatus($error);
      return [];
    }

    $queryString = $this->buildAddressQueryString();
    // Do a legislative lookup if we have district types.
    $response = [
      'district' => [],
      'official' => [],
    ];
    $legislativeDistrictTypes = [
      'country',
      'administrativeArea1',
      'administrativeArea2',
      'locality',
    ];
    $legislativeLookupComplete = FALSE;
    foreach ($this->districtTypes as $districtType) {
      try {
        // Cicero has one URL for legislative lookups and a different URL for each other lookup.
        if (in_array($districtType, $legislativeDistrictTypes)) {
          if ($legislativeLookupComplete) {
            continue;
          }
          $url = self::CIVICRM_CICERO_LEGISLATIVE_QUERY_URL . $queryString;
          if ($this->includeUpcoming) {
            $today = (new \DateTime('now'))->format('Y-m-d');
            $url .= "&valid_on_or_after=$today";
          }
          // One legislative lookup gets all the levels, so don't re-run for each level.
          $legislativeLookupComplete = TRUE;
        }
        else {
          $url = self::CIVICRM_CICERO_NONLEGISLATIVE_QUERY_URL . "$queryString&type=$districtType";
        }
        $resp_obj = $this->civicrm_cicero_get_response($url);
      }
      catch (\GuzzleHttp\Exception\RequestException $e) {
        \Civi::log()->debug("Failed to retrieve $districtType data from Cicero for contact {$this->address['contact_id']}", ['electoral']);
        if ($e->hasResponse()) {
          $statusCode = $e->getResponse()->getStatusCode();
          \Civi::log()->debug("Got response code $statusCode");
        }
      }
      // successful lookup.
      if ($resp_obj) {
        // We previously used the district API endpoint to get legislative
        // district info, but now we use the "officials" endpoint to get both
        // district and official in one lookup.
        if (in_array($districtType, $legislativeDistrictTypes)) {
          foreach ($resp_obj->response->results->candidates[0]->officials as $official) {
            $districtInfo = $official->office->district;
            // Don't need districts for exec positions, since it'll always be "NEW YORK" for NY, etc.
            if (strpos($districtInfo->district_type, '_EXEC')) {
              continue;
            }
            $response['district'][] = $this->parseDistrictData($districtInfo);
            $response['official'][] = $this->parseOfficialData($official);
          }
        }
        else {
          $response['district'] = array_merge($response['district'], $this->parseDistrictData($resp_obj->response->results->candidates[0]->districts));
        }
      }
    }
    return $response;
  }

  /**
   * Format address array into a query string.
   */
  private function buildAddressQueryString() : string {
    $streetAddress = $this->civicrm_cicero_adjust_street_address();
    $city = $this->address['city'] ?? NULL;
    $stateProvince = $this->address['state_province_id.name'] ?? NULL;
    $postalCode = $this->address['postal_code'] ?? NULL;
    $country = $this->address['country_id.name'] ?? NULL;
    $apiKey = $this->apiKey;
    // Alternate approach, requires more address parsing than I'd like to do on non-US addresses though.
    // $query = "search_address={$streetAddress}&search_city={$city}&search_state={$stateProvince}&search_postal={$postalCode}&search_country={$country}";
    // $query = str_replace(' ', '+', $query);
    // $query .= '&format=json&key=' . $apiKey;
    $searchLoc = str_replace(' ', '+', $streetAddress . '+' . $city . '+' . $stateProvince . '+' . $postalCode . '+' . $country);
    $query = "search_loc={$searchLoc}&format=json&key={$apiKey}";

    return $query;
  }

  /**
   * cicero sometimes fails to find a result simply because an apartment
   * number was included. This functions adjusts the 'street_address' indexed
   * value based on the parsed address. If civicrm could not parse the address
   * we assume cicero won't be able to either.
   *
   * @param $values
   *   The values array returned by the get Address civicrm_api call.
   *
   * @return string 
   *   The adjusted street _address after attempting to adjust the street_address
   *   to increase the odds of matching in cicero.
   */
  private function civicrm_cicero_adjust_street_address() {
    // Civi can't handle Spanish-language address parsing because it has both floor and door numbers
    $streetAddress = $this->address['street_address'];
    if (in_array($this->address['country_id.name'], ['MX', 'ES'])) {
      $streetAddress = preg_replace('/ Puerta.*/i', '', $streetAddress);
    }
    $parsed_values = \CRM_Core_BAO_Address::parseStreetAddress($streetAddress);
    $streetNumber = $parsed_values['street_number'];
    $streetName = $parsed_values['street_name'];

    // Used the parsed values if they are available
    if ($streetName) {
      $streetAddress = trim($streetNumber . ' ' . $streetName);
    }
    return $streetAddress;
  }

  /**
   * Ensure that the address that has been returned is complete
   * enough to justify attempting a cicero lookup, which will
   * cost money even if no matches are made.
   */
  private function addressIsCompleteEnough() : bool {
    $stateProvinceNeeded = FALSE;
    if (in_array($this->address['country_id.name'], ['US', 'CA'])) {
      $stateProvinceNeeded = TRUE;
    }
    if ($this->address['street_address'] && ($this->address['state_province_id.name'] || !$stateProvinceNeeded) && $this->address['city']) {
      return TRUE;
    }
    return FALSE;
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
   * @return $json
   *   Decoded JSON PHP object object returned by the Cicero API or FALSE on error.
   */
  private function civicrm_cicero_get_response($url) {
    \Civi::log()->debug("Contacting cicero with url: {$url}.", ['electoral']);
    $guzzleClient = $this->getGuzzleClient();
    $response = $guzzleClient->request('GET', $url);
    $json = $response->getBody()->getContents();
    if ($json) {
      $json_decoded = json_decode($json);
      if (!is_object($json_decoded)) {
        \Civi::log()->debug("Cicero did not return an object.", ['electoral']);
        return FALSE;
      }
      if ($json_decoded->response->errors ?? FALSE) {
        $error = 'Unknown Error';
        if (is_string($json_decoded->response->errors)) {
          $error = $json_decoded->response->errors;
        }
        elseif (is_array($json_decoded->response->errors)) {
          $error = array_pop($json_decoded->response->errors);
        }
        if ($error == 'This account has reached its overdraft limit. Please purchase more credits.') {
          // This is an error that should get immediate attention even if it means
          // showing an embarrasing error to a user.
          \CRM_Core_Session::setStatus(E::ts("Out of credits for lookup of electoral info."), "Out of credits", 'alert');
        }
        $errorArray['code'] = $response->getStatusCode();
        $errorArray['reason'] = '';
        $errorArray['message'] = $error;
        $this->writeElectoralStatus($errorArray);
        return FALSE;
      }
      // Success.
      return $json_decoded;
    }
    elseif ($json === FALSE) {
      \Civi::log()->debug("cicero url: $url returned false. Giving up.", ['electoral']);
      return FALSE;
    }
  }

  /**
   * Convert the Cicero raw data to the format writeDistrictData expects and write it.
   */
  protected function parseDistrictData($districtDatum) : array {
    $data['contactId'] = $this->address['contact_id'];
    $data['level'] = $this->levelMap[$districtDatum->district_type];
    $data['stateProvinceId'] = $this->address['state_province_id'] ?? '';
    $data['county'] = NULL;
    $data['city'] = $districtDatum->city;
    $data['chamber'] = $this->chamberMap[$districtDatum->district_type] ?? NULL;
    $data['district'] = $districtDatum->district_id;
    $data['note'] = NULL;
    $data['inOffice'] = NULL;
    $data['valid_from'] = $districtDatum->valid_from ?? NULL;
    $data['valid_to'] = $districtDatum->valid_to ?? NULL;
    $data['ocd_id'] = $districtDatum->ocd_id;
    if ($districtDatum->district_type == 'LOCAL') {
      $data['note'] = str_replace(" " . $districtDatum->district_id, '', $districtDatum->label);
    }
    return $data;
  }

  /**
   * Given an array of officials from Cicero's API, returns an array where all elements are of type CRM_Electoral_Official.
   */
  private function parseOfficialData($officialInfoObject) : \CRM_Electoral_Official {
    $officialInfo = json_decode(json_encode($officialInfoObject), TRUE);
    $externalIdentifier = 'cicero_' . $officialInfo['id'];

    // This is for readability.
    $office = $officialInfo['office'] ?? NULL;

    // Get the basic info.
    $official = new CRM_Electoral_Official();
    $official
      ->setFirstName($officialInfo['first_name'])
      ->setMiddleName($officialInfo['middle_initial'])
      ->setLastName($officialInfo['last_name'])
      ->setNickName($officialInfo['nickname'])
      ->setPrefix($officialInfo['salutation'])
      ->setSuffix($officialInfo['name_suffix'])
      ->setExternalIdentifier($externalIdentifier)
      ->setOcdId($office['district']['ocd_id'])
      ->setTitle($office['title'])
      ->setCurrentTermStartDate($officialInfo['current_term_start_date'])
      ->setTermEndDate($officialInfo['term_end_date'])
      ->setPoliticalParty($officialInfo['party'])
      ->setImageUrl($officialInfo['photo_origin_url']);
    // We're only supporting two addresses/phones/emails at this time due to how Civi handles location types.
    foreach ($officialInfo['addresses'] as $key => $addressData) {
      if ($key === 0) {
        $locationType = 'Main';
      }
      if ($key === 1) {
        $locationType = 'Other';
      }
      if ($key > 1) {
        break;
      }
      $address[$key] = [
        'street_address' => $addressData['address_1'],
        'supplemental_address_1' => $addressData['address_2'],
        'supplemental_address_2' => $addressData['address_3'],
        'city' => $addressData['city'],
        'state_province' => $addressData['state'],
        'country' => $office['representing_country']['name_short_iso'],
        'county' => $addressData['county'],
        'postal_code' => $addressData['postal_code'],
      ];
      $official->setAddress($address[$key], $locationType);
      $official->setPhone($addressData['phone_1'], $locationType);
    }
    foreach ($officialInfo['email_addresses'] as $key => $email) {
      if ($key === 0) {
        $locationType = 'Main';
      }
      if ($key === 1) {
        $locationType = 'Other';
      }
      if ($key > 1) {
        break;
      }
      $official->setEmailAddress($email, $locationType);
    }
    return $official;
  }

}
