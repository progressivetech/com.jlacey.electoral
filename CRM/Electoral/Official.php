<?php

class CRM_Electoral_Official {
  private $firstName;
  private $middleName;
  private $lastName;
  private $nickName;
  private $prefix;
  private $suffix;
  private $externalIdentifier;
  private $office;
  private $ocdId;
  private $title;
  private $currentTermStartDate;
  private $termEndDate;
  private $address;
  private $emailAddress;
  private $phone;
  private $website;
  private $politicalParty;
  private $twitter;
  private $facebook;
  private $instagram;
  private $imageUrl;

  /**
   * Create an official from the Official class.
   */
  protected function createOfficial() {
    //Initialize contact params
    $contact = [
      'contact_type' => 'Individual',
      'contact_sub_type' => 'Official',
      'do_not_email' => 1,
      'first_name' => $this->firstName,
      'middle_name' => $this->middleName,
      'last_name' => $this->lastName,
      'nick_name' => $this->nickName,
      'external_identifier' => $this->externalIdentifier,
      'image_URL' => $this->imageUrl,
    ];

    //Check if rep already exists, create or update accordingly
    $exists = \Civi\Api4\Contact::get(FALSE)
      ->addWhere('external_identifier', $this->externalIdentifier)
      ->execute()
      ->count();
    if (!$exists) {
      $official = \Civi\Api4\Contact::create(FALSE)
        ->addValue('external_identifier', $this->externalIdentifier);
    }
    else {
      $official = \Civi\Api4\Contact::update(FALSE)
        ->addWhere('external_identifier', '=', $this->externalIdentifier);
    }
    $official
      ->addValue('contact_type', 'Individual')
      ->addValue('contact_sub_type', 'Official')
      ->addValue('do_not_email', TRUE)
      ->addValue('first_name', $this->firstName)
      ->addValue('middle_name', $this->middleName)
      ->addValue('last_name', $this->lastName)
      ->addValue('nick_name', $this->nickName)
      ->addValue('image_URL', $this->imageUrl)
      ->execute();
    $cid = $official->id;

    // Create the email, phone, address.
    if ($this->emailAddress) {
      $this->createEmail($cid, $this->emailAddress);
    };

    if ($this->phone) {
      $this->createPhone($cid, $this->phone);
    };

    if ($this->address) {
      $this->createAddress($cid, $this->address);
    };

    //Create website
    if (isset($reps['officials'][$officialIndex]['urls'][0])) {
      electoral_create_website($contactId, $reps['officials'][$officialIndex]['urls'][0], 2);
    }

    
    if (isset($reps['officials'][$officialIndex]['channels'])) {
      foreach ($reps['officials'][$officialIndex]['channels'] as $channel) {
        if ($channel['type'] == 'Facebook') {
          //Create Facebook
          if ($channel['id'] != NULL) {
            $repFacebook = 'https://facebook.com/' . $channel['id'];
            electoral_create_website($contactId, $repFacebook, 3);
          }
        }
        if ($channel['type'] == 'Twitter') {
          //Create Twitter
          if ($channel['id'] != NULL) {
            $repTwitter = 'https://twitter.com/' . $channel['id'];
            electoral_create_website($contactId, $repTwitter, 11);
          }
        }
      }
    }

    //Tag the legislator with their party
    if ($repExistContact['count'] == 0 &&
        isset($reps['officials'][$officialIndex]['party'])) {
      electoral_tag_party($contactId, $reps['officials'][$officialIndex]['party']);
    }
  }

  /**
   * Helper function to check if website exists
   * and if not, create it
   */
  function createWebsite($contactId, $website, $websiteType) {
    //Check if contact has a website set, Main location type
    $websiteExist = civicrm_api3('Website', 'get', [
      'return' => "url",
      'contact_id' => $contactId,
      'website_type_id' => $websiteType,
    ]);
    //If there is an existing website, set the id for comparison
    if ($websiteExist['count'] > 0) {
      $websiteExistId = $websiteExist['id'];
    }

    //Add an updated website or a new one if none exist,
    //and set it to primary
    if (($websiteExist['count'] == 1 && $websiteExist['values'][$websiteExistId]['url'] != $website) ||
        $websiteExist['count'] == 0) {
      $websiteParams = [
        'contact_id' => $contactId,
        'url' => "$website",
        'website_type_id' => $websiteType,
      ];
      $website = civicrm_api3('Website', 'create', $websiteParams);
    }
  }

  /**
   * Helper function to check if email exists
   * and if not, create it
   */
  private function createEmail($contactId, $email) {
    //Check if contact has an email address set, Main location type
    $emailExist = civicrm_api3('Email', 'get', [
      'return' => "email",
      'contact_id' => $contactId,
      'is_primary' => 1,
      'location_type_id' => 3,
    ]);
    //If there is an existing email address, set the id for comparison
    if ($emailExist['count'] > 0) {
      $emailExistId = $emailExist['id'];
    }

    //Add an updated email address or a new one if none exist,
    //and set it to primary
    if (($emailExist['count'] == 1 && $emailExist['values'][$emailExistId]['email'] != strtolower($email)) ||
         $emailExist['count'] == 0) {
      $emailParams = [
        'contact_id' => $contactId,
        'location_type_id' => 3,
        'is_primary' => 1,
        'email' => "$email",
      ];
      civicrm_api3('Email', 'create', $emailParams);
    }
  }

  /**
   * Helper function to check if phone exists
   * and if not, create it
   */
  private function createPhone($contactId, $phone) {
    //Check if contact has a phone set, Main location type
    $phoneExist = civicrm_api3('Phone', 'get', [
      'return' => "phone",
      'contact_id' => $contactId,
      'is_primary' => 1,
      'location_type_id' => 3,
    ]);
    //If there is an existing phone number, set the id for comparison
    if ($phoneExist['count'] > 0) {
      $phoneExistId = $phoneExist['id'];
    }

    //Add an updated phone number or a new one if none exist,
    //and set it to primary
    if (($phoneExist['count'] == 1 && $phoneExist['values'][$phoneExistId]['phone'] != strtolower($phone)) ||
        $phoneExist['count'] == 0) {
      $phoneParams = [
        'contact_id' => $contactId,
        'location_type_id' => 3,
        'phone_type_id' => 1,
        'is_primary' => 1,
        'phone' => "$phone",
      ];
      $createdPhone = civicrm_api3('Phone', 'create', $phoneParams);
    }
  }

  /**
   * Helper function to check if address exists
   * and if not, create it
   */
  private function createAddress($contactId, $address) {
    $streetAddress = $address['line1'];
    //Check if contact has an address set
    $addressExist = civicrm_api3('Address', 'get', [
      'return' => "street_address",
      'contact_id' => $contactId,
      'is_primary' => 1,
    ]);
    //If there is an existing address address, set the id for comparison
    if ($addressExist['count'] > 0) {
      $addressExistId = $addressExist['id'];
    }

    //Add an updated address address or a new one if none exist,
    //and set it to primary
    if (($addressExist['count'] == 1 && $addressExist['values'][$addressExistId]['street_address'] != $streetAddress) ||
        $addressExist['count'] == 0) {
      $usStates = array_flip(CRM_Core_PseudoConstant::stateProvinceForCountry(1228, 'abbreviation'));
      $addressParams = [
        'contact_id' => $contactId,
        'location_type_id' => 3,
        'is_primary' => 1,
        'street_address' => $streetAddress,
        'supplemental_address_1' => $address['line2'],
        'city' => $address['city'],
        'state_province_id' => $usStates[$address['state']],
        'postal_code' => $address['zip'],
      ];
      $createdAddress = civicrm_api3('Address', 'create', $addressParams);
    }
  }

  /**
   * Set the value of firstName
   *
   * @return  self
   */
  public function setFirstName($firstName) {
    $this->firstName = $firstName;
    return $this;
  }

  /**
   * Set the value of middleName
   *
   * @return  self
   */
  public function setMiddleName($middleName) {
    $this->middleName = $middleName;
    return $this;
  }

  /**
   * Set the value of lastName
   *
   * @return  self
   */
  public function setLastName($lastName) {
    $this->lastName = $lastName;
    return $this;
  }

  /**
   * Set the value of nickName
   *
   * @return  self
   */
  public function setNickName($nickName) {
    $this->nickName = $nickName;
    return $this;
  }

  /**
   * Set the value of prefix
   *
   * @return  self
   */
  public function setPrefix($prefix) {
    $this->prefix = $prefix;
    return $this;
  }

  /**
   * Set the value of suffix
   *
   * @return  self
   */
  public function setSuffix($suffix) {
    $this->suffix = $suffix;
    return $this;
  }

  /**
   * Set the value of externalIdentifier
   *
   * @return  self
   */
  public function setExternalIdentifier($externalIdentifier) {
    $this->externalIdentifier = $externalIdentifier;
    return $this;
  }

  /**
   * Set the value of office
   *
   * @return  self
   */
  public function setOffice($office) {
    $this->office = $office;
    return $this;
  }

  /**
   * Set the value of ocdId
   *
   * @return  self
   */
  public function setOcdId($ocdId) {
    $this->ocdId = $ocdId;
    return $this;
  }

  /**
   * Set the value of title
   *
   * @return  self
   */
  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  /**
   * Set the value of currentTermStartDate
   *
   * @return  self
   */
  public function setCurrentTermStartDate($currentTermStartDate) {
    $this->currentTermStartDate = $currentTermStartDate;
    return $this;
  }

  /**
   * Set the value of termEndDate
   *
   * @return  self
   */
  public function setTermEndDate($termEndDate) {
    $this->termEndDate = $termEndDate;
    return $this;
  }

  /**
   * Set the value of addresses
   *
   * @return  self
   */
  public function setAddress($address) {
    $this->address = $address;
    return $this;
  }

  /**
   * Set the value of emailAddresses
   *
   * @return  self
   */
  public function setEmailAddress($emailAddress) {
    $this->emailAddress = $emailAddress;
    return $this;
  }

  /**
   * Set the value of phone
   *
   * @return  self
   */
  public function setPhone($phone) {
    $this->phone = $phone;
    return $this;
  }

  /**
   * Set the value of website
   *
   * @return  self
   */
  public function setWebsite($website) {
    $this->website = $website;
    return $this;
  }

  /**
   * Set the value of politicalParty
   *
   * @return  self
   */
  public function setPoliticalParty($politicalParty) {
    $this->politicalParty = $politicalParty;
    return $this;
  }

  /**
   * Set the value of twitter
   *
   * @return  self
   */
  public function setTwitter($twitter) {
    $this->twitter = $twitter;
    return $this;
  }

  /**
   * Set the value of facebook
   *
   * @return  self
   */
  public function setFacebook($facebook) {
    $this->facebook = $facebook;
    return $this;
  }

  /**
   * Set the value of instagram
   *
   * @return  self
   */
  public function setInstagram($instagram) {
    $this->instagram = $instagram;
    return $this;
  }

  /**
   * Set the value of imageUrl
   *
   * @return  self
   */
  public function setImageUrl($imageUrl) {
    $this->imageUrl = $imageUrl;
    return $this;
  }

}
