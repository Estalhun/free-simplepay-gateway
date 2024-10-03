<?php

namespace FSG\SimplePay\Payloads;

use FSG\SimplePay\Plugin;
use FSG\SimplePay\Support\Config;
use FSG\SimplePay\Support\Hash;
use FSG\SimplePay\Support\Str;
use WC_Order;

abstract class RefundPayload
{
    /**
     * Handle the data.
     *
     * @param  \WC_Order  $order
     * @param  int|float  $amount
     * @return string
     */
    public static function handle(WC_Order $order, $amount)
    {
        return json_encode(static::serialize($order, $amount));
    }

    /**
     * Serialize the data.
     *
     * @param  \WC_Order  $order
     * @param  int|float  $amount
     * @return array
     */
    protected static function serialize(WC_Order $order, $amount)
    {
        return [
            'salt' => Hash::salt(),
            'refundTotal' => $amount,
            'merchant' => Config::get('merchant'),
            'currency' => $order->get_order_currency(),
            'orderRef' => Str::refFromId($order->get_order_number()),
            'sdkVersion' => 'Pine SimplePay Gateway:'.Plugin::VERSION,
        ];
    }
}
