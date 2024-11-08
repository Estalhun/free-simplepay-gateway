<?php

namespace FSG\SimplePay;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use FSG\SimplePay\Handlers\IPNHandler;
use FSG\SimplePay\Handlers\IRNHandler;
use FSG\SimplePay\Handlers\PaymentHandler;
use FSG\SimplePay\Payloads\PaymentPayload;
use FSG\SimplePay\Payloads\RefundPayload;
use FSG\SimplePay\Support\Config;
use FSG\SimplePay\Support\Hash;
use FSG\SimplePay\Support\Log;
use FSG\SimplePay\Support\Request;
use FSG\SimplePay\Support\Str;
use Exception;
use WC_Order;
use WC_Payment_Gateway;

class Gateway extends WC_Payment_Gateway
{
    // Az összes lehetséges konfigurációs beállítás deklarálása
    protected $sandbox;
    protected $debug;
    protected $two_step;
    protected $merchant;
    protected $secret_key;
    protected $show_icon;
            
    // Pénznem-specifikus beállítások
    protected $huf_merchant;
    protected $huf_secret_key;
    protected $huf_sandbox_merchant;
    protected $huf_sandbox_secret_key;
    protected $eur_merchant;
    protected $eur_secret_key;
    protected $eur_sandbox_merchant;
    protected $eur_sandbox_secret_key;
    protected $usd_merchant;
    protected $usd_secret_key;
    protected $usd_sandbox_merchant;
    protected $usd_sandbox_secret_key;
    
    // A Config osztályban látható további beállítások
    protected $notification_settings;
    protected $sandbox_settings;
    protected $sanbox_settings;
    protected $two_step_settings;
    protected $prefix;
    protected $icon_settings;
    protected $huf_settings;
    protected $eur_settings;
    protected $usd_settings;
    protected $debug_settings;

    /**
     * The ID.
     *
     * @var string
     */
    public $id = 'simplepay-gateway';

    /**
     * The title.
     *
     * @var string
     */
    public $method_title = 'SimplePay';

    /**
     * The description.
     *
     * @var string
     */
    public $method_description = 'Free OTP SimplePay Payment Gateway';

    /**
     * The supported features.
     *
     * @var array
     */
    public $supports = [
        'refunds', 'products',
    ];

    /**
     * The supported currencies.
     *
     * @var array
     */
    protected $currencies = [
        'HUF', 'USD', 'EUR',
    ];

    /**
     * Create a new gateway instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->init_settings();
        $this->init_form_fields();
        $this->setOptions();
        $this->checkCurrency();
    }

    /**
     * Register the gateway.
     *
     * @param  array  $gateways
     * @return array
     */
    public function register($gateways)
    {
        $gateways[] = __CLASS__;

        return $gateways;
    }

    /**
     * Check if the currency is supported by the gateway.
     *
     * @return void
     */
    public function checkCurrency()
    {
        if (! in_array(get_woocommerce_currency(), $this->currencies)) {
            $this->enabled = 'no';
        }
    }

    /**
     * Initialize the form fields.
     *
     * @return void
     */
    public function init_form_fields()
    {
        $this->form_fields = (include __DIR__.'/../includes/fields.php');
    }

    /**
     * Set the options as gateway properties.
     *
     * @return void
     */
    protected function setOptions()
    {
        
        $options = Config::get();
        
        // Biztonságos tulajdonság beállítás ellenőrzéssel
        foreach ($options as $key => $option) {
            if (property_exists($this, $key)) {
                $this->{$key} = $option;
            } else {
                // Naplózzuk az ismeretlen kulcsokat debug célból
                if (Config::isDebug()) {
                    error_log("Unknown config key in Gateway: {$key}");
                }
            }
        }

        $this->method_description = __('Free OTP SimplePay Payment Gateway', 'free-simplepay');

        if (isset($this->show_icon) && $this->show_icon === 'yes') {
            $this->icon = apply_filters('cone_simplepay_icon', plugin_dir_url(__DIR__).'images/icon.svg');
        }
    }

     /**
     * Get the order.
     *
     * @param  int|string  $orderId
     * @return \WC_Order|false
     */
    public function getOrder($orderId)
    {
        $order = wc_get_order(Str::idFromRef($orderId));

        if (! $order instanceof WC_Order) {
            $order = wc_get_order(wc_get_order_id_by_order_key($orderId));
        }

        if (! $order instanceof WC_Order) {
            
            $order = false;
       }

       return $order;
    }

    /**
     * Process the payment.
     *
     * @param  int|string  $orderId
     * @return array|void
     */
    public function process_payment($orderId)
    {
        $order = $this->getOrder($orderId);

        Config::setByCurrency($order->get_currency());

        $request = Request::post(
            Config::url('start'),
            $payload = PaymentPayload::handle($order)
        );

        try {
            $request->send();

            if (! $request->valid()) {
                Log::info(sprintf(__('Request is invalid: %s', 'free-simplepay'), $request->response('body')));

                return [
                    'result' => 'failure',
                    'redirect' => $request->body('paymentUrl'),
                ];
            }

            return [
                'result' => 'success',
                'redirect' => $request->body('paymentUrl'),
            ];
        } catch (Exception $e) {
            Log::info(sprintf('%s: %s', $e->getMessage(), $payload));

            wc_add_notice($e->getMessage(), 'error');
        }
    }

    /**
     * Handle the payment.
     *
     * @return void
     */
    public function handlePayment()
    {
        $payload = json_decode(base64_decode($_GET['r']), true);

        $order = $this->getOrder($payload['o']);

        if (! $order instanceof WC_Order) {
            wp_safe_redirect(wc_get_checkout_url());
            die();
        }

        Config::setByCurrency($order->get_currency());

        (new PaymentHandler($order))->handle($payload);
    }

    /**
     * Handle the IPN / IRN call.
     *
     * @return void
     */
    public function handleNotification()
    {
        $input = file_get_contents('php://input');

        $payload = json_decode($input, true);

        $order = $this->getOrder($payload['orderRef']);

        if (! $order instanceof WC_Order) {
            die(__('Order not found.', 'free-simplepay'));
        }

        Config::setByCurrency($order->get_currency());

        if (! Hash::check($_SERVER['HTTP_SIGNATURE'], $input)) {
            die(__('Invalid signature.', 'free-simplepay'));
        }

        if ((isset($payload['refundStatus']) && $payload['status'] === 'FINISHED') || $payload['status'] === 'REFUND') {
            (new IRNHandler($order))->handle($payload);
        } else {
            (new IPNHandler($order))->handle($payload);
        }

        $payload['receiveDate'] = date('c');

        header('Content-type: application/json');
        header('Signature: '.Hash::make($response = json_encode($payload)));
        die($response);
    }

    /**
     * Process the refund.
     *
     * @param  int|string  $orderId
     * @param  float|null  $amount
     * @param  string|null  $reason
     * @return bool
     */
    public function process_refund($orderId, $amount = null, $reason = '')
    {
        $order = $this->getOrder($orderId);

        if ($order && $order->get_transaction_id()) {
            Config::setByCurrency($order->get_currency());

            $request = Request::post(
                Config::url('refund'),
                RefundPayload::handle($order, $amount)
            );

            try {
                $request->send();

                return $request->valid();
            } catch (Exception $e) {
                return false;
            }
        }

        return false;
    }

    /**
     * Get the transaction URL.
     *
     * @param  \WC_Order  $order
     * @return string
     */
    public function get_transaction_url($order)
    {
        if (Config::isSandbox()) {
            $this->view_transaction_url = 'https://sandbox.simplepay.hu/admin/transactions/data/%s';
        } else {
            $this->view_transaction_url = 'https://secure.simplepay.hu/admin/transactions/data/%s';
        }

        return parent::get_transaction_url($order);
    }

    /**
     * Extend the order table with the transaction ID.
     *
     * @param  \WC_Order  $order
     * @return void
     */
    public function extendOrderTable($order)
    {
        include __DIR__.'/../includes/order-item-row.php';
    }

    /**
     * Add a link to the icon.
     *
     * @param  string  $icon
     * @param  string  $id
     * @return string
     */
    public function addIconLink($icon, $id)
    {
        if ($id !== $this->id) {
            return $icon;
        }

        return sprintf(
            '<a href="%s" target="_blank">%s</a>',
            get_locale() === 'hu_HU'
                ? 'https://simplepartner.hu/PaymentService/Fizetesi_tajekoztato.pdf'
                : 'https://simplepartner.hu/PaymentService/Payment_information.pdf',
            $icon
        );
    }

    /**
     * Register the admin scripts.
     *
     * @param  string  $hook
     * @return void
     */
    public function scripts($hook)
    {
        if ($hook === 'woocommerce_page_wc-settings' && (isset($_GET['section']) && $_GET['section'] === 'simplepay-gateway')) {
            wp_enqueue_script('simplepay', plugin_dir_url(__DIR__).'includes/simplepay.js');
        }
    }

    /**
     * Boot the gateway.
     *
     * @return void
     */
    public static function boot()
    {
        (new static())->registerHooks();
    }

    /**
     * Init checkout block compatibility.
     *
     * @return void
     */
    public static function initBlock()
    {
        if (class_exists(AbstractPaymentMethodType::class)) {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                function (PaymentMethodRegistry $registry) {
                    $registry->register(new GatewayBlock());
                }
            );
        }
    }

    /**
     * Register the hooks.
     *
     * @return void
     */
    public function registerHooks()
    {
        add_action('admin_enqueue_scripts', [$this, 'scripts']);
        add_filter('woocommerce_payment_gateways', [$this, 'register']);
        add_filter('woocommerce_gateway_icon', [$this, 'addIconLink'], 10, 2);
        add_action('woocommerce_api_process_simplepay_payment', [$this, 'handlePayment']);
        add_action("woocommerce_api_wc_gateway_{$this->id}", [$this, 'handleNotification']);
        add_action('woocommerce_order_details_after_order_table_items', [$this, 'extendOrderTable']);
        add_action("woocommerce_update_options_payment_gateways_{$this->id}", [$this, 'process_admin_options']);
        add_action('woocommerce_blocks_loaded', [$this, 'initBlock']);
    }
}
