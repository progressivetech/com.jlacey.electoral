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

  public function districts() {
    // Get the addresses.
    $addresses = $this->getAddresses();
    foreach ($addresses as $address) {
      $this->addressDistrictLookup($address);
    }
    return 'hey';
  }

  public function reps() {
    // Reps code here.
  }

  /**
   * @var array
   * Map Cicero district types to Civi's.
   * FIXME: Needs doing.
   */
  private $districtMap = [];

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
  protected function addressDistrictLookup(array $address) {
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
    if ($this->districtTypes) {
      $url = self::CIVICRM_CICERO_LEGISLATIVE_QUERY_URL . $queryString;
      $resp_obj = $this->civicrm_cicero_get_response($url);
      if (FALSE === $resp_obj) {
        \Civi::log()->debug("Failed to obtain legislative current response. Continuing...", ['cicero']);
      }
      else {
        $response['legislative'][] = $resp_obj;
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
    $query = rawurlencode('search_loc=' . $searchLoc . '&key=' . $apiKey . '&format=json');
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
   * Helper function to setup curl calls Cicero API calls.
   *
   * @param $url
   *   The url of the cicero page you are getting a response from.
   *
   * @param $postfields
   *   The posfields to be passed to the page
   *
   * @return $json
   *   JSON object returned by the Cicero API, or FALSE.
   */
  private function civicrm_cicero_get_response_curl_setup($url, $postfields) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_FAILONERROR, TRUE);
    if ($postfields !== '') {
      curl_setopt($ch, CURLOPT_POST, TRUE);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
    }
    $json = curl_exec($ch);
    if ($json === FALSE) {
      // There was an error.
      $error = curl_error($ch);
      \Civi::log()->debug("curl_exec returned an error: $error.", ['cicero']);
    }
    curl_close($ch);
    if (empty($json)) {
      \Civi::log()->debug("curl_exec returned an empty string.", ['cicero']);
      return FALSE;
    }
    return $json;
  }

}
