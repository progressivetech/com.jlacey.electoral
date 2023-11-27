// Reload the main page after adding a new district job.
CRM.$(document).ready(function() {
 CRM.$('form#Electoral').submit(function() {
  location.reload();
 });
});
