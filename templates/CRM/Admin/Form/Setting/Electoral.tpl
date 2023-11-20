<h3>Electoral API extension settings</h3>

<div class="crm-block crm-form-block crm-electroal-api-form-block">
  <div class="help">Enter your API configuration below.</a></div>

  <table class="form-layout">
        <tr class="crm-electoral-api-form-block-data-providers">
           <td class="label">{$form.electoralApiProviders.label}</td>
           <td>{$form.electoralApiProviders.html|crmAddClass:huge}<br />
           <span class="description">{ts}Select your electoral data provider(s){/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-cicero-api-key">
           <td class="label">{$form.ciceroAPIKey.label}</td>
           <td>{$form.ciceroAPIKey.html|crmAddClass:huge}<br />
           <span class="description">{ts}Add your registered Cicero API Key.  <a href="https://www.cicerodata.com/free-trial/" target="_blank">Register at Cicero</a> to obtain a key.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-google-civic-information-api-key">
           <td class="label">{$form.googleCivicInformationAPIKey.label}</td>
           <td>{$form.googleCivicInformationAPIKey.html|crmAddClass:huge}<br />
           <span class="description">{ts}Add your registered Google Civic Information API Key.  <a href="https://developers.google.com/civic-information/docs/using_api#APIKey" target="_blank">Register at the Google Civic Information API</a> to obtain a key.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-openstates-api-key">
           <td class="label">{$form.openstatesAPIKey.label}</td>
           <td>{$form.openstatesAPIKey.html|crmAddClass:huge}<br />
           <span class="description">{ts}Add your registered Open States API Key.  <a href="https://openstates.org/accounts/signup/" target="_blank">Register at Open States</a> to obtain a free key.{/ts}</span></td>
       </tr>
        <tr class="crm-electoral-api-form-block-district-types">
           <td class="label">{$form.electoralApiDistrictTypes.label}</td>
           <td>{$form.electoralApiDistrictTypes.html|crmAddClass:huge}&nbsp;&nbsp;<br />
           <span class="description">{ts}Select the district types you want district data for.{/ts}</span>
           <span class="cicero-only description"><br />{ts}Nonlegislative data lookups cost a separate credit per type.{/ts}</span>
           </td>
       </tr>
       <tr id="electoral-future-date" class="crm-electoral-api-form-block-future-date">
           <td class="label">{$form.electoralApiFutureDate.label}</td>
           <td>{$form.electoralApiFutureDate.html}
           <span class="description"><br />{ts}Leave empty to use the current date (default). Or, enter a date in YYYY-MM-DD format and districts that have been re-districted but are not active until after the date you enter will be used. Only available via Cicero.{/ts}</td>
       </tr>
       <tr class="crm-electoral-api-form-block-address-location-type">
           <td class="label">{$form.addressLocationType.label}</td>
           <td>{$form.addressLocationType.html}<br />
           <span class="description">{ts}Select the address location type to use when looking up a contact's districts.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-country">
           <td class="label">{$form.electoralApiIncludedCountries.label}</td>
           <td>{$form.electoralApiIncludedCountries.html|crmAddClass:huge}&nbsp;&nbsp;{$form.electoralApiAllCountries.html}  {$form.electoralApiAllCountries.label}<br />
           <span class="description">{ts}Countries to include in electoral district lookups.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-state-province">
           <td class="label">{$form.includedStatesProvinces.label}</td>
           <td>{$form.includedStatesProvinces.html|crmAddClass:huge}&nbsp;&nbsp;{$form.electoralApiAllStates.html}  {$form.electoralApiAllStates.label}<br />
           <span class="description">{ts}States and Provinces included in electoral district lookups.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-county">
           <td class="label">{$form.includedCounties.label}</td>
           <td>{$form.includedCounties.html|crmAddClass:huge}&nbsp;&nbsp;{$form.allCounties.html}  {$form.allCounties.label}<br />
           <span class="description">{ts}Counties included in electoral district lookups.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-city">
           <td class="label">{$form.includedCities.label}</td>
           <td>{$form.includedCities.html|crmAddClass:huge}&nbsp;&nbsp;{$form.electoralApiAllCities.html}  {$form.electoralApiAllCities.label}<br />
           <span class="description">{ts}Cities included in electoral district lookups.{/ts}</span></td>
       </tr>
        <tr class="crm-electoral-api-form-block-lookup-on-address-update">
           <td class="label">{$form.electoralApiLookupOnAddressUpdate.label}</td>
           <td>{$form.electoralApiLookupOnAddressUpdate.html}<br />
           <span class="description">{ts}Get district data any time an address matching these criteria is added/changed.{/ts}</span></td>
       </tr>
  </table>

  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>


  <h3>Scheduled District Jobs</h3>

  <div class="help">Optionally, to set the district information for a group of contacts, create a new scheduled job on this screen, then ensure the "Electoral Run Scheduled Jobs" is configured to execute in the <a href="/civicrm/admin/job?reset=2">CiviCRM list of scheduled jobs.</a></div>

<div class="action-link">
  <a href="/civicrm/electoral/form" id="newDistrictJob" class="button" target="crm-popup"><i aria-hidden="true" class="crm-i fa-plus-circle"></i> Add New District Job</a>
</div>

  <table class="form-layout-compressed">
        <tr class="crm-electoral-api-form-block-job">
        </tr>
  </table>
</div>

{literal}
  <script type="text/javascript">
  CRM.$(function($) {
    $('#electoralApiProviders').change(function() {
      showHideKeyFields();
    });
    showHideKeyFields();
  });
  function showHideKeyFields() {
    activeProviders = CRM.$('#electoralApiProviders').select2('data');
    ciceroVisible = gCivicVisible = openstatesVisible = false;
    activeProviders.forEach(function (item, index) {
      if (item.text == 'Cicero') {
        ciceroVisible = true;
      }
      if (item.text == 'Google Civic') {
        gCivicVisible = true;
      }
      if (item.text == 'Open States') {
        openstatesVisible = true;
      }
    });

    CRM.$('.cicero-only').toggle(ciceroVisible);
    CRM.$('.crm-electoral-api-form-block-cicero-api-key').toggle(ciceroVisible);
    CRM.$('.crm-electoral-api-form-block-nonlegislative-districts').toggle(ciceroVisible);
    CRM.$('.crm-electoral-api-form-block-google-civic-information-api-key').toggle(gCivicVisible);
    CRM.$('.crm-electoral-api-form-block-openstates-api-key').toggle(openstatesVisible);
    CRM.$('#electoralApiDistrictTypes option').prop( 'disabled', 'disabled');

    // Only show districts to lookup options appropriate for this data provider.
    // All providers handle country and administrativeArea1 (state/local)
    ['country', 'administrativeArea1'].forEach(function (item, index) {
      CRM.$("#electoralApiDistrictTypes option[value='" + item + "']").prop( 'disabled', '' );
    });

    // Only Cicero has future lookups.
    if (ciceroVisible) {
      CRM.$("#electoral-future-date").show();
    }
    else {
      CRM.$("#electoral-future-date").hide();
    }
    // Cicero and Google both handle administartiveArea2 (county) and locality.
    if (ciceroVisible || gCivicVisible) {
      ['administrativeArea2', 'locality'].forEach(function (item, index) {
        CRM.$("#electoralApiDistrictTypes option[value='" + item + "']").prop( 'disabled', '');
      });
    }
    else {
      ['administrativeArea2', 'locality'].forEach(function (item, index) {
        CRM.$("#electoralApiDistrictTypes option[value='" + item + "']").prop( 'disabled', 'disabled');
      });
    }
    // Cicero has special fields which should only be available if it's enabled, and should
    // go away if it's disabled.
    ['voting', 'judicial', 'police', 'school'].forEach(function (item, index) {
      CRM.$("#electoralApiDistrictTypes option[value='" + item + "']").prop( 'disabled', ciceroVisible ? '' : 'disabled').addClass('hidden');
    });
 
    var hideStyle = '#select2-drop .select2-results .select2-result-unselectable {display:none;}';
    var styleSheet = document.createElement("style")
    styleSheet.type = "text/css"
    styleSheet.innerText = hideStyle
    document.head.appendChild(styleSheet)
  }
  </script>
{/literal}
