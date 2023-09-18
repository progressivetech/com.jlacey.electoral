<?php

namespace Civi\Electoral\Api;

use CRM_Electoral_ExtensionUtil as E;

// use \GuzzleHttp\Client;

/**
 * Cicero Data Provider class.
 */
class Cicero extends \Civi\Electoral\AbstractApi {

  const CIVICRM_CICERO_LEGISLATIVE_QUERY_URL = 'https://cicero.azavea.com/v3.1/legislative_district?';
  const CIVICRM_CICERO_NONLEGISLATIVE_QUERY_URL = 'https://cicero.azavea.com/v3.1/nonlegislative_district?';
  const COUNCIL_DISTRICT_SYNONYMS = [
    'council_district',
    'ward',
  ];

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
    // arbitrarily calling city council "lower" because that seems
    // to be what Google does.
    'LOCAL' => 'lower',
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
  protected function apiLookup() : array {
    $queryString = $this->buildAddressQueryString();
    // Do a legislative lookup if we have district types.
    $response = [
      'district' => [],
    ];
    $legislativeDistrictTypes = [
      'country',
      'administrativeArea1',
      'administrativeArea2',
      'locality',
    ];
    $legislativeLookupComplete = FALSE;
    foreach ($this->districtTypes as $districtType) {
      // Cicero has one URL for legislative lookups and a different URL for each other lookup.
      if (in_array($districtType, $legislativeDistrictTypes)) {
        if ($legislativeLookupComplete) {
          continue;
        }
        $url = self::CIVICRM_CICERO_LEGISLATIVE_QUERY_URL . $queryString;
        if ($this->futureDate) {
          $url .= "&valid_on_or_after=" . $this->futureDate;
        }
        // One legislative lookup gets all the levels, so don't re-run for each level.
        $legislativeLookupComplete = TRUE;
      }
      else {
        $url = self::CIVICRM_CICERO_NONLEGISLATIVE_QUERY_URL . "$queryString&type=$districtType";
      }
      $resp_obj = NULL;
      $json = $this->lookupUrl($url);
      if ($json) {
        $resp_obj = $this->decodeLookupResults($json);
      }
      
      // successful lookup.
      if ($resp_obj) {
        if (in_array($districtType, $legislativeDistrictTypes)) {
          foreach ($resp_obj->response->results->candidates[0]->districts as $districtInfo) {
	    if ($this->futureDate) {
	      $validTo = substr($districtInfo->valid_to, 0, 10);
	      if ($validTo && $validTo <= $this->futureDate) {
	        // When pulling in future districts, omit district date that is relatively stale
		// to avoid having multiple districts.
	        continue;
              }
	    }

            // Don't need districts for exec positions, since it'll always be "NEW YORK" for NY, etc.
            if (strpos($districtInfo->district_type, '_EXEC')) {
              continue;
            }

            // Sometimes the mayor shows up as LOCAL_EXEC but other times the
            // mayor is a member of the city council so they will show up as
            // just LOCAL making them seem like a city council person. If we
            // add them as a district it will be added as a AT LARGE district
            // and will mess up searching on the real city council districts. 
            //
            // Rebecca Womack from Cicero said we can use the presence of
            // "council_district" in the ocd_id field, eg:
            // ocd-division/country:us/state:tx/place:austin/council_district:32
            // to indicate that it's a council member and not the mayor.
            if (preg_match('/^LOCAL/', $districtInfo->district_type)) {
              // We will skip this record unless council_district is specified.
              $skip = TRUE;
              $ocdIdParts = explode('/', $districtInfo->ocd_id);
              $lastPart = $ocdIdParts[4] ?? NULL;
              if ($lastPart){
                $districtTypeParts = explode(':', $lastPart);
                $districtType = $districtTypeParts[0] ?? NULL;
                if ($districtType) {
                  if (in_array($districtType, self::COUNCIL_DISTRICT_SYNONYMS)) {
                    $skip = FALSE;
                  }
                }
              }
              if ($skip) {
                continue;
              }
            }

            // We also want to exclude COUNTY as a subtype - to avoid having county
            // commissioners pop up when we want city council members.
            if (property_exists($districtInfo, 'subtype') && $districtInfo->subtype == 'COUNTY') {
              continue;
            }
            if (empty($districtInfo->district_id)) {
              continue;
            }

            $response['district'][] = $this->parseDistrictData($districtInfo);
          }
        }
        else {
          foreach ($resp_obj->response->results->candidates[0]->districts as $districtInfo) {
            if (empty($districtInfo->district_id)) {
              continue;
            }
            $response['district'][] = $this->parseDistrictData($districtInfo);
          }
        }
      }
    }
    return $response;
  }

  /**
   * Format address array into a query string.
   */
  private function buildAddressQueryString() : string {
    $streetAddress = $this->adjustStreetAddress();
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
  private function adjustStreetAddress() {
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
  protected function addressIsCompleteEnough() : bool {
    $stateProvinceNeeded = FALSE;
    // We can't lookup PO Boxes
    if (preg_match('/P[.]?O[.]? BOX/i', $this->address['street_address'])) {
      return FALSE;
    }
    if (in_array($this->address['country_id.name'], ['US', 'CA'])) {
      $stateProvinceNeeded = TRUE;
    }
    if ($this->address['street_address'] && ($this->address['state_province_id.name'] || !$stateProvinceNeeded) && $this->address['city']) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Decode Respsonse from Cicero.
   *
   * Function to do Cicero API calls.
   *
   * @param $json 
   *   Raw json from cicero. 
   *
   * @return $json
   *   Decoded JSON PHP object object returned by the Cicero API or FALSE on error.
   */
  protected function decodeLookupResults($json) {
    $json_decoded = json_decode($json);
    if (!is_object($json_decoded) || $json_decoded->response->errors ?? FALSE) {
      $error = 'Unknown Error';
      if (!is_object($json_decoded)) {
        $error = "Cicero did not return an object for contact id: ." . $this->address['contact_id'];
      }
      elseif (is_string($json_decoded->response->errors)) {
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
      \Civi::log()->debug($error);
      $this->results['status'] = 'failure';
      $this->results['message'] = $error;
      return FALSE;
    }
    // Success.
    return $json_decoded;
  }

  /**
   * Convert the Cicero raw data to the format writeDistrictData expects and write it.
   */
  private function parseDistrictData($districtDatum) : array {
    $data['contactId'] = $this->address['contact_id'];
    $data['level'] = $this->levelMap[$districtDatum->district_type];
    $data['state_province_id'] = $this->address['state_province_id'] ?? '';
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

}
