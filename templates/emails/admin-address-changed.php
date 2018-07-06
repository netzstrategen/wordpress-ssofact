<?php

namespace Netzstrategen\Ssofact;

/**
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email );
?>

<p><?= __('The following {address_type} has been edited on {site_url}:', Plugin::L10N) ?></p>

<hr>

<p><?= __('Customer: {customer}', Plugin::L10N) ?></p>

<p>{address}</p>

<hr>
