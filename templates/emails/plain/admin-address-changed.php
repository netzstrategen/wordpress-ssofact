<?php

namespace Netzstrategen\Ssofact;
?>
= <?= $email_heading ?> =

<?= __('The following {address_type} has been edited on {site_url}:', Plugin::L10N) ?>


=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

<?= __('Customer: {customer}', Plugin::L10N) ?>


{address}

=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=-=

<?php // echo apply_filters( 'woocommerce_email_footer_text', get_option( 'woocommerce_email_footer_text' ) ); ?>
