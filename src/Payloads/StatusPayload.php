<?php

namespace FSG\SimplePay\Payloads;

use FSG\SimplePay\Plugin;
use FSG\SimplePay\Support\Config;
use FSG\SimplePay\Support\Hash;

abstract class StatusPayload
{
    /**
     * Handle the data.
     *
     * @param  string|array  $ids
     * @return string
     */
    public static function handle($ids)
    {
        return json_encode(static::serialize($ids));
    }

    /**
     * Serialize the data.
     *
     * @param  string|array  $ids
     * @return array
     */
    protected static function serialize($ids)
    {
        return [
            'salt' => Hash::salt(),
            'transactionIds' => (array) $ids,
            'merchant' => Config::get('merchant'),
            'sdkVersion' => 'Pine SimplePay Gateway:'.Plugin::VERSION,
        ];
    }
}
