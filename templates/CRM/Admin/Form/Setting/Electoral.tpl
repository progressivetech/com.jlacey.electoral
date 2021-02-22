<h3>Electoral API extension settings</h3>

<div class="crm-block crm-form-block crm-electroal-api-form-block">
  <table class="form-layout-compressed">
        <tr class="crm-electroral-api-form-block-data-providers">
           <td>{$form.electoralApiProviders.label}</td>
           <td>{$form.electoralApiProviders.html|crmAddClass:huge}<br />
           <span class="description">{ts}Select your electoral data provider(s){/ts}</span></td>
       </tr>
       <tr class="crm-electroral-api-form-block-cicero-api-key">
           <td>{$form.ciceroAPIKey.label}</td>
           <td>{$form.ciceroAPIKey.html|crmAddClass:huge}<br />
           <span class="description">{ts}Enter your Cicero API Key.  <a href="https://www.cicerodata.com/free-trial/" target="_blank">Register at Cicero</a> to obtain a key.{/ts}</span></td>
       </tr>
       <tr class="crm-electroral-api-form-block-google-civic-information-api-key">
           <td>{$form.googleCivicInformationAPIKey.label}</td>
           <td>{$form.googleCivicInformationAPIKey.html|crmAddClass:huge}<br />
           <span class="description">{ts}Enter your Google Civic Information API Key.  <a href="https://developers.google.com/civic-information/docs/using_api#APIKey" target="_blank">Register at the Google Civic Information API</a> to obtain a key.{/ts}</span></td>
       </tr>
        <tr class="crm-electroral-api-form-block-district-types">
           <td>{$form.electoralApiDistrictTypes.label}</td>
           <td>{$form.electoralApiDistrictTypes.html|crmAddClass:huge}&nbsp;&nbsp;{$form.electoralApiIncludeRedistricted.html}  {$form.electoralApiIncludeRedistricted.label}<br />
           <span class="description">{ts}Select the district types you want district data for.{/ts}</span></td>
           <span class="description">{ts}Cicero only: Include district info going into effect after the next election, if applicable.{/ts}</span></td>
       </tr>
       <tr class="crm-electoral-api-form-block-address-location-type">
           <td>{$form.addressLocationType.label}</td>
           <td>{$form.addressLocationType.html}<br />
           <span class="description">{ts}Select the address location type to use when looking up a contact's districts.{/ts}</span></td>
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
  </table>
  <div class="crm-submit-buttons">{include file="CRM/common/formButtons.tpl" location="bottom"}</div>
</div>
