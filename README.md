# Electoral API

A CiviCRM extension to automatically add information about your contacts'
electoral districts.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.2+
* CiviCRM 5.45+

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Configuration

### Settings page

After installation, configure the extension at **Administer » System Settings »
Electoral API**.  See screenshot below, with an explanation of each option:

![Screenshot of Electoral API Settings page](/images/settings_screen.png)

* **Data Provider(s)**:  Electoral API ships with support for Azavea Cicero,
  Google Civic Information, and Openstates.  Select the provider(s) you want to
  use.
* **Cicero API Key**/**Google Civic Information API Key**/**Openstates API
  Key**: When you [register with
  Cicero](https://www.cicerodata.com/free-trial/), [register with Google
  Developers](https://developers.google.com/civic-information/docs/using_api#APIKey),
  or [register with Openstates](https://openstates.org/accounts/signup/) you
  will receive an "API key", which gives you access to information using your
  registered account.  Put the API key in this field.
* **Districts to Look Up**:  Select the types of electoral districting data you
  want.  For electoral data, choose "Country", "State", "County" or "City"
  levels. Cicero offers additional lookups for "Voting", "Judicial", "Police",
  and "School" (each additional lookup costs an additional credit).  Openstates
  only supports the "Country" and "State" levels.
* **Include Future Districts** (Cicero only): If data exists for future
  electoral districts in your area, you can enable this option to download
  them.  You can distinguish current and future districts with the "Valid From"
  and "Valid To" dates (see *Usage*, below).
* **Address location for district lookup**: Choose the address (home, work,
  etc.) you want to use to determine a contact's districts.
* **Countries**/**States**/**Counties**: You can choose to only look up
  district information for contacts whose address falls in certain
  countries/states/counties. You can also select the corresponding **All
  Countries/All States/All Counties**.
* **District Lookup on Address Update**: If you enable this option, district
  data will get looked up every time you save an address that matches the
  criteria above. If you do not enable this option, district data will only be
  populated by scheduled job (see below).

### Scheduled Job

Under **Administration menu » System Settings » Scheduled Jobs** you will find
a job titled *Electoral API - Districts Lookup*.  By enabling this job, CiviCRM
will populate the districts of all contacts whose addresses meet the criteria
specified on the Settings page.  Default is 100 contacts a day.  You can
optionally set the parameters `limit`, `update`, or `groups` for example:

```
limit=100
update=false
groups=Administrators|Staff
```

* **Limit** indicates how many contacts to look up districts for in a single
  run. Default: 100
* **Update** set to false will not look up district data for contacts that
  already have it.  **Update** set to true will include contacts that have
  already been looked up and overwrite their existing district data.  Default:
  false
* **groups** will restrict look ups to contacts in the matching pipe (|) separated
  list of group names. 

## Usage

### Districts

After installation, you will see a new tab on all contacts called *Electoral
Districts*, see screenshot below:

![Screenshot of 'Electoral Districts' tab](/images/districts.png)

This displays the *level* of government, the *state or province* of the
district, the *county* and *city* if applicable, the *chamber*, the *district
number*, a *note* if one is available, and a *Last Updated* field showing when
the data was added.  Cicero users will also have a *Valid From* and *Valid To*
field indicating when redistricting may invalidate the information.

CiviCRM sees district data as a multi-record custom field, and is available in
searches, reports, etc. as such.

## Migrate

An api is provided for migrating from the [Drupal civicrm_cicero
module](https://www.drupal.org/project/civicrm_cicero) to this extension. Only
the following will be migrated:

 * National House District
 * State Lower
 * State Upper
 * City Council

To run the migration, first test with one contact (replace NNN with the contact
id of the record you want to test):
    
`cv api Electoral.Ciceromigrate contact_id=NNN`

When you are satisfied that it works with your configuration, run for multiple
contacts, with a limit:

`cv api Electoral.Ciceromigrate limit=10`

When you are really sure it is all working, remove the limit.

## Known Issues

* Google and Cicero both support data for multiple countries, but Google has
  not been tested on non-US locations. Open States only works for US addresses.
