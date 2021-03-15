<h3>Electoral API extension settings</h3>

<div class="crm-block crm-form-block crm-electroal-api-form-block">
  <table class="form-layout-compressed">
        <tr class="crm-electoral-api-form-block-data-providers">
           <td>{$form.electoralApiProviders.label}</td>
           <td>{$form.electoralApiProviders.html|crmAddClass:huge}<br />
           <span class="description">{ts}Select your electoral data provider(s){/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-cicero-api-key">
           <td>{$form.ciceroAPIKey.label}</td>
           <td>{$form.ciceroAPIKey.html|crmAddClass:huge}<br />
           <span class="description">{ts}Enter your Cicero API Key.  <a href="https://www.cicerodata.com/free-trial/" target="_blank">Register at Cicero</a> to obtain a key.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-google-civic-information-api-key">
           <td>{$form.googleCivicInformationAPIKey.label}</td>
           <td>{$form.googleCivicInformationAPIKey.html|crmAddClass:huge}<br />
           <span class="description">{ts}Enter your Google Civic Information API Key.  <a href="https://developers.google.com/civic-information/docs/using_api#APIKey" target="_blank">Register at the Google Civic Information API</a> to obtain a key.{/ts}</span></td>
       </tr>
        <tr class="crm-electoral-api-form-block-district-types">
           <td>{$form.electoralApiDistrictTypes.label}</td>
           <td>{$form.electoralApiDistrictTypes.html|crmAddClass:huge}&nbsp;&nbsp;{$form.electoralApiIncludeRedistricted.html}  {$form.electoralApiIncludeRedistricted.label}<br />
           <span class="description">{ts}Select the district types you want district data for.{/ts}</span>
           <span class="cicero-only description"><br />{ts}Nonlegislative data lookups cost a separate credit per type.{/ts}</span>
       </tr>
       <tr class="crm-electoral-api-form-block-address-location-type">
           <td>{$form.addressLocationType.label}</td>
           <td>{$form.addressLocationType.html}<br />
           <span class="description">{ts}Select the address location type to use when looking up a contact's districts.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-country">
           <td>{$form.electoralApiIncludedCountries.label}</td>
           <td>{$form.electoralApiIncludedCountries.html|crmAddClass:huge}<br />
           <span class="description">{ts}Select states and provinces to include in API scheduled jobs.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-state-province">
           <td>{$form.includedStatesProvinces.label}</td>
           <td>{$form.includedStatesProvinces.html|crmAddClass:huge}&nbsp;&nbsp;{$form.electoralApiAllStates.html}  {$form.electoralApiAllStates.label}<br />
           <span class="description">{ts}Select states and provinces to include in API scheduled jobs.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-county">
           <td>{$form.includedCounties.label}</td>
           <td>{$form.includedCounties.html|crmAddClass:huge}&nbsp;&nbsp;{$form.allCounties.html}  {$form.allCounties.label}<br />
           <span class="description">{ts}Select counties to include in API scheduled jobs.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-city">
           <td>{$form.includedCities.label}</td>
           <td>{$form.includedCities.html|crmAddClass:huge}<br />
           <span class="description">{ts}Select cities to include in API scheduled jobs.{/ts}</span></td>
       </tr>
        <tr class="crm-electoral-api-form-block-lookup-on-address-update">
           <td>{$form.electoralApiLookupOnAddressUpdate.label}</td>
           <td>{$form.electoralApiLookupOnAddressUpdate.html}<br />
           <span class="description">{ts}Get district data any time an address matching these criteria is added/changed.{/ts}</span></td>
       </tr>
  </table>
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
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
    ciceroVisible = gCivicVisible = false;
    activeProviders.forEach(function (item, index) {
      if (item.text == 'Cicero') {
        ciceroVisible = true;
      }
      if (item.text == 'Google Civic') {
        gCivicVisible = true;
      }
    });
    CRM.$('.cicero-only').toggle(ciceroVisible);
    CRM.$('.crm-electoral-api-form-block-cicero-api-key').toggle(ciceroVisible);
    CRM.$('.crm-electoral-api-form-block-nonlegislative-districts').toggle(ciceroVisible);
    CRM.$('.crm-electoral-api-form-block-google-civic-information-api-key').toggle(gCivicVisible);
    CRM.$('#electoralApiDistrictTypes option').prop( 'disabled', 'disabled');

    // Only show legislative options appropriate for this data provider.
    ['legislative', 'voting', 'judicial', 'police', 'school'].forEach(function (item, index) {
      CRM.$("#electoralApiDistrictTypes option[value='" + item + "']").prop( 'disabled', ciceroVisible ? '' : 'disabled').addClass('hidden');
    });
    ['country', 'administrativeArea1', 'administrativeArea2', 'locality'].forEach(function (item, index) {
      CRM.$("#electoralApiDistrictTypes option[value='" + item + "']").prop( 'disabled', gCivicVisible ? '' : 'disabled').addClass('hidden');
    });
    var hideStyle = '#select2-drop .select2-results .select2-result-unselectable {display:none;}';
    var styleSheet = document.createElement("style")
    styleSheet.type = "text/css"
    styleSheet.innerText = hideStyle
    document.head.appendChild(styleSheet)
  }
  </script>
{/literal}
