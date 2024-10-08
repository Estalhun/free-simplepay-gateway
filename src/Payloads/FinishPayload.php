<?php

namespace FSG\SimplePay\Payloads;

use FSG\SimplePay\Plugin;
use FSG\SimplePay\Support\Config;
use FSG\SimplePay\Support\Hash;
use FSG\SimplePay\Support\Str;
use WC_Order;

abstract class FinishPayload
{
    /**
     * Handle the data.
     *
     * @param  \WC_Order  $order
     * @return string
     */
    public static function handle(WC_Order $order)
    {
        return json_encode(static::serialize($order));
    }

    /**
     * Serialize the data.
     *
     * @param  \WC_Order  $order
     * @return array
     */
    protected static function serialize(WC_Order $order)
    {
        return [
            'salt' => Hash::salt(),
            'merchant' => Config::get('merchant'),
            'orderRef' => Str::refFromId($order->get_order_number()),
            'currency' => $order->get_currency(),
            'sdkVersion' => 'Pine SimplePay Gateway:'.Plugin::VERSION,
            'originalTotal' => $reserved = $order->get_meta('_free_simplepay_two_step_payment_reserved'),
            'approveTotal' => min($reserved, $order->get_total()),
        ];
    }
}
