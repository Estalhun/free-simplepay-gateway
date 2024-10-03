<?php

namespace FSG\SimplePay;

use FSG\SimplePay\Payloads\FinishPayload;
use FSG\SimplePay\Support\Config;
use FSG\SimplePay\Support\Log;
use FSG\SimplePay\Support\Request;
use Exception;
use WC_Order;

class TwoStepPayment
{
    /**
     * The action key.
     *
     * @var string
     */
    protected const ACTION = 'free_simplepay_two_step_finish';

    /**
     * Register the hooks.
     *
     * @return void
     */
    public static function boot()
    {
        add_filter('woocommerce_order_actions', [static::class, 'register'], 10, 2);
        add_action('woocommerce_order_action_'.static::ACTION, [static::class, 'handle']);
    }

    /**
     * Register the action.
     *
     * @param  array  $actions
     * @param  \WC_Order  $order
     * @return array
     */
    public static function register($actions, $order)
    {
        if ($order->get_meta('_free_simplepay_two_step_payment_reserved') && ! $order->get_meta('_free_simplepay_two_step_payment_finished')) {
            $actions[static::ACTION] = __('Finish the two step SimplePay payment', 'free-simplepay');
        }

        return $actions;
    }

    /**
     * Perform the acion.
     *
     * @return void
     */
    public static function handle(WC_Order $order)
    {
        Config::setByCurrency($order->get_currency());

        $request = Request::post(
            Config::url('finish'),
            $payload = FinishPayload::handle($order)
        );

        try {
            $request->send();

            if (! $request->valid()) {
                throw new Exception(__('Request is invalid', 'free-simplepay'));
            }

            if ($order->get_meta('_free_simplepay_two_step_payment_reserved') && ! $order->get_meta('_free_simplepay_two_step_payment_finished')) {
                $order->update_meta_data('_free_simplepay_two_step_payment_finished', date('c'));
            }

            $order->add_order_note(
                __('Two step SimplePay payment has been finished.', 'free-simplepay')
            );
        } catch (Exception $e) {
            Log::info(sprintf('%s: %s', $e->getMessage(), $payload));

            $order->add_order_note(
                __('Two step SimplePay payment request has been failed.', 'free-simplepay')
            );
        }
    }
}
