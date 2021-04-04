(function ($) {
  $('table.crm-multifield-selector.crm-ajax-table').on('crmLoad', function() {
    // Hide the ocd_id when viewing the custom fields.
    // columnNumber = document.querySelectorAll('[data-data="electoral_valid_from"]')[0].cellIndex;
    // table = document.querySelectorAll('[data-data="electoral_valid_to"]')[0].closest('table');
    // for (let row of table.rows) {
    //   row.cells[columnNumber].style.display = "none";
    // }
  });
})(CRM.$);