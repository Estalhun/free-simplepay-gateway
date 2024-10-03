<?php

namespace FSG\SimplePay\Handlers;

use FSG\SimplePay\Payloads\StatusPayload;
use FSG\SimplePay\Support\Config;
use FSG\SimplePay\Support\Log;
use FSG\SimplePay\Support\Request;
use Exception;

class IRNHandler extends Handler
{
    /**
     * Handle the IRN request.
     *
     * @param  array  $payload
     * @return void
     */
    public function handle($payload)
    {
        Log::info(sprintf(
            "%s\n%s",
            __('IRN event was fired.', 'free-simplepay'),
            json_encode($payload)
        ));

        $request = Request::post(
            Config::url('query'),
            StatusPayload::handle($payload['transactionId'])
        );

        try {
            $request->send();

            if ($request->valid()) {
                $amount = (float) $this->order->get_remaining_refund_amount();
                $amount -= (float) $request->body('transactions.0.remainingTotal');

                if ($amount > 0) {
                    wc_create_refund([
                        'amount' => $amount,
                        'order_id' => $this->order->get_id(),
                    ]);
                }
            }
        } catch (Exception $e) {
            //
        }
    }
}
