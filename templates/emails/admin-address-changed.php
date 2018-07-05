<?php

namespace Netzstrategen\Ssofact;

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?= __('The {address_type} address of {customer} has been edited on {site_url}:', Plugin::L10N) ?></p>

<hr>

<?= __('Customer: {customer}', Plugin::L10N) ?>

{address}

<hr>
