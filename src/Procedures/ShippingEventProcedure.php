<?php

namespace Payever\Procedures;

use Payever\Helper\PayeverHelper;
use Payever\Services\PayeverService;
use Plenty\Modules\EventProcedures\Events\EventProceduresTriggered;
use Plenty\Modules\Order\Shipping\Package\Contracts\OrderShippingPackageRepositoryContract;
use Plenty\Modules\Payment\Contracts\PaymentRepositoryContract;
use Plenty\Modules\Payment\Models\Payment;
use Plenty\Modules\Payment\Models\PaymentProperty;
use Plenty\Plugin\Log\Loggable;

class ShippingEventProcedure
{
    use Loggable;

    /**
     * @var OrderShippingPackageRepositoryContract $orderShippingPackage
     */
    private $orderShippingPackage;
    
    /**
     * ShipmentController constructor.
     *
     * @param OrderShippingPackageRepositoryContract $orderShippingPackage
     */
    public function __construct(OrderShippingPackageRepositoryContract $orderShippingPackage)
    {
        $this->orderShippingPackage = $orderShippingPackage;
    }
    
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
        $orderId = $paymentHelper->getOrderIdByEvent($eventTriggered);
        
        $packages = $this->orderShippingPackage->listOrderShippingPackages($orderId);

        $order = $eventTriggered->getOrder();
        $this->getLogger(__METHOD__)->debug('Payever::debug.transactionData', $packages);
        foreach ($order->orderItems as $item) {
            //$quantity = $item->quantity;
            //$price = $item->amounts->first()->priceGross;
            //$amount += ($quantity * $price);
        }


        if (empty($orderId)) {
            throw new \Exception('Shipping goods payever payment action is failed! The given order is invalid!');
        }
        /** @var Payment[] $payment */
        $payments = $paymentContract->getPaymentsByOrderId($orderId);
        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if ($paymentHelper->isPayeverPaymentMopId($payment->mopId)) {
                $transactionId = $paymentHelper->getPaymentPropertyValue(
                    $payment,
                    PaymentProperty::TYPE_TRANSACTION_ID
                );
                $this->getLogger(__METHOD__)->debug(
                    'Payever::debug.shippingData',
                    'TransactionId: ' . $transactionId
                );
                if (!empty($transactionId)) {
                    $transaction = $paymentService->getTransaction($transactionId);
                    $this->getLogger(__METHOD__)->debug('Payever::debug.transactionData', $transaction);
                    if ($paymentHelper->isAllowedTransaction($transaction, 'shipping_goods')) {
                        // shipping the payment
                        $shippingResult = $paymentService->shippingGoodsPayment($transactionId);
                        $this->getLogger(__METHOD__)->debug('Payever::debug.shippingResponse', $shippingResult);
                    } else {
                        $this->getLogger(__METHOD__)->debug(
                            'Payever::debug.shippingResponse',
                            'Shipping goods payever payment action is not allowed!'
                        );
                        throw new \Exception('Shipping goods payever payment action is not allowed!');
                    }
                }
            }
        }
    }
}
