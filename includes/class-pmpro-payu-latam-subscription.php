<?php

class PMProGateway_payulatamsubscription extends PMProGateway
{

    /**
     * @var bool    Is the payU/PHP Library loaded
     */
    private static $is_loaded = false;

    public function __construct($gateway = NULL)
    {
        $this->gateway = $gateway;
        $this->gateway_environment = get_option("pmpro_gateway_environment");

        if( true === $this->dependencies() ) {
            self::loadPayuLibrary();

            try {
                PayU::$apiKey = $this->get_api_key();
                PayU::$apiLogin = $this->get_api_login();
                PayU::$merchantId = $this->get_merchant_id();
                PayU::$language = 'es'; //en, pt
                PayU::$isTest = $this->get_is_test();
                Environment::setPaymentsCustomUrl($this->get_payment_url());
                Environment::setReportsCustomUrl($this->get_payment_url(true));
            } catch (Exception $exception){
                global $msg;
                global $msgt;
                global $pmpro_payulatamsubscription_error;

                error_log($exception->getMessage() );

                $pmpro_payulatamsubscription_error = true;
                $msg                   = - 1;
                $msgt                  = sprintf( esc_html__( 'Attempting to load payU Latam Subscription gateway: %s', 'paid-memberships-pro' ), $exception->getMessage() );
                return false;
            }

            self::$is_loaded = true;
        }

        return $this->gateway;
    }

    public static function dependencies() {
        global $msg, $msgt, $pmpro_payu_error;

        if ( version_compare( PHP_VERSION, '5.3.29', '<' ) ) {

            $pmpro_payu_error = true;
            $msg                = - 1;
            $msgt               = sprintf( __( "The payU Latam Subscription Gateway requires PHP 5.3.29 or greater. We recommend upgrading to PHP %s or greater. Ask your host to upgrade.", "paid-memberships-pro" ), PMPRO_MIN_PHP_VERSION );

            if ( ! is_admin() ) {
                pmpro_setMessage( $msgt, "pmpro_error" );
            }

            return false;
        }

        $modules = array( 'curl', 'mbstring', 'json', 'xml' );

        foreach ( $modules as $module ) {
            if ( ! extension_loaded( $module ) ) {
                $pmpro_payu_error = true;
                $msg                = - 1;
                $msgt               = sprintf( __( "The %s gateway depends on the %s PHP extension. Please enable it, or ask your hosting provider to enable it.", 'paid-memberships-pro' ), 'payU Latam Subscription', $module );

                //throw error on checkout page
                if ( ! is_admin() ) {
                    pmpro_setMessage( $msgt, 'pmpro_error' );
                }

                return false;
            }
        }

        self::$is_loaded = true;

        return true;
    }

    public static function supports($feature): bool|string
    {
        $supports = array(
            'subscription_sync' => true,
            'payment_method_updates' => false,
        );

        if (empty($supports[ $feature ])) {
            return false;
        }

        return $supports[ $feature ];
    }

    public static function loadPayuLibrary(): void
    {
        //load Stripe library if it hasn't been loaded already (usually by another plugin using Stripe)
        if (!class_exists("\PayU")) {
            require_once(pmpro_payu_latam_subscription()->lib_path . "PayU.php");
        } else {
            // Another plugin may have loaded the Stripe library already.
            // Let's log the current Stripe Library info so that we know
            // where to look if we need to troubleshoot library conflicts.
            $previously_loaded_class = new \ReflectionClass('\PayU');
            pmpro_track_library_conflict('payulatam', $previously_loaded_class->getFileName(), '4.0.1');
        }
    }

    public static function init(): void
    {
        add_filter('pmpro_gateways', array('PMProGateway_payulatamsubscription', 'pmpro_gateways'));
        add_filter('pmpro_payment_options', array('PMProGateway_payulatamsubscription', 'pmpro_payment_options'));
        add_filter('pmpro_payment_option_fields', array('PMProGateway_payulatamsubscription', 'pmpro_payment_option_fields'), 10, 2);
        //updates cron
        add_action('pmpro_cron_payulatamsubscription_updates', array('PMProGateway_payulatamsubscription', 'pmpro_cron_subscription_updates'));

        $gateway = pmpro_getOption("gateway");
        if ($gateway == "payulatamsubscription") {
            //add_filter('pmpro_include_billing_address_fields', array('PMProGateway_payulatamsubscription', 'pmpro_include_billing_address_fields'));
            //add_filter('pmpro_include_cardtype_field', '__return_false');
            //add_filter('pmpro_include_billing_address_fields', '__return_false');
            add_filter('pmpro_required_billing_fields', array('PMProGateway_payulatamsubscription', 'pmpro_required_billing_fields'));
            add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_payulatamsubscription', 'pmpro_checkout_default_submit_button'));
            add_filter('pmpro_checkout_order', array( 'PMProGateway_payulatamsubscription', 'pmpro_checkout_order'));
            add_filter('pmpro_billing_order', array( 'PMProGateway_payulatamsubscription', 'pmpro_checkout_order'));
            add_filter('pmpro_include_payment_information_fields', array( 'PMProGateway_payulatamsubscription', 'pmpro_include_payment_information_fields'));
            //add_filter('pmpro_checkout_before_change_membership_level', array('PMProGateway_payulatamsubscription', 'pmpro_checkout_before_change_membership_level'), 10, 2);
            add_action('pmpro_after_checkout', array('PMProGateway_payulatamsubscription', 'pmpro_after_checkout'), 10, 2);
            add_action('pmpro_after_checkout_preheader', array('PMProGateway_payulatamsubscription', 'pmpro_checkout_after_preheader'));
            add_action('pmpro_billing_preheader', array( 'PMProGateway_payulatamsubscription', 'pmpro_checkout_after_preheader' ) );
        }
    }

    public static function pmpro_gateways(array $gateways): array
    {
        if (empty($gateways[ 'payulatamsubscription' ]))
            $gateways[ 'payulatamsubscription' ] = __('payU Latam Subscription', 'pmpro');

        return $gateways;
    }

    public static function getGatewayOptions(): array
    {
        return array(
            'merchant_id',
            'account_id',
            'api_login',
            'api_key',
            'public_key',
            'sslseal',
            'nuclear_HTTPS',
            'gateway_environment',
            'currency',
            'use_ssl',
            'tax_state',
            'tax_rate',
            'accepted_credit_cards'
        );
    }

    public static function pmpro_payment_options(array $options): array
    {
        $payu_options = self::getGatewayOptions();

        return [...$payu_options, ...$options];
    }

    public static function pmpro_payment_option_fields($values, $gateway): void
    {
        ?>
        <tr class="pmpro_settings_divider gateway gateway_payulatamsubscription" <?php if($gateway != "payulatamsubscription") { ?>style="display: none;"<?php } ?>>
            <td colspan="2">
                <?php _e('payU  Latam Subscription Configuraciones', 'pmpro'); ?>
            </td>
        </tr>
        <tr class="gateway gateway_payulatamsubscription" <?php if($gateway != "payulatamsubscription") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="merchant_id"><?php _e('Merchant ID', 'pmpro');?>:</label>
            </th>
            <td>
                <input type="number" id="merchant_id" name="merchant_id" size="60" value="<?php echo esc_attr($values['merchant_id'])?>" />
            </td>
        </tr>
        <tr class="gateway gateway_payulatamsubscription" <?php if($gateway != "payulatamsubscription") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="account_id"><?php _e('Account ID', 'pmpro');?>:</label>
            </th>
            <td>
                <input type="number" id="account_id" name="account_id" size="60" value="<?php echo esc_attr($values['account_id'])?>" />
            </td>
        </tr>
        <tr class="gateway gateway_payulatamsubscription" <?php if($gateway != "payulatamsubscription") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="api_login"><?php _e('Api Login', 'pmpro');?>:</label>
            </th>
            <td>
                <input type="password" id="api_login" name="api_login" size="60" value="<?php echo esc_attr($values['api_login'])?>" />
            </td>
        </tr>
        <tr class="gateway gateway_payulatamsubscription" <?php if($gateway != "payulatamsubscription") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="api_key"><?php _e('Api Key', 'pmpro');?>:</label>
            </th>
            <td>
                <input type="password" id="api_key" name="api_key" size="60" value="<?php echo esc_attr($values['api_key'])?>" />
            </td>
        </tr>
        <tr class="gateway gateway_payulatamsubscription" <?php if($gateway != "payulatamsubscription") { ?>style="display: none;"<?php } ?>>
            <th scope="row" valign="top">
                <label for="public_key"><?php _e('Public Key', 'pmpro');?>:</label>
            </th>
            <td>
                <input type="password" id="public_key" name="public_key" size="60" value="<?php echo esc_attr($values['public_key'])?>" />
            </td>
        </tr>
        <script>
            jQuery(document).ready(function(){
                const gateway = jQuery('#gateway').val();

                if(gateway === 'payulatamsubscription'){
                    jQuery('#currency-tax-settings .gateway_check').show();
                }
            });
        </script>
        <?php
    }

    public static function pmpro_currencies(array $currencies): array
    {
        $currencies['COP'] = __('Peso Colombiano (&#36;)', 'paid-memberships-pro' );
        return $currencies;
    }

    public static function pmpro_required_billing_fields(array $fields ): array
    {
        unset($fields['CardType']);
        unset($fields['AccountNumber']);
        unset($fields['ExpirationMonth']);
        unset($fields['ExpirationYear']);
        unset($fields['CVV']);

        return $fields;
    }

    public static function pmpro_checkout_default_submit_button($show): bool
    {
        //show our submit buttons
        ?>
        <span id="pmpro_submit_span">
                    <input type="hidden" name="submit-checkout" value="1" />
                    <input type="submit" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_btn pmpro_btn-submit-checkout', 'pmpro_btn-submit-checkout' ) ); ?>" value="<?php  esc_html_e( 'Suscribirse', 'pmpro' ); ?>" />
                    </span>
        <?php

        //don't show the default
        return false;
    }

    public static function pmpro_checkout_order($morder)
    {
        // Create a code for the order.
        if ( empty( $morder->code ) ) {
            $morder->code = $morder->getRandomCode();
        }

        if ( ! empty ( $_REQUEST['card_number'] ) ) {
            $morder->card_number = sanitize_text_field(preg_replace('/\s+/', '',$_REQUEST['card_number']));
        }

        if ( ! empty ( $_REQUEST['card_name'] ) ) {
            $morder->card_name = sanitize_text_field(preg_replace('/\s+/', '',$_REQUEST['card_name']));
        }

        if ( ! empty ( $_REQUEST['card_expiry'] ) ) {
            $card_expiry = sanitize_text_field(preg_replace('/\s+/', '',$_REQUEST['card_expiry']));
            $expiry = explode('/', $card_expiry);
            $curent_year = wp_date('Y');
            $month = $expiry[0];
            $year = $expiry[ 1 ];
            $year_part = substr($curent_year, -strlen($curent_year), strlen($year));
            $year = strlen($curent_year) === strlen($year) ? $year : $year_part . $year;
            $expiry = "$year/$month";
            $morder->card_expiry = $expiry;
        }

        if ( ! empty ( $_REQUEST['card_cvc'] ) ) {
            $morder->card_cvc = sanitize_text_field(preg_replace('/\s+/', '',$_REQUEST['card_cvc']));
        }

        if ( ! empty ( $_REQUEST['card_type'] ) ) {
            $morder->card_type = sanitize_text_field(preg_replace('/\s+/', '',$_REQUEST['card_type']));
        }

        if ( ! empty ( $_REQUEST['card_document'] ) ) {
            $morder->card_document = sanitize_text_field(preg_replace('/\s+/', '',$_REQUEST['card_document']));
        }

        return $morder;
    }


    public static function pmpro_after_checkout( $user_id, $morder ) {
        global $gateway;

        if ( $gateway == "payulatamsubscription" ) {
            if ( self::$is_loaded && ! empty( $morder ) && ! empty( $morder->Gateway ) && ! empty( $morder->Gateway->customer ) && ! empty( $morder->Gateway->customer->id ) ) {
                update_user_meta( $user_id, "pmpro_payulatam_customerid", $morder->Gateway->customer->id );
            }
        }
    }

    public static function pmpro_include_payment_information_fields($include)
    {
        global $pmpro_requirebilling, $pmpro_show_discount_code, $discount_code, $CardType, $AccountNumber, $ExpirationMonth, $ExpirationYear;

        ?>
        <fieldset id="pmpro_payment_information_fields" class="<?php echo  esc_attr( pmpro_get_element_class( 'pmpro_form_fieldset', 'pmpro_payment_information_fields' ) ); ?>" <?php if ( ! $pmpro_requirebilling || apply_filters( "pmpro_hide_payment_information_fields", false ) ) { ?>style="display: none;"<?php } ?>>
            <div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card' ) ); ?>">
                <div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_card_content' ) ); ?>">
                    <legend class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_legend' ) ); ?>">
                        <h2 class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_heading pmpro_font-large' ) ); ?>"><?php esc_html_e('Payment Information', 'paid-memberships-pro' ); ?></h2>
                    </legend>
                    <div class='card-wrapper'></div>
                    <div class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_fields' ) ); ?>" id="card_payu_latam">
                        <input type="text" id="card_number" name="card_number" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-text', 'card_number' ) );?>" placeholder="Número de tarjeta" required />
                        <input type="text" id="card_name" name="card_name" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-text', 'card_number' ) );?>" placeholder="Titular de la tarjeta" required/>
                        <input type="text" id="card_expiry" name="card_expiry" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-text', 'card_number' ) );?>" placeholder="MM/YY" required/>
                        <input type="text" id="card_cvc" name="card_cvc" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-text', 'card_number' ) );?>" placeholder="CCV" required/>
                        <input type="number" id="card_document" name="card_document" class="<?php echo esc_attr( pmpro_get_element_class( 'pmpro_form_input pmpro_form_input-number', 'card_document' ) );?>" placeholder="Número de identificación" required/>
                        <input type="hidden" id="card_type" name="card_type"/>
                    </div> <!-- end pmpro_form_fields -->
                </div> <!-- end pmpro_card_content -->
            </div> <!-- end pmpro_card -->
        </fieldset> <!-- end pmpro_payment_information_fields -->
        <?php

        //don't include the default
        return false;
    }

    public function process(&$order)
    {
        if(floatval($order->InitialPayment) == 0)
        {
            if($this->authorize($order)){
                $this->void($order);
                $order->ProfileStartDate = pmpro_calculate_profile_start_date( $order, 'Y-m-d\TH:i:s' );
                return $this->subscribe($order);
            }
        }else {
            //charge first payment
            if($this->charge($order))
            {
                if(pmpro_isLevelRecurring($order->membership_level))
                {

                    $order->ProfileStartDate = pmpro_calculate_profile_start_date( $order, 'Y-m-d\TH:i:s' );

                    if($this->subscribe($order))
                    {
                        //yay!
                        return true;
                    }
                    else
                    {
                        //try to refund initial charge
                        return false;
                    }
                }
            }
            else
            {
                if(empty($order->error)) {

                    if ( !self::$is_loaded ) {

                        $order->error = esc_html__("Payment error: Please contact the webmaster (payulatamsubscription-load-error)", "paid-memberships-pro");

                    } else {

                        $order->error = esc_html__( "Unknown error: Initial payment failed.", "paid-memberships-pro" );
                    }
                }

                return false;
            }
        }

        return false;

    }

    function authorize(&$order)
    {
        //create a code for the order
        if(empty($order->code))
            $order->code = $order->getRandomCode();

        //simulate a successful authorization
        $order->payment_transaction_id = "PAYULATAM" . $order->code;
        $order->updateStatus("authorized");
        return true;
    }

    function void(&$order)
    {
        //need a transaction id
        if(empty($order->payment_transaction_id))
            return false;

        //simulate a successful void
        $order->payment_transaction_id = "PAYULATAM" . $order->code;
        $order->updateStatus("voided");
        return true;
    }

    public function charge(&$order)
    {
        global $current_user, $pmpro_currency;

        if ( ! self::$is_loaded ) {
            $order->error = esc_html__("Payment error: Please contact the webmaster (payulatamsubscription-load-error)", "paid-memberships-pro");
            return false;
        }

        //create a code for the order
        if(empty($order->code)){
            $order->code = $order->getRandomCode();
        }

        $amount = $order->InitialPayment;
        $amount_tax = pmpro_round_price_as_string( $order->getTaxForPrice( $amount ) );
        $amount = pmpro_round_price_as_string((float)$amount + (float)$amount_tax);
        $order->subtotal = $amount;

        if(!empty($order->user_id)){
            $user_id = $order->user_id;
        }

        if(empty($user_id) && !empty($current_user->ID)){
            $user_id = $current_user->ID;
        }

        if(empty($user_id)){
            return false;
        }

        if(!$token = $this->get_card_token($order, $user_id)){
            return false;
        }

        try{

            $params_payment = array(
                //Ingresa aquí el identificador de la cuenta
                PayUParameters::ACCOUNT_ID => self::get_account_id(),
                // Ingresa aquí la referencia de pago.
                PayUParameters::REFERENCE_CODE => $order->code,
                // Ingresa aquí la descripción del pago.
                PayUParameters::DESCRIPTION => $order->membership_name,

                // -- Valores --
                //Ingresa aquí el valor.
                PayUParameters::VALUE => $amount,
                // Ingresa aquí la moneda.
                PayUParameters::CURRENCY => $pmpro_currency,

                // -- Comprador --
                // Ingresa aquí la información del comprador.
                //PayUParameters::[...] => [...],


                // -- Pagador --
                // Ingresa aquí la información del pagador.
                //PayUParameters::[...] => [...],

                // -- Datos de la tarjeta de crédito --
                // Ingresa aquí el token de la tarjeta de crédito
                PayUParameters::TOKEN_ID => $token,
                //PayUParameters::CREDIT_CARD_EXPIRATION_DATE => $order->card_expiry,
                // Ingresa aquí el código de seguridad de la tarjeta de crédito
                PayUParameters::CREDIT_CARD_SECURITY_CODE => $order->card_cvc,
                // Ingresa aquí el nombre de la tarjeta de crédito
                PayUParameters::PAYMENT_METHOD => $order->card_type,

                // Ingresa aquí el número de cuotas.
                PayUParameters::INSTALLMENTS_NUMBER => "1",
                // Ingresa aquí el nombre del país.
                PayUParameters::COUNTRY => PayUCountries::CO,

                // Device Session ID
                PayUParameters::DEVICE_SESSION_ID => md5(session_id().microtime()),
                // IP del pagador
                PayUParameters::IP_ADDRESS => pmpro_get_ip(),
                // Cookie de la sesión actual
                PayUParameters::PAYER_COOKIE => "cookie_" . time(),
                // User agent de la sesión actual
                PayUParameters::USER_AGENT => $_SERVER['HTTP_USER_AGENT']
            );

            //wc_get_logger()->add('payu-latam', print_r($params_payment, true));

            // Petición de "Autorización y captura"
            $response = PayUPayments::doAuthorizationAndCapture($params_payment);

            if ($response && $response->transactionResponse->state === "APPROVED") {
                $order->payment_transaction_id = $response->transactionResponse->transactionId;
                $order->updateStatus("success");
                return true;
            }else {
                return false;
            }
        }catch (Exception $exception){
            $order->error = "Error: " . $exception->getMessage();
            $order->shorterror = $order->error;
            return false;
        }
    }

    public function subscribe(&$order)
    {
        global $current_user;

        if(empty($order->code)){
            $order->code = $order->getRandomCode();
        }

        if(!empty($order->user_id)){
            $user_id = $order->user_id;
        }

        if(empty($user_id) && !empty($current_user->ID)){
            $user_id = $current_user->ID;
        }

        if(empty($user_id)){
            return false;
        }

        $token = get_user_meta( $user_id, "pmpro_payulatam_token", true );

        if(!$token){
            $this->get_card_token($order, $user_id);
        }

        //filter order before subscription. use with care.
        $order = apply_filters("pmpro_subscribe_order", $order, $this);

        //simulate a successful subscription processing
        $order->updateStatus("success");
        $order->subscription_transaction_id = "PAYULATAM" . $order->code;

        return true;
    }

    public function update(&$order)
    {
        return parent::update($order); // TODO: Change the autogenerated stub
    }

    public function cancel(&$order)
    {
        if(empty($order->subscription_transaction_id))
            return false;

        if(!empty($order->user_id)){
            $user_id = $order->user_id;
            delete_user_meta($user_id, "pmpro_payulatam_token");
        }

        $order->updateStatus("cancelled");
        return true;
    }

    public function getSubscriptionStatus(&$order)
    {
        return parent::getSubscriptionStatus($order); // TODO: Change the autogenerated stub
    }

    public static function pmpro_cron_subscription_updates(): void
    {
        global $wpdb, $pmpro_currency;

        //Don't let anything run if PMPro is paused
        if( pmpro_is_paused() ) {
            return;
        }

        //clean up errors in the memberships_users table that could cause problems
        pmpro_cleanup_memberships_users_table();

        $wpdb->pmpro_memberships_users = $wpdb->prefix . 'pmpro_memberships_users';
        $wpdb->pmpro_membership_orders = $wpdb->prefix . 'pmpro_membership_orders';
        $wpdb->pmpro_subscriptions = $wpdb->prefix . 'pmpro_subscriptions';
        $wpdb->pmpro_membership_levels = $wpdb->prefix . 'pmpro_membership_levels';
        $date_format = "Y-m-d";
        $today = date($date_format, current_time("timestamp"));
        /*$date = DateTime::createFromFormat($date_format, $today);

        // add 1 month
        $date->modify('+1 month');
        $today = $date->format($date_format);*/

        $subscriptions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT u.user_id, m.name, s.id as subscription_id, s.next_payment_date, s.billing_amount, s.trial_amount, s.trial_limit, s.subscription_transaction_id FROM  $wpdb->pmpro_memberships_users u 
            INNER JOIN $wpdb->pmpro_subscriptions s ON s.membership_level_id = u.membership_id
            INNER JOIN $wpdb->pmpro_membership_orders o ON o.subscription_transaction_id = s.subscription_transaction_id
            INNER JOIN $wpdb->pmpro_membership_levels m ON m.id = u.membership_id
            WHERE u.status = 'active' 
                 AND s.next_payment_date IS NOT NULL
                 AND s.next_payment_date > '0001-01-01'
                 AND DATE(s.next_payment_date) <= %s",
                $today
            )
        );

        if(empty($subscriptions)){
            return;
        }

        new self();

        foreach ($subscriptions as  $subscription){

            $amount = (float)$subscription->billing_amount;
            $amount = pmpro_round_price_as_string($amount);

            $user_id = $subscription->user_id;
            $token = get_user_meta( $user_id, 'pmpro_payulatam_token', true );

            if(!$token) {
                continue;
            }

            try {

                $parameters = array(
                    // Ingresa aquí el identificador del pagador.
                    PayUParameters::PAYER_ID => $user_id,
                    // Ingresa aquí identificador el token.
                    PayUParameters::TOKEN_ID => $token,
                );

                $cards = PayUTokens::find($parameters);
                $card = array_pop($cards->creditCardTokenList);

                $params_payment = array(
                    //Ingresa aquí el identificador de la cuenta
                    PayUParameters::ACCOUNT_ID => self::get_account_id(),
                    // Ingresa aquí la referencia de pago.
                    PayUParameters::REFERENCE_CODE => time(),
                    // Ingresa aquí la descripción del pago.
                    PayUParameters::DESCRIPTION => $subscription->name,

                    // -- Valores --
                    //Ingresa aquí el valor.
                    PayUParameters::VALUE => $amount,
                    // Ingresa aquí la moneda.
                    PayUParameters::CURRENCY => $pmpro_currency,

                    // -- Comprador --
                    // Ingresa aquí la información del comprador.
                    //PayUParameters::[...] => [...],


                    // -- Pagador --
                    // Ingresa aquí la información del pagador.
                    //PayUParameters::[...] => [...],

                    // -- Datos de la tarjeta de crédito --
                    // Ingresa aquí el token de la tarjeta de crédito
                    PayUParameters::TOKEN_ID => $token,
                    //PayUParameters::CREDIT_CARD_EXPIRATION_DATE => $order->card_expiry,
                    // Ingresa aquí el código de seguridad de la tarjeta de crédito
                    PayUParameters::CREDIT_CARD_SECURITY_CODE => '7777', // 7777
                    PayUParameters::PROCESS_WITHOUT_CVV2 => false, //
                    // Ingresa aquí el nombre de la tarjeta de crédito
                    PayUParameters::PAYMENT_METHOD => $card->paymentMethod,

                    // Ingresa aquí el número de cuotas.
                    PayUParameters::INSTALLMENTS_NUMBER => "1",
                    // Ingresa aquí el nombre del país.
                    PayUParameters::COUNTRY => PayUCountries::CO,

                    // Device Session ID
                    PayUParameters::DEVICE_SESSION_ID => md5(session_id().microtime()),
                    // IP del pagador
                    PayUParameters::IP_ADDRESS => pmpro_get_ip(),
                    // Cookie de la sesión actual
                    PayUParameters::PAYER_COOKIE => "cookie_" . time(),
                    // User agent de la sesión actual
                    PayUParameters::USER_AGENT => $_SERVER['HTTP_USER_AGENT']
                );

                // Petición de "Autorización y captura"
                $response = PayUPayments::doAuthorizationAndCapture($params_payment);

                if ($response && $response->transactionResponse->state === "APPROVED") {
                    $subscription = PMPro_Subscription::get_subscription( $subscription->subscription_id );
                    $next_payment_date = date_i18n( 'Y-m-d H:i:s', strtotime( '+ ' . $subscription->get_cycle_number() . ' ' . $subscription->get_cycle_period(), current_time("timestamp") ) );
                    $args = [
                        'next_payment_date' => $next_payment_date
                    ];
                    $subscription->set($args);
                    $subscription->save();
                }
            }catch (Exception $exception){
            }

        }
    }

    public static function pmpro_checkout_after_preheader($order): void
    {
        global $gateway;

        $default_gateway = get_option( "pmpro_gateway" );

        if ( $gateway == "payulatamsubscription" || $default_gateway == "payulatamsubscription" ) {
            wp_enqueue_script( 'pmpro_payu_latam_card', pmpro_payu_latam_subscription()->assets. 'js/card.js', array(), PMPRO_PAYU_LATAM_SUBSCRIPTION_VERSION );
            wp_enqueue_script( 'pmpro_payu_latam', pmpro_payu_latam_subscription()->assets. 'js/pmpro-payu-latam.js', array( 'jquery', 'pmpro_payu_latam_card' ), PMPRO_PAYU_LATAM_SUBSCRIPTION_VERSION, ['in_footer' => true] );
        }

    }

    private function get_api_key() {
        return get_option( 'pmpro_api_key' ) ?: '';
    }

    private function get_api_login() {
        return get_option( 'pmpro_api_login' ) ?: '';
    }

    private function get_merchant_id()
    {
        return get_option( 'pmpro_merchant_id' ) ?: '';
    }

    private static function get_account_id()
    {
        return get_option( 'pmpro_account_id' ) ?: '';
    }

    private function get_is_test(): bool
    {
        return get_option( 'pmpro_gateway_environment' ) === 'sandbox';
    }

    private function get_payment_url($reports = false): string
    {
        if ($this->get_is_test()){
            $url = "https://sandbox.api.payulatam.com/";
        }else{
            $url = "https://api.payulatam.com/";
        }
        if ($reports){
            $url .= 'reports-api/4.0/service.cgi';
        }else{
            $url .= 'payments-api/4.0/service.cgi';
        }
        return $url;
    }

    private function get_card_token(&$order, $user_id): ?string
    {
        $credit_card_token = null;

        try {
            $params_token = array(
                // Ingresa aquí el nombre del pagador.
                PayUParameters::PAYER_NAME => $order->card_name,
                // Ingresa aquí el identificador del pagador.
                PayUParameters::PAYER_ID => $user_id,
                // Ingresa aquí el número de identificación del pagador.
                PayUParameters::PAYER_DNI => $order->card_document,
                // Ingresa aquí el número de la tarjeta de crédito
                PayUParameters::CREDIT_CARD_NUMBER => $order->card_number,
                // Ingresa la fecha de expiración de la tarjeta de crédito
                PayUParameters::CREDIT_CARD_EXPIRATION_DATE => $order->card_expiry,
                // Ingresa aquí el nombre de la tarjeta de crédito
                PayUParameters::PAYMENT_METHOD => $order->card_type
            );

            $response = PayUTokens::create($params_token);

            if($response){
                //Puedes obtener el token de la tarjeta de crédito
                $credit_card_token =  $response->creditCardToken->creditCardTokenId;
                update_user_meta( $user_id, "pmpro_payulatam_token", $credit_card_token );
            }
        } catch (Exception $exception){

        }

        return $credit_card_token;
    }
}