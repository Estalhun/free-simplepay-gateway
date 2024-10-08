<?php

namespace FSG\SimplePay\Handlers;

use FSG\SimplePay\Support\Log;

class IPNHandler extends Handler
{
    /**
     * Handle the IPN request.
     *
     * @param  array  $payload
     * @return void
     */
    public function handle($payload)
    {
        Log::info(sprintf(
            "%s\n%s",
            __('IPN event was fired.', 'free-simplepay'),
            json_encode($payload)
        ));

        switch ($payload['status']) {
            case 'FINISHED';
                $this->handleFinished();
                break;
            case 'NOTAUTHORIZED';
                $this->handleNotAuthorized();
                break;
            case 'CANCELLED';
                $this->handleCancelled();
                break;
            case 'TIMEOUT';
                $this->handleTimeout();
                break;
            default:
                Log::error(sprintf(
                    '%s %s',
                    __('Unknown IPN status:', 'free-simplepay'),
                    $payload['status']
                ));
                break;
        };
    }

    /**
     * Handle the FINISHED event.
     *
     * @return void
     */
    protected function handleFinished()
    {
        $this->order->payment_complete();

        if ($this->order->get_meta('_free_simplepay_two_step_payment_reserved') && ! $this->order->get_meta('_free_simplepay_two_step_payment_finished')) {
            $this->order->update_meta_data('_free_simplepay_two_step_payment_finished', date('c'));

            $this->order->save();
        }

        $virtual = true;

        foreach ($this->order->get_items(['line_item']) as $item) {
            if (! $virtual = $item->get_product()->is_virtual()) {
                break;
            }
        }

        if ($virtual) {
            $this->order->update_status('completed');
        }
    }

    /**
     * Handle the NOTAUTHORIZED event.
     *
     * @return void
     */
    protected function handleNotAuthorized()
    {
        $this->order->update_status('failed');
    }

    /**
     * Handle the CANCELLED event.
     *
     * @return void
     */
    protected function handleCancelled()
    {
        $this->order->update_status('pending');
    }

    /**
     * Handle the TIMEOUT event.
     *
     * @return void
     */
    protected function handleTimeout()
    {
        $this->order->update_status('cancelled');
    }
}
