<?php
/**
 * Plugin Name: WooCommerce Exchange Rate Manager
 * Description: Simple plugin that can convert product pricing to another currency.
 * Author: eightzeros
 * Author URI: eightzeros.io
 * Version: 0.10
 *
 * WC requires at least: 3
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @author    eightzeros
 * @category  WooCommerce, WordPress
 * @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Define Constants
 */
define( 'WC_EXCHANGE_RATE_MANAGER', '0.11' );
define( 'WC_EXCHANGE_RATE_MANAGER_REQUIRED_WOOCOMMERCE_VERSION', 3 );
define( 'WC_EXCHANGE_RATE_MANAGER_DIR', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WC_EXCHANGE_RATE_MANAGER_URI', untrailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'WC_EXCHANGE_RATE_MANAGER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) ); 

require_once( 'includes/helper-functions.php' );

/**
 * Check if WooCommerce is active, and is required WooCommerce version.
 */
if ( ! ezetup_is_woocommerce_active() || version_compare( get_option( 'woocommerce_version' ), WC_EXCHANGE_RATE_MANAGER_REQUIRED_WOOCOMMERCE_VERSION, '<' ) ){
	add_action( 'admin_notices', 'ezetup_woocommerce_inactive_notice' );
	return;
}

class WC_Exchange_Rate_Manager {

    private $id = 'woocommerce-exchange-rate-manager';

    private static $instance;

    public $settings = array(
        'enabled' => 'no',
        'api_url' => '',
        'json_key' => array(),
        'fallback_rate' => '',
        'exclusions' => array(
            'enabled' => 'no',
            'products' => array(),
            'categories' => array(),
        ),
    );

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        // Update options each time the plugins load
        add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

        // Add scripts to admin pages
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts') );

        // Add AJAX actions to populate the select2 boxes
        add_action( 'wp_ajax_get_excluded_products', array( $this, 'get_products') );
        add_action( 'wp_ajax_get_excluded_categories', array( $this, 'get_categories') );

        // Add settings in WooCommerce
        add_filter( 'woocommerce_get_sections_products', array( $this, 'add_settings_section' ) );
        add_filter( 'woocommerce_get_settings_products', array( $this, 'settings_section' ), 10, 2 );
        add_action( 'woocommerce_update_options_products', array( $this, 'update_settings' ) );

        // Simple, grouped and external products
        add_filter('woocommerce_product_get_price', array( $this, 'get_current_price'), 99, 2 );
        add_filter('woocommerce_product_get_regular_price', array( $this, 'get_current_price'), 99, 2 );
        // Variable
        add_filter('woocommerce_product_variation_get_regular_price', array( $this, 'get_current_price'), 99, 2 );
        add_filter('woocommerce_product_variation_get_price', array( $this, 'get_current_price'), 99, 2 );
        // Variation of variable
        add_filter('woocommerce_variation_prices_regular_price', array( $this, 'get_current_variation_price'), 99, 3 );
        add_filter('woocommerce_variation_prices_price', array( $this, 'get_current_variation_price'), 99, 3 );
    }

    public function plugins_loaded() {
        $this->settings[ 'enabled' ] = get_option( 'exchange_rates_enabled', 'no' );
        $this->settings[ 'api_url' ] = get_option( 'exchange_rates_api_url', '' );

        $dirty_json_keys = get_option( 'exchange_rates_json_key', array() );
        $json_keys = array();
        foreach ($dirty_json_keys as $key) {
            $json_keys[] = sanitize_text_field( $key );
        }
        $this->settings[ 'json_key' ] = $json_keys;

        $this->settings[ 'fallback_rate' ] = get_option( 'exchange_rates_fallback_rate', 1 );
        $this->settings[ 'exclusions' ][ 'enabled' ] = get_option( 'exchange_rates_enable_exclusions', 'no' );
        $this->settings[ 'exclusions' ][ 'products' ] = get_option( 'exchange_rates_excluded_products', array() );
        $this->settings[ 'exclusions' ][ 'categories' ] = get_option( 'exchange_rates_excluded_categories', array() );
    }

    public function enqueue_scripts() {
        wp_enqueue_style(
            'select2', 
            WC_EXCHANGE_RATE_MANAGER_URI . '/assets/select2/select2.min.css' 
        );

        wp_enqueue_script(
            'select2', 
            WC_EXCHANGE_RATE_MANAGER_URI . '/assets/select2/select2.min.js', 
            array( 'jquery' ) 
        );

        wp_enqueue_script(
            'woocommerce-exchange-rates', 
            WC_EXCHANGE_RATE_MANAGER_URI . '/assets/js/exchange-rates.js' ,
            array( 'jquery', 'select2' ) 
        );
    }

    public function add_settings_section( $sections ) {
        $sections[ 'exchange_rates' ] = __( 'Exchange Rates', $this->id );
        return $sections;
    }    

    public function settings_section( $settings ) {
        global $current_section;
        if ( 'exchange_rates' == $current_section ) {
            return $this->get_settings();
        }
        else {
            return $settings;
        }
    }    

    public function update_settings() {
        global $current_section;
        if ( 'exchange_rates' == $current_section ) {
            woocommerce_update_options( $this->get_settings() );
            $this->settings[ 'enabled' ] = get_option( 'exchange_rates_enabled', 'no' );
            $this->settings[ 'api_url' ] = get_option( 'exchange_rates_api_url', '' );

            $dirty_json_keys = get_option( 'exchange_rates_json_key', array() );
            $json_keys = array();
            foreach ($dirty_json_keys as $key) {
                $json_keys[] = sanitize_text_field( $key );
            }
            $this->settings[ 'json_key' ] = $json_keys;

            $this->settings[ 'fallback_rate' ] = floatval( get_option( 'exchange_rates_fallback_rate', 1 ) );
            $this->settings[ 'exclusions' ][ 'enabled' ] = get_option( 'exchange_rates_enable_exclusions', 'no' );
            $this->settings[ 'exclusions' ][ 'products' ] = get_option( 'exchange_rates_excluded_products', array() );
            $this->settings[ 'exclusions' ][ 'categories' ] = get_option( 'exchange_rates_excluded_categories', array() );
        }
    }    

    public function get_settings() {
        $settings = array(
            'section_title' => array(
                'id'       => 'exchange_rates_section_title',
                'name'     => __( 'Exchange Rates', $this->id ),
                //'desc'     => __( 'Settings: ' . print_r( $this->settings, TRUE ), $this->id ), // DEBUG
                'type'     => 'title',
            ),
            'enabled' => array(
                'id'   => 'exchange_rates_enabled',
                'name' => __( 'Enable Plugin', $this->id ),
                'type' => 'checkbox',
                'value' => $this->settings[ 'enabled' ],
            ),
            'api_url' => array(
                'id'   => 'exchange_rates_api_url',
                'name' => __( 'API URL', $this->id ),
                'type' => 'url',
                'value' => $this->settings[ 'api_url' ],
            ),
            'json_key' => array(
                'id'   => 'exchange_rates_json_key',
                'name' => __( 'JSON Key', $this->id ),
                'type' => 'multiselect',
                'options' => $this->settings[ 'json_key' ],
            ),
            'fallback_rate' => array(
                'id'   => 'exchange_rates_fallback_rate',
                'name' => __( 'Fallback Rate', $this->id ),
                'type' => 'text',
                'data_type' => 'decimal',
                'value' => $this->settings[ 'fallback_rate' ],
            ),
            'enable_exclusions' => array(
                'id'   => 'exchange_rates_enable_exclusions',
                'name' => __( 'Enable Exclusions', $this->id ),
                'type' => 'checkbox',
                'value' => $this->settings[ 'exclusions' ][ 'enabled' ],
            ),
            'excluded_products' => array(
                'id'   => 'exchange_rates_excluded_products',
                'name' => __( 'Excluded Products', $this->id ),
                'type' => 'multiselect',
                'options' => $this->settings[ 'exclusions' ][ 'products' ],
            ),
            'excluded_categories' => array(
                'id'   => 'exchange_rates_excluded_categories',
                'name' => __( 'Excluded Categories', $this->id ),
                'type' => 'multiselect',
                'options' => $this->settings[ 'exclusions' ][ 'categories' ],
            ),
            'section_end' => array(
                'id' => 'exchange_rates_section_end',
                'type' => 'sectionend',
            )
        );
        return apply_filters( 'wc_settings_exchange_rates_settings', $settings );
    }    

    /**
     * Get a list of products for the settings page. Called using AJAX.
     */
    public function get_products() {
        // TODO: Add name searching functionality
        // This only works for searching the SKU
        $query = new WC_Product_Query( array( 
            'sku' => sanitize_text_field( $_GET['q'] ),
        ) );

        $products = $query->get_products();

        $product_names = array();
        foreach ( $products as $product ) {
            $product_names[] = array(
        		'id' => $product->get_id(), 
        		'name' => $product->get_name(),
        		'sku' => $product->get_sku()
        	);
        }

        wp_send_json( $product_names );
    }

    /**
     * Get a list of product categories for the settings page. Called using AJAX.
     */
    public function get_categories() {
        $args = array(
            'name__like' => sanitize_text_field( $_GET['q'] ),
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'orderby' => 'name',
            'fields' => 'all',
        );

        $categories = get_terms( $args );

        $category_names = array();
        foreach( $categories as $category ) {
            $category_names[] = array(
        		'id' => $category->term_id, 
        		'name' => $category->name,
        		'slug' => $category->slug
            );
        }
        
        wp_send_json( $category_names );
    }

    /**
     * Get the list of excluded products
     */
    public function get_excluded_products() {
        $products = $this->settings[ 'exclusions' ][ 'products' ];
        $product_ids = array();

        foreach( $products as $product_json ) {
            $product = json_decode( $product_json );
            $product_ids[] = $product->id;
        }

        return $product_ids;        
    }

    /**
     * Get the list of excluded categories
     */
    public function get_excluded_categories() {
        $categories = $this->settings[ 'exclusions' ][ 'categories' ];
        $category_ids = array();

        foreach( $categories as $category_json ) {
            $category = json_decode( $category_json );
            $category_ids[] = $category->id;
        }

        return $category_ids;        
    }

    /**
     * Get the exchange rate and store it in a transient
     */
    public function get_exchange_rate() {
        $transient = get_transient( 'current_exchange_rate' );

        if ( ! empty( $transient ) ) {
            return $transient;
        } else {
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)' );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch, CURLOPT_URL, $this->settings[ 'api_url' ] );
            $result = curl_exec( $ch );
            curl_close( $ch );

            $obj = json_decode( $result, true );
            foreach ( $this->settings[ 'json_key' ] as $key ) {
                try {
                    $obj = $obj[ $key ];
                }
                catch ( Exception $e ) {
                    $obj = 0;
                    break;
                }
            }

            $rate = floatval( $obj );

            if ( is_null( $rate ) || $rate === 0 || empty( $rate ) ) {
                $rate = $this->settings[ 'fallback_rate' ];
            }

            set_transient( 'current_exchange_rate', $rate, DAY_IN_SECONDS );

            return $rate;	
        }	
    }

    /**
     * Get the price for each product and multiply it by the current exchange rate before returning
     */
    public function get_current_price( $price, $product ) {
        // Abort if the plugin is activated but disabled
        if ( $this->settings[ 'enabled' ] == 'no' ) {
            return $price;
        }

        if ( $this->settings[ 'exclusions' ][ 'enabled' ] == 'yes' ) {
            // Abort if it's an excluded product
            if ( in_array( $product->get_id(), $this->get_excluded_products() ) ) {
                return $price;
            }

            // Check if the it's in an excluded category and enumerate them
            $excluded_categories = array_intersect( $this->get_excluded_categories(), $product->get_category_ids() );

            // Abort if it's in any excluded categories
            if ( count( $excluded_categories ) > 0 ) {
                return $price;
            }
        }

        // Abort if the price is blank or zero
        if ( is_null( $price ) || $price === 0 || empty( $price ) ) {
            return $price;
        } 

        // Delete product cached price (if needed)
        wc_delete_product_transients($product->get_id());

        // Calculate the USD price based and the current exchange rate
        return $price * $this->get_exchange_rate();
    }

    /**
     * Variations (of a variable product)
     */
    public function get_current_variation_price( $price, $variation, $product ) {	
        // Abort if the plugin is activated but disabled
        if ( $this->settings[ 'enabled' ] == 'no' ) {
            return $price;
        }

        if ( $this->settings[ 'exclusions' ][ 'enabled' ] == 'yes' ) {
            // Abort if it's an excluded product
            if ( in_array( $product->get_id(), $this->get_excluded_products() ) ) {
                return $price;
            }

            // Check if the it's in an excluded category and enumerate them
            $excluded_categories = array_intersect( $this->get_excluded_categories(), $product->get_category_ids() );

            // Abort if it's in any excluded categories
            if ( count( $excluded_categories ) > 0 ) {
                return $price;
            }
        }

        // Abort if the price is blank or zero
        if ( is_null( $price ) || $price === 0 || empty( $price ) ) {
            return $price;
        } 

        // Delete product cached price  (if needed)
        wc_delete_product_transients($variation->get_id());

        //calculate the USD price based on the current exchange rate
        return $price * $this->get_exchange_rate();
    }
}

// Create global singleton
$woocommerce_exchange_rate_manager = WC_Exchange_Rate_Manager::get_instance();

