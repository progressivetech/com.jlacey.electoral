<?php

use CRM_Electoral_ExtensionUtil as E;
use Civi\Test\HeadlessInterface;
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

/**
 * Mock Geocode Provider class
 *
 * Used to avoid network lookups while testing.
 */
class mockGeocodeProviderClass {
  public static function format(&$params) {
    $params['geo_code_1'] = '40.67650';
    $params['geo_code_2'] = '-73.96918';
    return TRUE;
  }
}

/**
 * Test sinlge address lookup against all providers.
 *
 * @group headless
 */
class SingleAddressLookupTest extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface, TransactionalInterface {

  protected $addressId;
  protected $contactId;

  public function setUpHeadless() {
    return \Civi\Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
    // Add a contact.
    $this->contactId = \Civi\Api4\Contact::create()
      ->addValue('contact_type', 'Individual')
      ->addValue('first_name', 'Maria')
      ->addValue('middle_name', 'Q')
      ->addValue('last_name', 'Voter')
      ->addChain('create_address', \Civi\Api4\Address::create()->setValues(['contact_id' => '$id', 'street_address' => '431 Park Pl', 'city' => 'Brooklyn', 'state_province_id' => '1031', 'postal_code' => '11238', 'country_id' => 1228]))
      ->execute()->first()['id'];

    // Record the address id.
    $this->addressId = \Civi\Api4\Address::get()
      ->addSelect('id')
      ->addWhere('contact_id', '=', $this->contactId)
      ->execute()->first()['id'];
  }

  public function tearDown(): void {
    \Civi\Api4\Contact::delete()
      ->addWhere('id', '=', $this->contactId)
      ->execute();
    parent::tearDown();
  }

  /**
   * Test single address lookup for open states.
   */
  public function testOpenStates() {
    // We don't actually do an OpenStates lookup, but we have to have a
    // key or we get an error.
    \Civi\Api4\Setting::set()
      ->addValue('openstatesAPIKey', 'foo123')
      ->execute();
    \Civi\Api4\Setting::set()
      ->addValue('electoralApiDistrictTypes', ['country', 'administrativeArea1'])
      ->execute();

    // Create a mock guzzle client, specify exactly the response we
    // should get from running a real query against Open States.
    $mock = new \GuzzleHttp\Handler\MockHandler([
      new \GuzzleHttp\Psr7\Response(200, [], $this->OpenstatesJsonResults()),
    ]);

    $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
    $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

    $os = new Civi\Electoral\Api\Openstates();
    $os->setGuzzleClient($client);
    $os->setGeocodeProviderClass('mockGeocodeProviderClass');
    $os->singleAddressLookup($this->addressId);
    $districts = \Civi\Api4\Contact::get()
        ->addSelect('electoral_districts.*')
        ->addWhere('id', '=', $this->contactId)
        ->execute();
    $this->assertEquals($districts->count(), 3);
    foreach($districts as $district) {
      if ($district['electoral_districts.electoral_level'] == 'administrativeArea1') {
        if ($district['electoral_districts.electoral_chamber'] == 'lower') {
          $this->assertEquals($district['electoral_districts.electoral_district'], 57);
        }
        else if ($district['electoral_districts.electoral_chamber'] == 'upper') {
          $this->assertEquals($district['electoral_districts.electoral_district'], 20);
        }
      }
      else if ($district['electoral_districts.electoral_level'] == 'country') {
        $this->assertEquals($district['electoral_districts.electoral_district'], 'NY-9');
      }
    }

  }

  /**
   * Test single address lookup for google civic.
   */
  public function testGoogleCivic() {
    \Civi\Api4\Setting::set()
      ->addValue('googleCivicInformationAPIKey', 'foo123')
      ->execute();
    \Civi\Api4\Setting::set()
      ->addValue('electoralApiDistrictTypes', ['country', 'administrativeArea1', 'administrativeArea2', 'county', 'city'])
      ->execute();

    // Create a mock guzzle client, specify exactly the response we
    // should get from running a real query against Open States.
    $mock = new \GuzzleHttp\Handler\MockHandler([
      new \GuzzleHttp\Psr7\Response(200, [], $this->GoogleCivicJsonResults()),
    ]);

    $handlerStack = \GuzzleHttp\HandlerStack::create($mock);
    $client = new \GuzzleHttp\Client(['handler' => $handlerStack]);

    $os = new Civi\Electoral\Api\GoogleCivicInformation();
    $os->setGuzzleClient($client);
    $os->setGeocodeProviderClass('mockGeocodeProviderClass');
    $os->singleAddressLookup($this->addressId);
    $districts = \Civi\Api4\Contact::get()
        ->addSelect('electoral_districts.*')
        ->addWhere('id', '=', $this->contactId)
        ->execute();
    $this->assertEquals($districts->count(), 6);
    foreach($districts as $district) {
      if ($district['electoral_districts.electoral_level'] == 'administrativeArea1') {
        if ($district['electoral_districts.electoral_chamber'] == 'lower') {
          $this->assertEquals($district['electoral_districts.electoral_district'], 57);
        }
        else if ($district['electoral_districts.electoral_chamber'] == 'upper') {
          $this->assertEquals($district['electoral_districts.electoral_district'], 20);
        }
      }
      else if ($district['electoral_districts.electoral_level'] == 'country') {
        $this->assertEquals($district['electoral_districts.electoral_district'], '9');
      }
    }

  }


  protected function OpenstatesJsonResults() {
    return '{
      "results": [
        {
          "id":"ocd-person/8683c8ab-0d6c-471f-9259-a1b506109912",
          "name":"Phara Souffrant Forrest",
          "party":"Democratic",
          "current_role": {
            "title":"Assembly Member",
            "org_classification":"lower",
            "district":"57",
            "division_id":"ocd-division/country:us/state:ny/sldl:57"
          },
          "jurisdiction":{
          "id":"ocd-jurisdiction/country:us/state:ny/government",
            "name":"New York",
            "classification":"state"
          },
          "given_name":"",
          "family_name":"",
          "image":"https://assembly.state.ny.us/write/upload/member_files/057/headshot/057.jpg",
          "email":"souffrantforrestp@nyassembly.gov",
          "gender":"",
          "birth_date":"",
          "death_date":"",
          "extras":{},
          "created_at":"2021-01-09T00:14:50.467984+00:00",
          "updated_at":"2022-02-19T01:08:20.373856+00:00",
          "openstates_url":"https://openstates.org/person/phara-souffrant-forrest-45pCE4KIyx6HyKZYyDq7so/"
        },
        {
          "id": "ocd-person/969d3e67-5686-5b4c-a8fe-5ec702a7343d",
          "name":"Yvette D. Clarke",
          "party":"Democratic",
          "current_role": {
          "title":"Representative",
            "org_classification":"lower",
            "district":"NY-9",
            "division_id":"ocd-division/country:us/state:ny/cd:9"
          },
            "jurisdiction":{
            "id":"ocd-jurisdiction/country:us/government",
              "name":"United States",
              "classification":"country"
          },
          "given_name":"Yvette",
          "family_name":"Clarke",
          "image":"https://theunitedstates.io/images/congress/450x550/C001067.jpg",
          "email":"",
          "gender":"F",
          "birth_date":"1964-11-21",
          "death_date":"","extras":{},
          "created_at":"2021-05-10T15:10:40.897825+00:00",
          "updated_at":"2022-01-04T03:07:19.608798+00:00",
          "openstates_url":"https://openstates.org/person/yvette-d-clarke-4aChztG65qAUrvrrvBBZRl/"
        },
        {
          "id":"ocd-person/52c0e400-2385-4c12-8bf4-6b9205c18eb2",
          "name":"Zellnor Myrie",
          "party":"Democratic",
          "current_role":{
          "title":"Senator",
            "org_classification":"upper",
            "district":"20",
            "division_id":"ocd-division/country:us/state:ny/sldu:20"
          },
          "jurisdiction":{
          "id":"ocd-jurisdiction/country:us/state:ny/government",
            "name":"New York",
            "classification":"state"
          },
          "given_name":"Zellnor",
          "family_name":"Myrie",
          "image":"https://www.nysenate.gov/sites/default/files/styles/160x160/public/zmyriemainheadimage_0.jpg?itok=O2-EZTeD",
          "email":"myrie@nysenate.gov",
          "gender":"",
          "birth_date":"",
          "death_date":"",
          "extras":{},
          "created_at":"2019-01-13T06:15:42.453689+00:00",
          "updated_at":"2021-10-08T14:55:28.147689+00:00",
          "openstates_url":"https://openstates.org/person/zellnor-myrie-2W9WMrIZovzFCgYZe7bmza/"
        }
      ],
      "pagination":{
      "per_page":10,
      "page":1,
      "max_page":1,
      "total_items":3
      }
    }';
  }

  protected function GoogleCivicJsonResults() {
    return 
    '{
      "normalizedInput": {
        "line1": "426 Sterling Place",
        "city": "Brooklyn",
        "state": "NY",
        "zip": "11238"
      },
      "kind": "civicinfo#representativeInfoResponse",
      "divisions": {
        "ocd-division/country:us/state:ny/place:new_york": {
          "name": "New York city",
          "officeIndices": [
            12,
            13,
            14
          ]
        },
        "ocd-division/country:us/state:ny/sldu:20": {
          "name": "New York State Senate district 20",
          "officeIndices": [
            8
          ]
        },
        "ocd-division/country:us/state:ny/cd:9": {
          "name": "New York\'s 9th congressional district",
          "officeIndices": [
            3
          ]
        },
        "ocd-division/country:us": {
          "name": "United States",
          "officeIndices": [
            0,
            1
          ]
        },
        "ocd-division/country:us/state:ny/supreme_court:2": {
          "name": "NY State Supreme Court - 2nd District"
        },
        "ocd-division/country:us/state:ny": {
          "name": "New York",
          "officeIndices": [
            2,
            4,
            5,
            6,
            7
          ]
        },
        "ocd-division/country:us/state:ny/sldl:57": {
          "name": "New York Assembly district 57",
          "officeIndices": [
            9
          ]
        },
        "ocd-division/country:us/state:ny/county:kings": {
          "name": "Kings County",
          "alsoKnownAs": [
            "ocd-division/country:us/state:ny/borough:brooklyn",
            "ocd-division/country:us/state:ny/place:new_york/county:kings"
          ],
          "officeIndices": [
            10,
            11
          ]
        }
      },
      "offices": [
        {
          "name": "President of the United States",
          "divisionId": "ocd-division/country:us",
          "levels": [
            "country"
          ],
          "roles": [
            "headOfGovernment",
            "headOfState"
          ],
          "officialIndices": [
            0
          ]
        },
        {
          "name": "Vice President of the United States",
          "divisionId": "ocd-division/country:us",
          "levels": [
            "country"
          ],
          "roles": [
            "deputyHeadOfGovernment"
          ],
          "officialIndices": [
            1
          ]
        },
        {
          "name": "U.S. Senator",
          "divisionId": "ocd-division/country:us/state:ny",
          "levels": [
            "country"
          ],
          "roles": [
            "legislatorUpperBody"
          ],
          "officialIndices": [
            2,
            3
          ]
        },
        {
          "name": "U.S. Representative",
          "divisionId": "ocd-division/country:us/state:ny/cd:9",
          "levels": [
            "country"
          ],
          "roles": [
            "legislatorLowerBody"
          ],
          "officialIndices": [
            4
          ]
        },
        {
          "name": "Governor of New York",
          "divisionId": "ocd-division/country:us/state:ny",
          "levels": [
            "administrativeArea1"
          ],
          "roles": [
            "headOfGovernment"
          ],
          "officialIndices": [
            5
          ]
        },
        {
          "name": "Lieutenant Governor of New York",
          "divisionId": "ocd-division/country:us/state:ny",
          "levels": [
            "administrativeArea1"
          ],
          "roles": [
            "deputyHeadOfGovernment"
          ],
          "officialIndices": [
            6
          ]
        },
        {
          "name": "NY State Comptroller",
          "divisionId": "ocd-division/country:us/state:ny",
          "levels": [
            "administrativeArea1"
          ],
          "roles": [
            "governmentOfficer"
          ],
          "officialIndices": [
            7
          ]
        },
        {
          "name": "NY State Attorney General",
          "divisionId": "ocd-division/country:us/state:ny",
          "levels": [
            "administrativeArea1"
          ],
          "roles": [
            "governmentOfficer"
          ],
          "officialIndices": [
            8
          ]
        },
        {
          "name": "NY State Senator",
          "divisionId": "ocd-division/country:us/state:ny/sldu:20",
          "levels": [
            "administrativeArea1"
          ],
          "roles": [
            "legislatorUpperBody"
          ],
          "officialIndices": [
            9
          ]
        },
        {
          "name": "NY State Assembly Member",
          "divisionId": "ocd-division/country:us/state:ny/sldl:57",
          "levels": [
            "administrativeArea1"
          ],
          "roles": [
            "legislatorLowerBody"
          ],
          "officialIndices": [
            10
          ]
        },
        {
          "name": "Brooklyn Borough President",
          "divisionId": "ocd-division/country:us/state:ny/county:kings",
          "levels": [
            "administrativeArea2"
          ],
          "roles": [
            "headOfGovernment"
          ],
          "officialIndices": [
            11
          ]
        },
        {
          "name": "Brooklyn District Attorney",
          "divisionId": "ocd-division/country:us/state:ny/county:kings",
          "levels": [
            "administrativeArea2"
          ],
          "roles": [
            "governmentOfficer"
          ],
          "officialIndices": [
            12
          ]
        },
        {
          "name": "Mayor of New York",
          "divisionId": "ocd-division/country:us/state:ny/place:new_york",
          "levels": [
            "locality"
          ],
          "roles": [
            "headOfGovernment"
          ],
          "officialIndices": [
            13
          ]
        },
        {
          "name": "New York City Comptroller",
          "divisionId": "ocd-division/country:us/state:ny/place:new_york",
          "levels": [
            "locality"
          ],
          "roles": [
            "governmentOfficer"
          ],
          "officialIndices": [
            14
          ]
        },
        {
          "name": "New York Public Advocate",
          "divisionId": "ocd-division/country:us/state:ny/place:new_york",
          "levels": [
            "locality"
          ],
          "roles": [
            "governmentOfficer"
          ],
          "officialIndices": [
            15
          ]
        }
      ],
      "officials": [
        {
          "name": "Joseph R. Biden",
          "address": [
            {
              "line1": "1600 Pennsylvania Avenue Northwest",
              "city": "Washington",
              "state": "DC",
              "zip": "20500"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(202) 456-1111"
          ],
          "urls": [
            "https://www.whitehouse.gov/",
            "https://en.wikipedia.org/wiki/Joe_Biden"
          ],
          "channels": [
            {
              "type": "Twitter",
              "id": "potus"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "The White House 1600 Pennsylvania Avenue NW Washington, DC 20500",
              "featureId": {
                "cellId": "9923602325795527449",
                "fprint": "11513381022022344111"
              },
              "featureType": "typeCompoundBuilding",
              "positionPrecisionMeters": 126.14545494347092,
              "addressUnderstood": true
            }
          ]
        },
        {
          "name": "Kamala D. Harris",
          "address": [
            {
              "line1": "1600 Pennsylvania Avenue Northwest",
              "city": "Washington",
              "state": "DC",
              "zip": "20500"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(202) 456-1111"
          ],
          "urls": [
            "https://www.whitehouse.gov/",
            "https://en.wikipedia.org/wiki/Kamala_Harris"
          ],
          "channels": [
            {
              "type": "Twitter",
              "id": "VP"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "The White House 1600 Pennsylvania Avenue NW Washington, DC 20500",
              "featureId": {
                "cellId": "9923602325795527449",
                "fprint": "11513381022022344111"
              },
              "featureType": "typeCompoundBuilding",
              "positionPrecisionMeters": 126.14545494347092,
              "addressUnderstood": true
            }
          ]
        },
        {
          "name": "Charles E. Schumer",
          "address": [
            {
              "line1": "322 Hart Senate Office Building",
              "city": "Washington",
              "state": "DC",
              "zip": "20510"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(202) 224-6542"
          ],
          "urls": [
            "https://www.schumer.senate.gov/",
            "https://en.wikipedia.org/wiki/Chuck_Schumer"
          ],
          "photoUrl": "http://bioguide.congress.gov/bioguide/photo/S/S000148.jpg",
          "channels": [
            {
              "type": "Facebook",
              "id": "senschumer"
            },
            {
              "type": "Twitter",
              "id": "SenSchumer"
            },
            {
              "type": "YouTube",
              "id": "SenatorSchumer"
            },
            {
              "type": "YouTube",
              "id": "ChuckSchumer"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "322 Hart Senate Office Building, Washington DC 20510",
              "featureId": {
                "cellId": "9923602661160726555",
                "fprint": "13491012159388313795"
              },
              "featureType": "typePostalCode",
              "positionPrecisionMeters": 500,
              "addressUnderstood": false
            }
          ]
        },
        {
          "name": "Kirsten E. Gillibrand",
          "address": [
            {
              "line1": "478 Russell Senate Office Building",
              "city": "Washington",
              "state": "DC",
              "zip": "20510"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(202) 224-4451"
          ],
          "urls": [
            "https://www.gillibrand.senate.gov/",
            "https://en.wikipedia.org/wiki/Kirsten_Gillibrand"
          ],
          "photoUrl": "http://bioguide.congress.gov/bioguide/photo/G/G000555.jpg",
          "channels": [
            {
              "type": "Facebook",
              "id": "KirstenGillibrand"
            },
            {
              "type": "Twitter",
              "id": "SenGillibrand"
            },
            {
              "type": "YouTube",
              "id": "KirstenEGillibrand"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "478 Russell Senate Office Building, Washington DC 20510",
              "featureId": {
                "cellId": "9923602777983093035",
                "fprint": "16754435804240149550"
              },
              "featureType": "typeCompoundBuilding",
              "positionPrecisionMeters": 154.99767133790925,
              "addressUnderstood": false
            }
          ]
        },
        {
          "name": "Yvette D. Clarke",
          "address": [
            {
              "line1": "2058 Rayburn House Office Building",
              "city": "Washington",
              "state": "DC",
              "zip": "20515"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(202) 225-6231"
          ],
          "urls": [
            "https://clarke.house.gov/",
            "https://en.wikipedia.org/wiki/Yvette_Clarke"
          ],
          "photoUrl": "http://bioguide.congress.gov/bioguide/photo/C/C001067.jpg",
          "channels": [
            {
              "type": "Facebook",
              "id": "repyvetteclarke"
            },
            {
              "type": "Twitter",
              "id": "repyvetteclarke"
            },
            {
              "type": "YouTube",
              "id": "RepYvetteClarke"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "2058 Rayburn House Office Building, Washington, DC 20515-3209",
              "featureId": {
                "cellId": "9923602067032561107",
                "fprint": "3004281461341646448"
              },
              "featureType": "typeCompoundBuilding",
              "positionPrecisionMeters": 162.19669259570352,
              "addressUnderstood": false
            }
          ]
        },
        {
          "name": "Kathleen C. Hochul",
          "address": [
            {
              "line1": "170 State Street",
              "city": "Albany",
              "state": "NY",
              "zip": "12224"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(518) 474-8390"
          ],
          "urls": [
            "https://www.governor.ny.gov/",
            "https://en.wikipedia.org/wiki/Kathy_Hochul"
          ],
          "photoUrl": "https://www.governor.ny.gov/sites/governor.ny.gov/files/styles/one_stop_bannercustom_user_desktop_1x/public/thumbnails/image/LGHochul_Podium_Live_hero.jpg?itok=QrGC9tTw",
          "channels": [
            {
              "type": "Facebook",
              "id": "govkathyhochul"
            },
            {
              "type": "Twitter",
              "id": "GovKathyHochul"
            },
            {
              "type": "YouTube",
              "id": "UCNgh6Me2UyKXOuNDCnsCzPg"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "The Capitol, 170 State Street, Albany, NY, 12224-0341",
              "featureId": {
                "cellId": "9934388982548153733",
                "fprint": "1051736727565639358"
              },
              "featureType": "typeGeocodedAddress",
              "positionPrecisionMeters": 0,
              "addressUnderstood": true
            }
          ]
        },
        {
          "name": "Antonio Delgado",
          "party": "Democratic Party"
        },
        {
          "name": "Thomas P. DiNapoli",
          "address": [
            {
              "line1": "110 State Street",
              "city": "Albany",
              "state": "NY",
              "zip": "12236"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(518) 474-4044"
          ],
          "urls": [
            "https://www.osc.state.ny.us/",
            "https://en.wikipedia.org/wiki/Thomas_DiNapoli"
          ],
          "emails": [
            "contactus@osc.state.ny.us"
          ],
          "channels": [
            {
              "type": "Facebook",
              "id": "nyscomptroller"
            },
            {
              "type": "Twitter",
              "id": "NYSComptroller"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "110 State St, Albany, NY 12236",
              "featureId": {
                "cellId": "9934388977789993141",
                "fprint": "8369627291098704690"
              },
              "featureType": "typeCompoundBuilding",
              "positionPrecisionMeters": 71.349114160807432,
              "addressUnderstood": true
            }
          ]
        },
        {
          "name": "Letitia James",
          "address": [
            {
              "line1": "170 State Street",
              "city": "Albany",
              "state": "NY",
              "zip": "12224"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(800) 771-7755"
          ],
          "urls": [
            "https://ag.ny.gov/",
            "https://en.wikipedia.org/wiki/Letitia_James"
          ],
          "emails": [
            "nyag.pressoffice@ag.ny.gov"
          ],
          "channels": [
            {
              "type": "Facebook",
              "id": "newyorkstateag"
            },
            {
              "type": "Twitter",
              "id": "NewYorkStateAG"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "The Capitol, 170 State Street, Albany, NY, 12224-0341",
              "featureId": {
                "cellId": "9934388982548153733",
                "fprint": "1051736727565639358"
              },
              "featureType": "typeGeocodedAddress",
              "positionPrecisionMeters": 0,
              "addressUnderstood": true
            }
          ]
        },
        {
          "name": "Zellnor Y. Myrie",
          "address": [
            {
              "line1": "Legislative Office Building",
              "line2": "198 State Street",
              "city": "Albany",
              "state": "NY",
              "zip": "12247"
            }
          ],
          "party": "Unknown",
          "phones": [
            "(518) 455-2410"
          ],
          "urls": [
            "https://www.nysenate.gov/senators/zellnor-myrie",
            "https://en.wikipedia.org/wiki/Zellnor_Myrie"
          ],
          "emails": [
            "myrie@nysenate.gov"
          ],
          "channels": [
            {
              "type": "Facebook",
              "id": "SenatorMyrie"
            },
            {
              "type": "Twitter",
              "id": "SenatorMyrie"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "Legislative Office Building 198 State St., Rm. 903, Albany, NY 12247",
              "featureId": {
                "cellId": "9934388981325540317",
                "fprint": "16156866100741859412"
              },
              "featureType": "typeCompoundBuilding",
              "positionPrecisionMeters": 109.26895157660013,
              "addressUnderstood": false
            }
          ]
        },
        {
          "name": "Phara Souffrant Forrest",
          "address": [
            {
              "line1": "Legislative Office Building",
              "line2": "198 State Street",
              "city": "Albany",
              "state": "NY",
              "zip": "12248"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(518) 455-5325"
          ],
          "urls": [
            "https://nyassembly.gov/mem/Phara-Forrest",
            "https://en.wikipedia.org/wiki/Phara_Souffrant_Forrest"
          ],
          "emails": [
            "souffrantforrestp@nyassembly.gov"
          ],
          "channels": [
            {
              "type": "Facebook",
              "id": "Phara4Assembly"
            },
            {
              "type": "Twitter",
              "id": "phara4assembly"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "Legislative Office Building 198 State St., Albany, NY 12248",
              "featureId": {
                "cellId": "9934388981325540317",
                "fprint": "16156866100741859412"
              },
              "featureType": "typeCompoundBuilding",
              "positionPrecisionMeters": 109.26895157660013,
              "addressUnderstood": false
            }
          ]
        },
        {
          "name": "Antonio Reynoso",
          "address": [
            {
              "line1": "209 Joralemon Street",
              "city": "Brooklyn",
              "state": "NY",
              "zip": "11201"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(718) 802-3700"
          ],
          "urls": [
            "https://www.brooklyn-usa.org/",
            "https://en.wikipedia.org/wiki/Antonio_Reynoso"
          ],
          "emails": [
            "askreynoso@brooklynbp.nyc.gov"
          ],
          "channels": [
            {
              "type": "Facebook",
              "id": "BKBPReynoso"
            },
            {
              "type": "Twitter",
              "id": "BKBPReynoso"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "209 Joralemon Street, Brooklyn, NY 11201",
              "featureId": {
                "cellId": "9926595799452834959",
                "fprint": "8485857303837410122"
              },
              "featureType": "typeCompoundBuilding",
              "positionPrecisionMeters": 38.99534941647542,
              "addressUnderstood": true
            }
          ]
        },
        {
          "name": "Eric Gonzalez",
          "address": [
            {
              "line1": "350 Jay Street",
              "city": "Brooklyn",
              "state": "NY",
              "zip": "11201"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(718) 250-2340"
          ],
          "urls": [
            "http://www.brooklynda.org/",
            "https://en.wikipedia.org/wiki/Eric_Gonzalez_%28lawyer%29"
          ],
          "channels": [
            {
              "type": "Facebook",
              "id": "BrooklynDA"
            },
            {
              "type": "Twitter",
              "id": "BrooklynDA"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "350 Jay Street, 16th Floor, Brooklyn, NY 11201",
              "featureId": {
                "cellId": "9926595810230556131",
                "fprint": "7718075664352338432"
              },
              "featureType": "typeCompoundSection",
              "positionPrecisionMeters": 0,
              "addressUnderstood": true
            }
          ]
        },
        {
          "name": "Eric L. Adams",
          "address": [
            {
              "line1": "City Hall",
              "city": "New York",
              "state": "NY",
              "zip": "10007"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(212) 639-9675"
          ],
          "urls": [
            "https://www1.nyc.gov/office-of-the-mayor/index.page",
            "https://en.wikipedia.org/wiki/Eric_Adams"
          ],
          "channels": [
            {
              "type": "Facebook",
              "id": "NYCMayor"
            },
            {
              "type": "Twitter",
              "id": "NYCMayor"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "City Hall Park, New York, NY 10007",
              "featureId": {
                "cellId": "9926595589186564801",
                "fprint": "14346043759670404280"
              },
              "featureType": "typePostalCode",
              "positionPrecisionMeters": 836.67461766541214,
              "addressUnderstood": false
            }
          ]
        },
        {
          "name": "Brad Lander",
          "address": [
            {
              "line1": "1 Centre Street",
              "city": "New York",
              "state": "NY",
              "zip": "10007"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(212) 669-3916"
          ],
          "urls": [
            "https://comptroller.nyc.gov/",
            "https://en.wikipedia.org/wiki/Brad_Lander"
          ],
          "emails": [
            "action@comptroller.nyc.gov"
          ],
          "channels": [
            {
              "type": "Facebook",
              "id": "ComptrollerNYC"
            },
            {
              "type": "Twitter",
              "id": "NYCComptroller"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "1 Centre St, Room 517, New York, NY 10007",
              "featureId": {
                "cellId": "9926595631550154875",
                "fprint": "10925191052660297491"
              },
              "featureType": "typeCompoundSection",
              "positionPrecisionMeters": 94.673212854590915,
              "addressUnderstood": true
            }
          ]
        },
        {
          "name": "Jumaane D. Williams",
          "address": [
            {
              "line1": "1 Centre Street",
              "city": "New York",
              "state": "NY",
              "zip": "10007"
            }
          ],
          "party": "Democratic Party",
          "phones": [
            "(212) 669-7200"
          ],
          "urls": [
            "https://www.pubadvocate.nyc.gov/",
            "https://en.wikipedia.org/wiki/Jumaane_Williams"
          ],
          "emails": [
            "reception@advocate.nyc.gov"
          ],
          "channels": [
            {
              "type": "Facebook",
              "id": "NYCPublicAdvocate"
            },
            {
              "type": "Twitter",
              "id": "JumaaneWilliams"
            }
          ],
          "geocodingSummaries": [
            {
              "queryString": "1 Centre Street, 15th Floor, NY, NY 10007",
              "featureId": {
                "cellId": "9926595631550154875",
                "fprint": "14624382921946496804"
              },
              "featureType": "typeCompoundSection",
              "positionPrecisionMeters": 94.673212854590915,
              "addressUnderstood": true
            }
          ]
        }
      ]
    }';
  }

}
