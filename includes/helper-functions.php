<?php

/**
 * Checks if WooCommerce is active
 */
function ezetup_is_woocommerce_active() {
    $active_plugins = (array) get_option( 'active_plugins', array() );
    
    if ( is_multisite() )
        $active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
    
    return in_array( 'woocommerce/woocommerce.php', $active_plugins ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins );
}


/**
 * Display plugin activation failure notice
 */
function ezetup_woocommerce_inactive_notice() {
    if ( current_user_can( 'activate_plugins' ) ) :
        if ( !class_exists( 'WooCommerce' ) ) :
            ?>
            <div id="message" class="error">
                <p>
                    <?php
                    printf(
                        __( '%sWooCommerce EUR to USD Pricing requires WooCommerce%s %sWooCommerce%s must be active for WooCommerce EUR to USD Pricing to work. Please install & activate WooCommerce.', 'woocommerce-eur-to-usd-pricing' ),
                        '<strong>',
                        '</strong><br>',
                        '<a href="http://wordpress.org/extend/plugins/woocommerce/" target="_blank" >',
                        '</a>'
                    );
                    ?>
                </p>
            </div>
            <?php
        elseif ( version_compare( get_option( 'woocommerce_db_version' ), WC_EUR_TO_USD_PRICING_REQUIRED_WOOCOMMERCE_VERSION, '<' ) ) :
            ?>
            <div id="message" class="error">
                <p>
                    <?php
                    printf(
                        __( '%sWooCommerce EUR to USD Pricing is inactive%s This version of WooCommerce EUR to USD Pricing requires WooCommerce %s or newer.', 'woocommerce-eur-to-usd-pricing' ),
                        '<strong>',
                        '</strong><br>',
                        WC_EUR_TO_USD_PRICING_REQUIRED_WOOCOMMERCE_VERSION
                    );
                    ?>
                </p>
                <div style="clear:both;"></div>
            </div>
            <?php
        endif;
    endif;
}
