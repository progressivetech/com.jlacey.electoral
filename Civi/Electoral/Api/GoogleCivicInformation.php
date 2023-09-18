<?php

namespace Civi\Electoral\Api;

use CRM_Electoral_ExtensionUtil as E;

/**
 * Google Civic Data Provider class.
 */
class GoogleCivicInformation extends \Civi\Electoral\AbstractApi {

  /**
   * @var array
   * Map google roles to Civi chamber types.
   */
  private $chamberMap = [
    'lower' => 'legislatorLowerBody',
    'upper' => 'legislatorUpperBody',
  ];

  /**
   * @inheritDoc
   */
  protected function getApiKey() : string {
    $key = \Civi::settings()->get('googleCivicInformationAPIKey');
    if (!$key) {
      throw new \Exception('Google Civic Information API Key is not set.');
    }
    return $key;
  }

  /**
   * @inheritDoc
   */
  protected function apiLookup() : array {
    $return = [
      'district' => [],
    ];
    
    // Assemble the API URL.
    $streetAddress = rawurlencode($this->address['street_address']);
    $city = rawurlencode($this->address['city']);
    $stateProvinceAbbrev = $this->address['state_province_id.abbreviation'];
    $apiKey = $this->getApiKey();
    $url = "https://www.googleapis.com/civicinfo/v2/representatives?address=$streetAddress%20$city%20$stateProvinceAbbrev&key=$apiKey";
    $result = NULL;
    $json = $this->lookupUrl($url);
    if ($json) {
      $result = $this->decodeLookupResults($json);
    }
    if ($result) {
      foreach ($result['offices'] as $office) {
        $district = $this->parseDistrictData($office);
        if ($district) {
          $return['district'][] = $district;
        }
      }
    }
    return $return;
  }

  /**
   * Ensure address is complete enough to justify
   * a lookup.
   */
  protected function addressIsCompleteEnough() : bool {
    $required = [
      'street_address',
      'city',
      'state_province_id.abbreviation',
      'country_id.name'
    ];
    foreach($required as $field) {
      if (empty($this->address[$field])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /** 
   * Convert the Google raw data to the format writeDistrictData expects and
   * write it.
   */
  private function parseDistrictData($office) : array {
    $levels = array_intersect($this->districtTypes, $office['levels']);
    $level = array_pop($levels);
    $chambers = array_intersect($this->chamberMap, $office['roles']);
    $googleChamber = array_pop($chambers);
    $chamber = array_search($googleChamber, $this->chamberMap);


    if (empty($level) || empty($chamber)) {
      return [];
    }
    if (!preg_match('/:([0-9a-z]+)$/', $office['divisionId'], $matches)) {
      return [];
    };
    $district = $matches[1];

    return [
      'contactId' => $this->address['contact_id'],
      'level' => $level,
      'state_province_id' => $this->address['state_province_id'],
      'county' => NULL,
      'city' => $this->address['city'],
      'chamber' => $chamber,
      'district' => $district,
      'note' => NULL,
      'inOffice' => NULL,
      'valid_from' => NULL,
      'valid_to' => NULL,
      'ocd_id' => $office['divisionId'],
    ];
  }

  protected function decodeLookupResults($json) {
    $result = $json ? json_decode($json, TRUE) : NULL;
    if (isset($result['error'])) {
      $this->results['status'] = 'failed';
      $this->results['message'] = $result['error'];
      return NULL;
    }
    return $result;
  }
}
