<?php

class CRM_Electoral_Official {
  private $firstName;
  private $middleName;
  private $lastName;
  private $nickName;
  private $prefix;
  private $suffix;
  private $externalIdentifier;
  private $ocdId;
  private $title;
  private $currentTermStartDate;
  private $termEndDate;
  /**
   * The next three are arrays, they can contain more than one of themselves.
   * @var array
   */
  private $address = [];
  private $emailAddress = [];
  private $phone = [];
  private $website;
  private $politicalParty;
  private $twitter;
  private $facebook;
  private $instagram;
  private $imageUrl;
  private $level;
  private $chamber;

  /**
   * Create an official from the Official class.
   */
  public function createOfficial() {
    //Check if rep already exists, create or update accordingly
    $exists = \Civi\Api4\Contact::get(FALSE)
      ->addWhere('external_identifier', '=', $this->externalIdentifier)
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
    $result = $official
      ->addValue('contact_type', 'Individual')
      ->addValue('contact_sub_type', ['Official'])
      ->addValue('do_not_email', TRUE)
      ->addValue('first_name', $this->firstName)
      ->addValue('middle_name', $this->middleName)
      ->addValue('last_name', $this->lastName)
      ->addValue('nick_name', $this->nickName)
      ->addValue('image_URL', $this->imageUrl)
      ->addValue('official_info.electoral_party', $this->politicalParty)
      ->addValue('official_info.electoral_office', $this->title)
      ->addValue('job_title', $this->title)
      ->addValue('official_info.electoral_ocd_id_official', $this->ocdId)
      ->addValue('official_info.electoral_current_term_start_date', $this->currentTermStartDate)
      ->addValue('official_info.electoral_term_end_date', $this->termEndDate)
      ->addValue('official_info.electoral_official_chamber', $this->chamber)
      ->addValue('official_info.electoral_official_level', $this->level)
      ->execute()
      ->first();
    $cid = $result['id'];

    // Create the email, phone, address.
    foreach ($this->emailAddress as $locationType => $email) {
      $this->createEmail($cid, $email, $locationType);
    };

    foreach ($this->phone as $locationType => $phone) {
      $this->createPhone($cid, $phone, $locationType);
    };

    foreach ($this->address as $locationType => $address) {
      $this->createAddress($cid, $address, $locationType);
    };

    $websiteList = [
      'Website' => $this->website,
      'Twitter' => $this->twitter,
      'Facebook' => $this->facebook,
      'Instagram' => $this->instagram,
    ];

    foreach ($websiteList as $websiteType => $url) {
      if ($url) {
        $this->createWebsite($cid, $url, $websiteType);
      }
    }
  }

  public function getName() {
    return $this->firstName . ' ' . $this->lastName;
  }

  /**
   * Helper function to check if website exists
   * and if not, create it
   */
  private function createWebsite($contactId, $website, $websiteType) {
    //Check if contact has a website set, Main location type
    $websiteExist = \Civi\Api4\Website::get(FALSE)
      ->addSelect('id', 'url')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('website_type_id:name', '=', $websiteType)
      ->execute()
      ->first();
    //Add an updated website or a new one if none exist,
    //and set it to primary
    if (!isset($websiteExist) || $websiteExist['url'] != strtolower($website)) {
      \Civi\Api4\Website::create(FALSE)
        ->addValue('website_type_id:name', $websiteType)
        ->addValue('url', $website)
        ->addValue('contact_id', $contactId)
        ->execute();
    }
  }

  /**
   * Helper function to check if email exists
   * and if not, create it
   */
  private function createEmail($contactId, $email, $locationType) {
    //Check if contact has an email address set, Main location type
    $emailExist = \Civi\Api4\Email::get(FALSE)
      ->addSelect('id', 'email')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('location_type_id:name', '=', $locationType)
      ->execute()
      ->first();
    //Add an updated email address or a new one if none exist,
    //and set it to primary
    if (!isset($emailExist) || $emailExist['email'] != strtolower($email)) {
      \Civi\Api4\Email::create(FALSE)
        ->addValue('location_type_id:name', $locationType)
        ->addValue('email', $email)
        ->addValue('contact_id', $contactId)
        ->addValue('is_primary', 1)
        ->execute();
    }
  }

  /**
   * Helper function to check if phone exists
   * and if not, create it
   */
  private function createPhone($contactId, $phone, $locationType) {
    //Check if contact has a phone set, Main location type
    $phoneExist = \Civi\Api4\Phone::get(FALSE)
      ->addSelect('id', 'phone')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('location_type_id:name', '=', $locationType)
      ->execute()
      ->first();
    //Add an updated phone number or a new one if none exist,
    //and set it to primary
    if (!isset($phoneExist) || $phoneExist['phone'] != $phone) {
      \Civi\Api4\Phone::create(FALSE)
        ->addValue('location_type_id:name', $locationType)
        ->addValue('phone_type_id:name', 'Phone')
        ->addValue('phone', $phone)
        ->addValue('contact_id', $contactId)
        ->addValue('is_primary', 1)
        ->execute();
    }
  }

  /**
   * Helper function to check if address exists
   * and if not, create it
   */
  private function createAddress($contactId, $address, $locationType) {
    //Check if contact has an address set
    $addressExist = \Civi\Api4\Address::get(FALSE)
      ->addSelect('id', 'street_address')
      ->addWhere('contact_id', '=', $contactId)
      ->addWhere('location_type_id:name', '=', $locationType)
      ->execute()
      ->first();

    //Add an updated address or a new one if none exist,
    //and set it to primary
    if (!isset($addressExist) || $addressExist['street_address'] != $address['street_address']) {
      // Ugh, can't do state by abbreviation in APIv4.
      $countryId = \Civi\Api4\Country::get()
        ->addSelect('id')
        ->addWhere('name', '=', $address['country'])
        ->execute()->first()['id'];
      if (empty($countryId)) {
        \Civi\log()->debug("Failed to located country Id for " . $address['country']);
        return;
      }
      $stateProvinceId = \Civi\Api4\StateProvince::get(FALSE)
        ->addSelect('id')
        ->addClause('OR', ['abbreviation', '=', $address['state_province']], ['name', '=', $address['state_province']])
        ->addWhere('country_id', '=', $countryId)
        ->execute()
        ->first()['id'];

      if (empty($stateProvinceId)) {
        \Civi\log()->debug("Failed to located state province Id for " . $address['state_province']);
        return;
      }
      \Civi\Api4\Address::create(FALSE)
        ->addValue('location_type_id:name', $locationType)
        ->addValue('contact_id', $contactId)
        ->addValue('street_address', $address['street_address'])
        ->addValue('supplemental_address_1', $address['supplemental_address_1'])
        ->addValue('supplemental_address_2', $address['supplemental_address_2'])
        ->addValue('city', $address['city'])
        ->addValue('state_province_id', $stateProvinceId)
        ->addValue('country:name', $address['country'])
        ->addValue('county:name', $address['county'])
        ->addValue('postal_code', $address['postal_code'])
        ->execute();
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
   * Set the value of address.
   * Acceptable array values are: street_address, supplemental_address_1,
   * supplemental_address_2, city, state_province, postal_code, country, county.
   * Note that all these fields are by name not id - e.g. country name, not country_id.
   *
   * @return  self
   */
  public function setAddress(array $address, $locationType = 'Main') {
    if ($address) {
      if (!isset($address['street_address'])) {
        throw new CRM_Core_Exception("street_address is a required array element.");
      }
      $this->address[$locationType] = $address;
    }
    return $this;
  }

  /**
   * Set the value of emailAddresses
   *
   * @return  self
   */
  public function setEmailAddress($emailAddress, $locationType = 'Main') {
    if ($emailAddress) {
      $this->emailAddress[$locationType] = $emailAddress;
    }
    return $this;
  }

  /**
   * Set the value of phone
   *
   * @return  self
   */
  public function setPhone($phone, $locationType = 'Main') {
    if ($phone) {
      $this->phone[$locationType] = $phone;
    }
    return $this;
  }

  /**
   * Set the value of website
   *
   * @return  self
   */
  public function setWebsite($website) {
    if ($website) {
      $this->website = $website;
    }
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
    if (strpos($twitter, 'twitter.com') === FALSE) {
      $twitter = 'https://twitter.com/' . $twitter;
    }
    $this->twitter = $twitter;
    return $this;
  }

  /**
   * Set the value of facebook
   *
   * @return  self
   */
  public function setFacebook($facebook) {
    if (strpos($facebook, 'facebook.com') === FALSE) {
      $facebook = 'https://facebook.com/' . $facebook;
    }
    $this->facebook = $facebook;
    return $this;
  }

  /**
   * Set the value of instagram
   *
   * @return  self
   */
  public function setInstagram($instagram) {
    if (strpos($instagram, 'instagram.com') === FALSE) {
      $instagram = 'https://instagram.com/' . $instagram;
    }
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

  /**
   * Set the value of chamber
   *
   * @return  self
   */
  public function setChamber($chamber) {
    $this->chamber = $chamber;
    return $this;
  }

  /**
   * Set the value of level
   *
   * @return  self
   */
  public function setLevel($level) {
    $this->level = $level;
    return $this;
  }

  /**
   * Get first email address
   */
  public function getEmailAddress() {
    foreach($this->emailAddress as $locationType => $email) {
      if ($email) {
        return $email;
      }
    }
    return NULL;
  }

  /**
   * Get image URL
   */
  public function getImageUrl() {
    return $this->imageUrl;
  }

  /**
   * Get first name
   */
  public function getFirstName() {
    return $this->firstName;
  }

  /**
   * Get last name
   */
  public function getLastName() {
    return $this->lastName;
  }

  /**
   * Get  OCD ID
   */
  public function getOcdId() {
    return $this->ocdId;
  }

  /**
   * Get title
   */
  public function getTitle() {
    return $this->title;
  }

  /**
   * Get chamber
   */
  public function getChamber() {
    return $this->chamber;
  }

  /**
   * Get level
   */
  public function getLevel() {
    return $this->level;
  }

  /**
   * Get external identifier 
   */
  public function getExternalIdentifier() {
    return $this->externalIdentifier;
  }

}
