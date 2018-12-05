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

  /**
   * Injects the pressed form button into AJAX form submissions.
   *
   * jQuery.serialize() does not include button elements, so the backend cannot
   * identify which button has been pressed.
   */
  $('form.checkout :submit').on('click.ssofact', function (e) {
    this.form.form_submit.value = this.id;
  });

});
