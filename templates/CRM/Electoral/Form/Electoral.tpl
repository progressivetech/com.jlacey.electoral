<div class="crm-block crm-form-block crm-electroal-new-district-form-block">
  <h3>Schedule A Districting Job</h3>
  <table class="form-layout">
      <tr class="crm-electoral-api-form-group">
          <td class="label">{$form.group_id.label}</td>
          <td>{$form.group_id.html}<br />
          <span class="description">{ts}The contacts will be selected when you hit submit, not when the job is executed.{/ts}</span></td>
      </tr>
      <tr class="crm-electoral-api-form-limit-per-run">
           <td class="label">{$form.limit_per_run.label}</td>
           <td>{$form.limit_per_run.html}<br />
           <span class="description">{ts}Indicate how many contacts should be districted on each scheduled run.{/ts}</span></td>
      </tr>
      <tr class="crm-electoral-api-form-update">
           <td class="label">{$form.update.label}</td>
           <td>{$form.update.html}<br />
           <span class="description">{ts}Lookup contacts that already have district information in the database?{/ts}</span></td>
       </tr>
  </table>

    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl" location="bottom"}
    </div>

</div>

