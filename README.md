# Electoral API

A CiviCRM extension to automatically add information about your contacts' electoral districts and elected officials.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

## Requirements

* PHP v7.2+
* CiviCRM 5.33+

## Installation (Web UI)

This extension has not yet been published for installation via the web UI.

## Installation (CLI, Zip)

Sysadmins and developers may download the `.zip` file for this extension and
install it with the command-line tool [cv](https://github.com/civicrm/cv).

```bash
cd <extension-dir>
cv dl eventonbehalfof@https://github.com/FIXME/eventonbehalfof/archive/master.zip
```

## Configuration

### Settings page
After installation, configure the extension at **Administer » System Settings » Electoral API**.  See screenshot below, with an explanation of each option:

![Screenshot of Electoral API Settings page](/images/settings_screen.png)

* **Data Provider(s)**:  Electoral API ships with support for Azavea Cicero and Google Civic Information.  Select the provider(s) you want to use.
* **Cicero API Key**/**Google Civic Information API Key**: When you [register with Cicero](https://www.cicerodata.com/free-trial/) or [register with Google Developers](https://developers.google.com/civic-information/docs/using_api#APIKey) you will receive an "API key", which gives you access to information using your registered account.  Put the API key in this field.
* **Districts to Look Up**:  Select the types of electoral districting data you want.  Cicero options are "Legislative", "Voting", "Judicial", "Police", and "School".  Google Civic only provides electoral data, but you can choose to only collect data at the "Country", "State/Province", "County", and "City" levels.
* **Include Future Districts** (Cicero only): If data exists for future electoral districts in your area, you can enable this option to download them.  You can distinguish current and future districts with the "Valid From" and "Valid To" dates (see *Usage*, below).
* **Address location for district lookup**: Choose the address (home, work, etc.) you want to use to determine a contact's districts.
* **Countries**/**States**/**Counties**: You can choose to only look up district information for contacts whose address falls in certain countries/states/counties.  You can also select the corresponding **All Countries/All States/All Counties**.
* **District Lookup on Address Update**: If you enable this option, district data will get looked up every time you save an address that matches the criteria above.  If you do not enable this option, district data will only be populated by scheduled job (see below).
* **Create Official on District Lookup** (Cicero only): When doing a district lookup, Cicero can also populate any elected officials of the contact without expending another credit.

### Scheduled Job
Under **Administration menu » System Settings » Scheduled Jobs** you will find a job titled *Electoral API - Districts Lookup*.  By enabling this job, CiviCRM will populate the districts of all contacts whose addresses meet the criteria specified on the Settings page.  Default is 100 contacts a day.  You can optionally set the parameters `limit` and `update`, for example:
```
limit=100
update=false
```

* **Limit** indicates how many contacts to look up districts for in a single run. Default: 100
* **Update** set to false will not look up district data for contacts that already have it.  **Update** set to false will overwrite existing district data.  Default: false

### Performance note
It is not recommended to enable both **District Lookup on Address Update** and **Create Official on District Lookup** in most cases, since adding a single address could cause ten or more additional contacts to be created, slowing performance.  However, if you have most elected officials already in your database, the penalty for the occasional missing official is relatively low.

## Usage

### Districts
After installation, you will see a new tab on all contacts called *Electoral Districts*, see screenshot below:

![Screenshot of 'Electoral Districts' tab](/images/districts.png)

This displays the *level* of government, the *state or province* of the district, the *county* and *city* if applicable, the *chamber*, the *district number*, a *note* if one is available, and a *Last Updated* field showing when the data was added.  Cicero users will also have a *Valid From* and *Valid To* field indicating when redistricting may invalidate the information.

CiviCRM sees district data as a multi-record custom field, and is available in searches, reports, etc. as such.


### Officials

A new contact subtype "Official" is created upon installation.  Contacts of type Official have an additional tab *Official Info*, which contains the name of the office they hold, their political party, the start and end date of their term, and a unique identifier for their region ("Open Civic Data ID") for integration with other tools.  Additionally, all available information (name, contact info, photo) will be added to the contact's *Summary* tab.  See below.

![Screenshot of Summary tab for an elected official](/images/official_summary.png)

![Screenshot of Official Info tab for an elected official](/images/official_info.png)

## Known Issues

* Officials lookup from Google Civic Data is not currently supported.  The previous version of this extension (2.0) supports this.  If you need this functionality, please do not upgrade at this time.
* Google and Cicero both support data for multiple countries, but Google has not been tested on non-US locations.
