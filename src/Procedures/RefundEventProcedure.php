<?php

namespace Payever\Procedures;

use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverService;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Log\Loggable;

class RefundEventProcedure
{
    use Loggable;

    /**
     * @param EventProceduresTriggered $eventTriggered
     * @param PayeverService $paymentService
     * @param PaymentRepositoryContract $paymentContract
     * @param PayeverHelper $paymentHelper
     * @throws \Exception
     */
    public function run(
        EventProceduresTriggered $eventTriggered,
        PayeverService $paymentService,
        PaymentRepositoryContract $paymentContract,
        PayeverHelper $paymentHelper
    ) {
        $orderId = false;
        $order = $eventTriggered->getOrder();
        
        $originOrders = $order->originOrders;
        if (!$originOrders->isEmpty() && $originOrders->count() > 0) {
            $originOrder = $originOrders->first();
            $orderId = $originOrder->id;
        }
        
        if (empty($orderId)) {
            throw new \Exception('Refund payever payment failed! The given order is invalid!');
        }
        
        $amount = 0;
        foreach ($order->orderItems as $item) {
            $quantity = $item->quantity;
            $price = $item->amounts->first()->priceGross;
            $amount += ($quantity * $price);
        }
        
        $payments = $paymentContract->getPaymentsByOrderId($orderId);
        foreach ($payments as $payment) {
            if ($paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
                $transactionId = $paymentHelper->getPaymentPropertyValue(
                    $payment,
                    PaymentProperty::TYPE_TRANSACTION_ID
                );
               
                $this->getLogger(__METHOD__)->debug(
                    'Payever::debug.refundData',
                    'TransactionId: ' . $transactionId . ', amount: ' . $amount
                );
                
                if ($transactionId) {
                    $transaction = $paymentService->getTransaction($transactionId);
                    $this->getLogger(__METHOD__)->debug('Payever::debug.transactionData', $transaction);
                    
                    $this->getLogger(__METHOD__)->debug('Payever::debug.refundResponse', $paymentHelper->isAllowedTransaction($transaction, 'cancel'));
                    // partial cancel
                    if ($paymentHelper->isAllowedTransaction($transaction, 'cancel')) {
                        $this->getLogger(__METHOD__)->debug('Payever::debug.refundResponse', 'can');
                    }
                    
                    // partial refund
                    if ($paymentHelper->isAllowedTransaction($transaction, 'refund')) {
                        $this->getLogger(__METHOD__)->debug('Payever::debug.transactionData', 'ref');
                    }
                }
            }
        }
    }
}
