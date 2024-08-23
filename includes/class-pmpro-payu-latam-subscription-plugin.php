<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class PMPRO_Payu_Latam_Subscription_Plugin
{
    /**
     * Absolute plugin path.
     *
     * @var string
     */
    public string $plugin_path;
    /**
     * Absolute plugin URL.
     *
     * @var string
     */
    public string $plugin_url;
    /**
     * assets plugin.
     *
     * @var string
     */
    public string $assets;
    /**
     * Absolute path to plugin includes dir.
     *
     * @var string
     */
    public string $includes_path;
    /**
     * Absolute path to plugin lib dir
     *
     * @var string
     */
    public string $lib_path;
    /**
     * @var bool
     */
    private bool $bootstrapped = false;

    public function __construct(
        protected $file,
        protected $version
    )
    {
        $this->plugin_path = trailingslashit(plugin_dir_path($this->file));
        $this->plugin_url = trailingslashit(plugin_dir_url($this->file));
        $this->assets = $this->plugin_url . trailingslashit('assets');
        $this->includes_path = $this->plugin_path . trailingslashit('includes');
        $this->lib_path = $this->plugin_path . trailingslashit('lib');
    }

    public function run_payu(): void
    {
        try {
            if ($this->bootstrapped) {
                throw new Exception('payU Latam Subscription for Paid Memberships Pro');
            }
            $this->run();
            $this->bootstrapped = true;
        } catch (Exception $e) {
            if (is_admin() && !defined('DOING_AJAX')) {
                add_action('admin_notices', function () use ($e) {
                    pmpro_payu_latam_subscription_notices($e->getMessage());
                });
            }
        }
    }

    private function run(): void
    {
        if (!class_exists('PMProGateway_payulatamsubscription')) {
            require_once($this->includes_path . 'class-pmpro-payu-latam-subscription.php');
            add_filter( 'pmpro_currencies', array('PMProGateway_payulatamsubscription', 'pmpro_currencies'));
            add_action( 'init', array('PMProGateway_payulatamsubscription', 'init'));
        }

        add_filter('plugin_action_links', array($this, 'plugin_action_links'));
    }

    public function plugin_action_links(array $links): array
    {
        $links[] = '<a href="'.admin_url('admin.php?page=pmpro-paymentsettings').'">'.__('Configuraciones', 'pmpro').'</a>';
        return $links;
    }
}