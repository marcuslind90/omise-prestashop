<?php
use \Mockery as m;

class PaymentOrderTest extends Mockery\Adapter\Phpunit\MockeryTestCase
{
    private $cart_id = 1234;
    private $cart_order_total = 100.25;
    private $currency_id = 12;
    private $customer_id = 1;
    private $customer_secure_key = 'customerSecureKey';
    private $extra_variables = array();
    private $is_not_needed_rounding_card_order_total = false;
    private $module;
    private $module_display_name = 'Omise';
    private $module_id = '2';
    private $module_current_order = '3';
    private $optional_message = null;
    private $order;
    private $order_state_accepted_payment = 'orderStatusPayment';
    private $order_state_canceled = 'orderStatusCanceled';
    private $order_state_processing_in_progress = 'orderStatusProcessingInProgress';
    private $payment_order;

    public function setup()
    {
        $unit_test_helper = new UnitTestHelper();

        $unit_test_helper->getMockedPaymentModule();

        $cart = $this->getMockBuilder(get_class(new stdClass()))
            ->setMethods(
                array(
                    'getOrderTotal',
                )
            )
            ->getMock();
        $cart->method('getOrderTotal')
            ->willReturn($this->cart_order_total);
        $cart->id = $this->cart_id;
        $cart->id_customer = $this->customer_id;

        $currency = $this->getMockBuilder(get_class(new stdClass()));
        $currency->id = $this->currency_id;

        $context = $this->getMockBuilder(get_class(new stdClass()));
        $context->cart = $cart;
        $context->currency = $currency;

        m::mock('alias:\Context')
            ->shouldReceive('getContext')
            ->andReturn($context);

        m::mock('alias:\Configuration')
            ->shouldReceive('get')
            ->with('PS_OS_CANCELED')
            ->andReturn($this->order_state_canceled)
            ->shouldReceive('get')
            ->with('PS_OS_PAYMENT')
            ->andReturn($this->order_state_accepted_payment)
            ->shouldReceive('get')
            ->with('PS_OS_PREPARATION')
            ->andReturn($this->order_state_processing_in_progress);

        $this->module = $this->getMockBuilder(get_class(new stdClass()))
            ->setMethods(
                array(
                    'validateOrder',
                )
            )
            ->getMock();
        $this->module->currentOrder = $this->module_current_order;
        $this->module->displayName = $this->module_display_name;
        $this->module->id = $this->module_id;

        m::mock('alias:\Module')
            ->shouldReceive('getInstanceByName')
            ->andReturn($this->module);

        $this->order = $this->createMockedOrder();
        $this->payment_order = new PaymentOrder();
    }

    public function testGetOrderStateAcceptedPayment_getOrderState_orderStateAcceptedPayment()
    {
        m::mock('alias:\Configuration')
            ->shouldReceive('get')
            ->with('PS_OS_PAYMENT')
            ->andReturn($this->order_state_accepted_payment);

        $order_state = $this->payment_order->getOrderStateAcceptedPayment();

        $this->assertEquals($this->order_state_accepted_payment, $order_state);
    }

    public function testGetOrderStateCanceled_getOrderState_orderStateCanceled()
    {
        m::mock('alias:\Configuration')
            ->shouldReceive('get')
            ->with('PS_OS_CANCELED')
            ->andReturn($this->order_state_canceled);

        $order_state = $this->payment_order->getOrderStateCanceled();

        $this->assertEquals($this->order_state_canceled, $order_state);
    }

    public function testGetOrderStateProcessingInProgress_getOrderState_orderStateProcessingInProgress()
    {
        m::mock('alias:\Configuration')
            ->shouldReceive('get')
            ->with('PS_OS_PREPARATION')
            ->andReturn($this->order_state_processing_in_progress);

        $order_state = $this->payment_order->getOrderStateProcessingInProgress();

        $this->assertEquals($this->order_state_processing_in_progress, $order_state);
    }

    public function testSave_theParameterOmiseChargeIdIsEmpty_noAnyOmiseChargeIdSaveToDatabaseForReference()
    {
        $id_charge = '';

        $this->module->expects($this->once())
            ->method('validateOrder')
            ->with(
                $this->cart_id,
                'idOrderState',
                $this->cart_order_total,
                'paymentMethod',
                $this->optional_message,
                $this->extra_variables,
                $this->currency_id,
                $this->is_not_needed_rounding_card_order_total,
                $this->customer_secure_key
            );

        $this->payment_order->save('idOrderState', 'paymentMethod', $id_charge);
    }

    public function testSave_theParameterOmiseChargeIdIsNull_noAnyOmiseChargeIdIsSaveToDatabaseForReference()
    {
        $id_charge = null;

        $this->module->expects($this->once())
            ->method('validateOrder')
            ->with(
                $this->cart_id,
                'idOrderState',
                $this->cart_order_total,
                'paymentMethod',
                $this->optional_message,
                $this->extra_variables,
                $this->currency_id,
                $this->is_not_needed_rounding_card_order_total,
                $this->customer_secure_key
            );

        $this->payment_order->save('idOrderState', 'paymentMethod', $id_charge);
    }

    public function testSave_theParameterOmiseChargeIdIsNotEmpty_saveAnOmiseChargeIdToDatabaseForReference()
    {
        $id_charge = 'id_charge';

        $this->module->expects($this->once())
            ->method('validateOrder')
            ->with(
                $this->cart_id,
                'idOrderState',
                $this->cart_order_total,
                'paymentMethod',
                $this->optional_message,
                array('transaction_id' => $id_charge),
                $this->currency_id,
                $this->is_not_needed_rounding_card_order_total,
                $this->customer_secure_key
            );

        $this->payment_order->save('idOrderState', 'paymentMethod', $id_charge);
    }

    public function testUpdateStateToBeCanceled_currentOrderStateIsCanceled_orderStateMustNotBeUpdated()
    {
        $this->order->current_state = $this->payment_order->getOrderStateCanceled();

        $this->order->expects($this->never())
            ->method('setCurrentState')
            ->with($this->payment_order->getOrderStateCanceled());

        $this->payment_order->updateStateToBeCanceled($this->order);
    }

    public function testUpdateStateToBeCanceled_currentOrderStateIsNotCanceled_orderStateMustBeUpdated()
    {
        $this->order->current_state = $this->payment_order->getOrderStateProcessingInProgress();

        $this->order->expects($this->once())
            ->method('setCurrentState')
            ->with($this->payment_order->getOrderStateCanceled());

        $this->payment_order->updateStateToBeCanceled($this->order);
    }

    public function testUpdateStateToBeSuccess_currentOrderStateIsAcceptedPayment_orderStateMustNotBeUpdated()
    {
        $this->order->current_state = $this->payment_order->getOrderStateAcceptedPayment();

        $this->order->expects($this->never())
            ->method('setCurrentState')
            ->with($this->payment_order->getOrderStateAcceptedPayment());

        $this->payment_order->updateStateToBeSuccess($this->order);
    }

    public function testUpdateStateToBeSuccess_currentOrderStateIsNotAcceptedPayment_orderStateMustBeUpdated()
    {
        $this->order->current_state = $this->payment_order->getOrderStateProcessingInProgress();

        $this->order->expects($this->once())
            ->method('setCurrentState')
            ->with($this->payment_order->getOrderStateAcceptedPayment());

        $this->payment_order->updateStateToBeSuccess($this->order);
    }

    private function createMockedOrder()
    {
        $order = $this->getMockBuilder(get_class(new stdClass()))
            ->setMethods(
                array(
                    'setCurrentState',
                )
            )
            ->getMock();

        return $order;
    }
}

if (! class_exists('Customer')) {
    class Customer
    {
        public $secure_key = 'customerSecureKey';

        public function __construct($customerId)
        {
        }
    }
}
