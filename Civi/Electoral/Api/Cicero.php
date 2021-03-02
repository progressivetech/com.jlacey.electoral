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

  public function reps() {
    // Reps code here.
  }

  /**
   * @var array
   * Map Cicero district types to Civi's levels.
   */
  private $levelMap = [
    'LOCAL' => 'locality',
    'JUDICIAL' => 'locality',
    'LOCAL_EXEC' => 'locality',
    'LOCAL_REDISTRICTED' => 'locality',
    'NATIONAL_EXEC' => 'country',
    'NATIONAL_LOWER' => 'country',
    'NATIONAL_LOWER_REDISTRICTED' => 'country',
    'NATIONAL_UPPER' => 'country',
    'POLICE' => 'locality',
    'SCHOOL' => 'locality',
    'STATE_EXEC' => 'administrativeArea1',
    'STATE_LOWER' => 'administrativeArea1',
    'STATE_LOWER_REDISTRICTED' => 'administrativeArea1',
    'STATE_UPPER' => 'administrativeArea1',
    'STATE_UPPER_REDISTRICTED' => 'administrativeArea1',
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
  protected function addressDistrictLookup() : array {
    $address = $this->address;
    if (!$this->addressIsCompleteEnough($address)) {
      // FIXME: Need to update the custom fields with an error message.
      $error = [
        'reason' => 'Failed to find enough address parameters to justify a lookup.',
        'message' => 'Cicero lookup not attempted.',
      ];
      $this->writeElectoralStatus($error, $address['id']);
      return FALSE;
    }

    $legislative_noncurrent = FALSE;
    $non_leg_types = [];
    $this->mapCiceroDistrictTypes();

    $queryString = $this->buildAddressQueryString($address);
    // if ($legislative_noncurrent) {
    //   $url = $this->CIVICRM_CICERO_LEGISLATIVE_QUERY_URL . $queryString .
    //     '&type=ALL_2010';
    //   $resp_obj = civicrm_cicero_get_response($url);
    //   if (FALSE === $resp_obj) {
    //     civicrm_cicero_log(t("Failed to obtain legislative non-current response. Continuing..."));
    //   }
    //   else {
    //     $response['legislative'][] = $resp_obj;
    //   }
    // }

    // Do a legislative lookup if we have district types.
    $response = [];
    if ($this->districtTypes) {
      $url = self::CIVICRM_CICERO_LEGISLATIVE_QUERY_URL . $queryString;
      $resp_obj = $this->civicrm_cicero_get_response($url);
      if (FALSE === $resp_obj) {
        \Civi::log()->debug("Failed to obtain legislative current response. Continuing...", ['cicero']);
      }
      else {
        $response = array_merge($response, $resp_obj->response->results->candidates[0]->districts);
      }
    }
    if ($this->nonlegislativeDistricts) {
      // while (list(, $type) = each($non_leg_types)) {
      //   $url = self::CIVICRM_CICERO_NONLEGISLATIVE_QUERY_URL . $queryString .
      //     '&type=' . $type;
      // }
      $url = self::CIVICRM_CICERO_NONLEGISLATIVE_QUERY_URL . $queryString;
      $resp_obj = $this->civicrm_cicero_get_response($url);
      if (FALSE === $resp_obj) {
        \Civi::log()->debug("Failed to obtain non-legislative response. Continuing...", ['cicero']);
      }
      else {
        $response['nonlegislative'][] = $resp_obj;
      }

    }
    return $response;
  }

  /**
   * Given the district types in Electoral API's settings, return the appropriate Cicero district types.
   */
  private function mapCiceroDistrictTypes() {
    // $mapping = [
    //   'country' = ['NATIONAL_EXEC', 'NATIONAL_UPPER', 'NATIONAL_LOWER'],
    //   'administrativeArea1' = ['STATE_EXEC', 'STATE_UPPER', 'STATE_LOWER'],
    //   'administrativeArea2' = ['LOCAL_EXEC', 'LOCAL'],
    //   'locality' = ['LOCAL_EXEC', 'LOCAL'],
    // ]
    // if (array_search($this->));
    // $temp = 1;
  }

  /**
   * Format address array into a query string.
   */
  private function buildAddressQueryString(array $address) : string {
    $streetAddress = $address['street_address'] ?? NULL;
    $city = $address['city'] ?? NULL;
    $stateProvince = $address['state_province_id:name'] ?? NULL;
    $postalCode = $address['postal_code'] ?? NULL;

    $searchLoc = str_replace(' ', '+', $streetAddress . '+' . $city . '+' . $stateProvince . '+' . $postalCode);
    // Get an official query response.
    $apiKey = $this->apiKey;
    //$query = rawurlencode('search_loc=' . $searchLoc . '&key=' . $apiKey . '&format=json');
    $query = 'search_loc=' . $searchLoc . '&format=json&key=' . $apiKey;
    return $query;
  }

  /**
   * Ensure that the address that has been returned is complete
   * enough to justify attempting a cicero lookup, which will
   * cost money even if no matches are made.
   */
  private function addressIsCompleteEnough(array $address) : bool {
    if ($address['street_address'] && $address['state_province_id:name'] && $address['city']) {
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
   * @param $postfields
   *   The posfields to be passed to the page.
   *
   * @return $json
   *   Decoded JSON PHP object object returned by the Cicero API or FALSE on error.
   */
  private function civicrm_cicero_get_response($url, $postfields = '') {
    \Civi::log()->debug("Contacting cicero with url: {$url} and postfields: {$postfields}.", ['cicero']);
    $guzzleClient = new \GuzzleHttp\Client();
    $json = $guzzleClient->request('GET', $url)->getBody()->getContents();
    // $json = $this->civicrm_cicero_get_response_curl_setup($url, $postfields);
    if ($json) {
      $json_decoded = json_decode($json);
      if (!is_object($json_decoded)) {
        \Civi::log()->debug("Cicero did not return an object.", ['cicero']);
        return FALSE;
      }
      if (count($json_decoded->response->errors) > 0) {
        $error = NULL;
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
        \Civi::log()->debug("Cicero error: $error", ['cicero']);
        return FALSE;
      }
      // Success.
      return $json_decoded;
    }
    elseif ($json === FALSE) {
      \Civi::log()->debug("cicero url: $url returned false. Giving up.", ['cicero']);
      return FALSE;
    }
  }

  /**
   * Convert the Cicero raw data to the format writeDistrictData expects and write it.
   */
  protected function parseDistrictData(array $districtData) : bool {
    \CRM_Core_Error::debug_var('districtData', $districtData);
    foreach ($districtData as $districtDatum) {
      $contactId = $this->address['contact_id'];
      $level = $this->levelMap[$districtDatum->district_type];
      $stateProvinceId = $this->address['state_province_id'];
      $county = NULL;
      $city = $districtDatum->city;
      $chamber = $this->chamberMap[$districtDatum->district_type] ?? NULL;
      $district = $districtDatum->district_id;
      $this->writeDistrictData($contactId, $level, $stateProvinceId, $county, $city, $chamber, $district);
    }

    $success = TRUE;
    return $success;
  }

}
