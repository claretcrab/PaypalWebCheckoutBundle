<?php

/**
 * This file is part of the PaymentSuite package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * Feel free to edit as you please, and have fun.
 *
 * @author Marc Morera <yuhu@mmoreram.com>
 */

namespace PaymentSuite\PaypalWebCheckoutBundle\Services\Wrapper;

use Symfony\Component\Form\FormFactory;
use Symfony\Component\Routing\RouterInterface;

use PaymentSuite\PaymentCoreBundle\Services\interfaces\PaymentBridgeInterface;
use PaymentSuite\PaypalWebCheckoutBundle\Exception\CurrencyNotSupportedException;
use PaymentSuite\PaypalWebCheckoutBundle\Services\UrlFactory;

/**
 * Class PaypalFormTypeWrapper
 *
 * @author Arkaitz Garro <hola@arkaitzgarro.com>
 * @author Mickaël Andrieu <andrieu.travail@gmail.com>
 */
class PaypalFormTypeWrapper
{
    /**
     * @var FormFactory
     *
     * Form factory
     */
    protected $formFactory;

    /**
     * @var PaymentBridgeInterface
     *
     * Payment bridge
     */
    private $paymentBridge;

    /**
     * @var RouterInterface
     *
     * Router
     */
    private $router;

    /**
     * @var string $business
     *
     * Merchant identifier
     */
    private $business;

    /**
     * @var string $paypalUrl
     *
     * Paypal web url
     */
    private $paypalUrl;

    /**
     * @var string $returnUrl
     *
     * Route for success payment
     */
    private $returnUrl;

    /**
     * @var string $cancelReturnUrl
     *
     * Route for fail payment
     */
    private $cancelReturnUrl;

    /**
     * @var string $notifyUrl
     *
     * Route for process payment
     */
    private $notifyUrl;

    /**
     * @var boolean $debug
     *
     * Debug enviroment
     */
    private $debug;

    /**
     * @var string $env
     *
     * Environment
     */
    private $env;

    /**
     * Formtype construct method
     *
     * @param FormFactory            $formFactory   Form factory
     * @param PaymentBridgeInterface $paymentBridge Payment bridge
     * @param RouterInterface        $router        Routing service
     * @param string                 $business      merchant code
     * @param UrlFactory             $urlFactory    URL Factory service
     */
    public function __construct(
        FormFactory $formFactory,
        PaymentBridgeInterface $paymentBridge,
        RouterInterface $router,
        $business,
        URLFactory $urlFactory
    ) {
        $this->formFactory           = $formFactory;
        $this->paymentBridge         = $paymentBridge;
        $this->router                = $router;
        $this->business              = $business;
        $this->urlFactory            = $urlFactory;
    }

    /**
     * Builds form given return, success and fail urls
     *
     * @return \Symfony\Component\Form\FormView
     */
    public function buildForm()
    {
        $formBuilder = $this
            ->formFactory
            ->createNamedBuilder(null);

        $orderId = $this
            ->paymentBridge
            ->getOrderId();

        /*
         * PaymentBridge stores payment amount in cents
         */
        $amount = $this
                ->paymentBridge
                ->getAmount() / 100;

        $currency = $this
            ->checkCurrency(
                $this
                    ->paymentBridge
                    ->getCurrency()
            );

        /*
         * Creates the return route, when coming back
         * from PayPal web checkout
         */
        $returnUrl = $this
            ->urlFactory
            ->getReturnUrlForOrderId($orderId);

        /*
         * Creates the cancel payment route, when cancelling
         * the payment process from PayPal web checkout
         */
        $cancelUrl = $this
            ->urlFactory
            ->getCancelReturnUrlForOrderId($orderId);

        /*
         * Creates the IPN payment notification route,
         * which is triggered after PayPal processes the
         * payment and returns the validity of the transaction
         *
         * For forther information
         *
         * https://developer.paypal.com/docs/classic/ipn/integration-guide/IPNandPDTVariables/
         * https://developer.paypal.com/webapps/developer/docs/classic/ipn/integration-guide/IPNIntro/
         */
        $processUrl = $this->urlFactory->getProcessUrlForOrderId($orderId);

        /*
         * Imploding the list of product names in the order to a single string.
         *
         * Project specific PaymentBridgeInterface::getExtraData
         * should return an array of this form
         *
         *   ['items' => [
         *       1 => [ 'item' => 'Item 1', 'amount' => 1234, 'currency_code' => 'EUR ],
         *       2 => [ 'item_name' => 'Item 2', 'item_amount' => 2345, 'item_currency_code' => 'EUR ],
         *   ]]
         *
         * The 'items' key consists of an array with the basic information
         * of each line of the order
         *
         */
        $productName = array_reduce(
            $this->paymentBridge->getExtraData()['items'],

            function ($productName, $orderLine) {
                return trim(
                    sprintf(
                        '%s %s',
                        $productName,
                        $orderLine['item_name'])
                );
            }
        );

        $formBuilder
            ->setAction($this->urlFactory->getPaypalBaseUrl())
            ->setMethod('POST')
            ->add('item_name', 'hidden', array(
                'data' => $productName
            ))
            ->add('amount', 'hidden', array(
                'data' => $amount,
            ))
            ->add('business', 'hidden', array(
                'data' => $this->business,
            ))
            ->add('return', 'hidden', array(
                'data' => $returnUrl,
            ))
            ->add('cancel_return', 'hidden', array(
                'data' => $cancelUrl,
            ))
            ->add('notify_url', 'hidden', array(
                'data' => $processUrl,
            ))
            ->add('item_number', 'hidden', array(
                'data' => $orderId,
            ))
            ->add('currency_code', 'hidden', array(
                'data' => $currency,
            ))
            ->add('env', 'hidden', array(
                'data' => $this->env,
            ))
        ;

        return $formBuilder->getForm()->createView();
    }

    public function checkCurrency($currency)
    {
        $allowedCurrencies = [
            'AUD',
            'BRL',
            'CAD',
            'CZK',
            'DKK',
            'EUR',
            'HKD',
            'HUF',
            'ILS',
            'JPY',
            'MYR',
            'MXN',
            'NOK',
            'NZD',
            'PHP',
            'PLN',
            'GBP',
            'RUB',
            'SGD',
            'SEK',
            'CHF',
            'TWD',
            'THB',
            'TRY',
            'USD'
        ];

        if (!in_array($currency, $allowedCurrencies)) {
            throw new CurrencyNotSupportedException();
        }

        return $currency;
    }
}
