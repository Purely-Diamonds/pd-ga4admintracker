<?php
namespace PurelyDiamonds\AdminOrder2GA4\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Model\Order;


class OrderSaveAfter implements ObserverInterface
{
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Send ecommerce conversion to Google Analytics 4 when order is saved
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer)
    {

        // Get the order object
        $order = $observer->getEvent()->getOrder();        

        // Check if the order was placed in the admin area
        $actionFlag = $order->getActionFlag(\Magento\Sales\Model\Order::ACTION_FLAG_EDIT);
        if ($actionFlag === true) {        

            // Check if the extension is enabled
            if (!$this->scopeConfig->getValue('adminorder2ga4/general/enable_conversion_tracking')) {
                return;
            }      

            // Get the GA4 tracking ID from the Magento 2 configuration
            $ga4TrackingId = $this->scopeConfig->getValue('adminorder2ga4/general/enable_conversion_tracking');
            
            // Check if the GA4 tracking ID is set
            if (!$ga4TrackingId) {
                return;
            }
            
            // Build the GA4 ecommerce conversion payload
            $payload = [
                'client_id' => $order->getCustomerId() ?: 'guest',
                'currency' => $order->getOrderCurrencyCode(),
                'items' => [],
                'transaction_id' => $order->getIncrementId(),
                'value' => $order->getGrandTotal(),
            ];

            // Add each item in the order to the GA4 ecommerce conversion payload
            foreach ($order->getAllVisibleItems() as $item) {
                $payload['items'][] = [
                    'item_id' => $item->getProductId(),
                    'item_name' => $item->getName(),
                    'item_category' => $item->getProductType(),
                    'item_quantity' => $item->getQtyOrdered(),
                    'item_price' => $item->getBasePrice(),
                ];
            }

            // Send the GA4 ecommerce conversion to Google Analytics
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.google-analytics.com/g/collect");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'measurement_id' => $ga4TrackingId,
                'api_secret' => '',
                'data' => [
                    'ecommerce' => [
                        'purchase' => $payload,
                    ],
                ],
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);

        }
            
    }
}
