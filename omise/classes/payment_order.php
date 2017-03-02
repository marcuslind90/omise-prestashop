<?php
if (! defined('_PS_VERSION_')) {
    exit();
}

if (defined('_PS_MODULE_DIR_')) {
    require_once _PS_MODULE_DIR_ . 'omise/omise.php';
}

class PaymentOrder
{
    protected $context;
    protected $module;

    public function __construct()
    {
        $this->context = Context::getContext();
        $this->module = Module::getInstanceByName(Omise::MODULE_NAME);
    }

    protected function getCartId()
    {
        return (int) $this->context->cart->id;
    }

    protected function getCartOrderTotal()
    {
        return (float) $this->context->cart->getOrderTotal();
    }

    protected function getCustomerSecureKey()
    {
        $customer = new Customer($this->context->cart->id_customer);

        return $customer->secure_key;
    }

    protected function getCurrencyId()
    {
        return (int) $this->context->currency->id;
    }

    /**
     * The array of extra variables that will be used to attach to the order email.
     *
     * @return array
     */
    protected function getExtraVariables()
    {
        return array();
    }

    protected function getModuleDisplayName()
    {
        return Omise::MODULE_DISPLAY_NAME;
    }

    /**
     * The optional message that will be used to attach to the order.
     *
     * @return string
     */
    protected function getOptionalMessage()
    {
        return null;
    }

    /**
     * The successful order status.
     *
     * @return int
     */
    public function getOrderStateAcceptedPayment()
    {
        return Configuration::get('PS_OS_PAYMENT');
    }

    /**
     * The order status that indicate the order is in progress.
     *
     * @return int
     */
    public function getOrderStateProcessingInProgress()
    {
        return Configuration::get('PS_OS_PREPARATION');
    }

    /**
     * The flag that used to indicate that the PrestaShop need to
     * round the card order total amount.
     *
     * If the flag is false, the PrestaShop will perform rounding.
     * If the flag is true, the PrestaShop WILL NOT preform rounding.
     *
     * @return bool
     */
    protected function isNotNeededRoundingCardOrderTotal()
    {
        return false;
    }

    public function save($order_state = null)
    {
        if (empty($order_state)) {
            $order_state = $this->getOrderStateAcceptedPayment();
        }

        $this->module->validateOrder(
            $this->getCartId(),
            $order_state,
            $this->getCartOrderTotal(),
            $this->getModuleDisplayName(),
            $this->getOptionalMessage(),
            $this->getExtraVariables(),
            $this->getCurrencyId(),
            $this->isNotNeededRoundingCardOrderTotal(),
            $this->getCustomerSecureKey()
        );
    }

    public function saveAsProcessing()
    {
        $this->save($this->getOrderStateProcessingInProgress());
    }

    /**
     * @param \Order $order The instance of class, Order.
     */
    public function updateStateToBeSuccess($order)
    {
        $order_state = $this->getOrderStateAcceptedPayment();

        if ($order->current_state == $order_state) {
            return;
        }

        $order->setCurrentState($order_state);
    }
}
