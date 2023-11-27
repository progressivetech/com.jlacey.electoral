// Reload the main page after deleting district job.
CRM.$(document).ready(function() {
 CRM.$('form#DeleteDistrictJob').submit(function() {
  location.reload();
 });
});
