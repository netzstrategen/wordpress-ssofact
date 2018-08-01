'use strict'

jQuery(document).ready(function ($) {

  /**
   * Conditionally displays company address fields.
   */
  $('.field--salutation select').bind('change.ssofact', function () {
    var prefix = this.id.split('_')[0];
    var isCompany = $(this).val() === 'Firma';
    $('#' + prefix + '_company_field, #' + prefix + '_company_contact_field')
      .toggle(isCompany)
      .toggleClass('validate-required', isCompany)
      .find('input').prop({ required: isCompany });
    $('#' + prefix + '_first_name_field, #' + prefix + '_last_name_field')
      .toggle(!isCompany)
      .toggleClass('validate-required', !isCompany)
      .find('input').prop({ required: !isCompany });
  }).trigger('change.ssofact');

});
