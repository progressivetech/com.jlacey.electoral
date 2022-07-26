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
  public function lookup() : array {
    $return = [
      'district' => [],
      'official' => [],
    ];
    $this->normalizeAddress();
    if (!$this->addressIsCompleteEnough()) {
      $error = [
        'reason' => 'Failed to find enough address parameters to justify a lookup.',
        'message' => 'Google Civic lookup not attempted.',
        'code' => '',
      ];
      $this->writeElectoralStatus($error);
      return $return;
    }
    
    // Assemble the API URL.
    $streetAddress = rawurlencode($this->address['street_address']);
    $city = rawurlencode($this->address['city']);
    $stateProvinceAbbrev = $this->address['state_province_id.abbreviation'];
    $apiKey = $this->getApiKey();
    $url = "https://www.googleapis.com/civicinfo/v2/representatives?address=$streetAddress%20$city%20$stateProvinceAbbrev&key=$apiKey";
    $result = NULL;
    $json = $this->lookupUrl($url);
    if ($json) {
      $result = $this->processLookupResults($json);
    }
    if ($result) {
      // Google data makes it really hard to parse like the other providers.
      // Google first provides a list of offices, which contain an "official_indices"
      // key, which is an array of indexes referring to a second list of officials.
      //
      // First we parse the officies to get the district information. And, as we do that,
      // we keep track of the official_indices so we can then parse the officials.
      $officialIndices = [];
      foreach ($result['offices'] as $office) {
        $district = $this->parseDistrictData($office);
        if ($district) {
          // Record the official indices so we can look those up later.
          foreach($district['official_indices'] as $index) {
            $officialIndices[$index] = $district;
          }
          // Now remove them since we don't record these.
          unset($district['official_indices']);
          $return['district'][] = $district;
        }
      }
      // Now we get the officials 
      foreach($officialIndices as $officialIndex => $district) {
        $return['official'][] = $this->parseOfficialData($result['officials'][$officialIndex], $district);
      }
    }
    return $return;
  }

  /**
   * Ensure address is complete enough to justify
   * a lookup.
   */
  protected function addressIsCompleteEnough() {
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
  protected function parseDistrictData($office) : array {
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
      'stateProvinceId' => $this->address['state_province_id'],
      'county' => NULL,
      'city' => $this->address['city'],
      'chamber' => $chamber,
      'district' => $district,
      'note' => NULL,
      'inOffice' => NULL,
      'valid_from' => NULL,
      'valid_to' => NULL,
      'ocd_id' => $office['divisionId'],
      // We add this special one to help us with official looksup later.
      'official_indices' => $office['officialIndices'],
    ];
  }

  protected function parseOfficialData($officialData, $district) {
    $districtId = $district['ocd_id'];
    // Bah, not real identifier. So we make one up.
    $externalIdentifier = 'google_' . hash("sha256", $officialData['name'] . $districtId);
    $official = new \CRM_Electoral_Official();

    $firstName = '';
    $middleName = '';
    $lastName = '';
    $names = $this->parseName($officialData['name']);
    $firstName = $names['first_name'];
    $middleName = $names['middle_name'];
    $lastName = $names['last_name'];
    $official
      ->setFirstName(utf8_encode($firstName))
      ->setMiddleName(utf8_encode($middleName))
      ->setLastName($lastName)
      ->setExternalIdentifier($externalIdentifier)
      ->setOcdId($districtId)
      ->setPoliticalParty($officialData['party'])
      ->setChamber($district['chamber'])
      ->setLevel($district['level']);
    // We're only supporting two addresses/phones/emails at this time due to how Civi handles location types.
    if (isset($officialData['address'])) {
      foreach ($officialData['address'] as $key => $addressData) {
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
          'street_address' => $addressData['line1'],
          'supplemental_address_1' => NULL,
          'supplemental_address_2' => NULL,
          'city' => $addressData['city'],
          'state_province' => $addressData['state'],
          'postal_code' => $addressData['zip'],
          'county' => NULL,
          'country' => $this->address['country_id.name'],
        ];
        $official->setAddress($address[$key], $locationType);
      }
    }
    if (isset($officialData['phones'])) {
      foreach ($officialData['phones'] as $key => $phone) {
        if ($key === 0) {
          $locationType = 'Main';
        }
        if ($key === 1) {
          $locationType = 'Other';
        }
        $official->setPhone($phone, $locationType);
      }
    }
    if (isset($officialData['emails'])) {
      foreach ($officialData['emails'] as $key => $email) {
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
    }
    return $official;
  }

  protected function processLookupResults($json) {
    $result = $json ? json_decode($json, TRUE) : [];
    if (isset($result['error'])) {
      $this->writeElectoralStatus($result['error']);
      return [];
    }
    return $result;
  }
}
