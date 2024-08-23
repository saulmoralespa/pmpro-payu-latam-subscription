<?php
/*
Plugin Name: payU Latam Subscription for Paid Memberships Pro
Description: Plugin to add payU Latam Subscription gateway into Paid Memberships Pro
Version: 0.1
Author: Saul Morales Pacheco
Author URI: https://saulmoralespa.com
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
Requires Plugins: paid-memberships-pro
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if(!defined('PMPRO_PAYU_LATAM_SUBSCRIPTION_VERSION')){
    define('PMPRO_PAYU_LATAM_SUBSCRIPTION_VERSION', '0.1');
}

add_action( 'plugins_loaded', 'pmpro_payu_latam_subscription_init');


function pmpro_payu_latam_subscription_init(): void
{
    pmpro_payu_latam_subscription()->run_payu();
}

function pmpro_payu_latam_subscription_notices($notice): void
{
    ?>
    <div class="error notice">
        <p><?php echo esc_html( $notice ); ?></p>
    </div>
    <?php
}

function pmpro_payu_latam_subscription(){
    static $plugin;
    if (!isset($plugin)){
        require_once('includes/class-pmpro-payu-latam-subscription-plugin.php');
        $plugin = new PMPRO_Payu_Latam_Subscription_Plugin(__FILE__, PMPRO_PAYU_LATAM_SUBSCRIPTION_VERSION);
    }
    return $plugin;
}

function activate_pmpro_payu_latam_subscription(): void
{
    if ( ! wp_next_scheduled( 'pmpro_cron_payulatamsubscription_updates'  ) ) {
        wp_schedule_event(time(), 'daily', 'pmpro_cron_payulatamsubscription_updates');
    }
}

function deactivation_pmpro_payu_latam_subscription(): void
{
    wp_clear_scheduled_hook( 'pmpro_cron_payulatamsubscription_updates' );
}

register_activation_hook( __FILE__, 'activate_pmpro_payu_latam_subscription' );
register_deactivation_hook( __FILE__, 'deactivation_pmpro_payu_latam_subscription' );