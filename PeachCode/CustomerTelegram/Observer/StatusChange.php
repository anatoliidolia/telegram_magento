<?php

namespace PeachCode\CustomerTelegram\Observer;

use Exception;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;
use Magento\Sales\Model\OrderRepository;
use Psr\Log\LoggerInterface;

/**
 * Observer to send order status to customer
 */
class StatusChange implements ObserverInterface
{
    public const IS_ENABLE = 'telegram_customer_links/general/telegram_integration';

    public const TELEGRAM_API_TOKEN = 'telegram_customer_links/general/telegram_api_token';

    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var OrderRepository
     */
    private $orderRepository;
    /**
     * @var CustomerRepositoryInterface
     */
    private $customerRepository;

    /**
     * @param OrderRepository $orderRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     * @param CustomerRepositoryInterface $customerRepository
     */
    public function __construct(
        OrderRepository $orderRepository,
        ScopeConfigInterface        $scopeConfig,
        LoggerInterface             $logger,
        CustomerRepositoryInterface $customerRepository
    )
    {
        $this->logger = $logger;
        $this->orderRepository = $orderRepository;
        $this->scopeConfig = $scopeConfig;
        $this->customerRepository = $customerRepository;
    }

    /**
     * @param Observer $observer
     */
    public function execute(Observer $observer)
    {
        if($this->scopeConfig->getValue(self::IS_ENABLE)) {

            $order = $observer->getEvent()->getOrder();
            //TODO: broken admin notifications
            if($order->getState() !== 'new' ) {
                try {
                    $orderId = $order->getId();
                    $order = $this->orderRepository->get($orderId);
                } catch (Exception $e) {
                    return;
                }

                $customerId = $order->getCustomerId();
                //check if customer exist, get customer ID
                if ($customerId) {
                    try {
                        $customer = $this->customerRepository->getById($customerId);
                    } catch (Exception $e) {
                        return;
                    }
                } else {
                    return;
                }

                //get customer attribute telegram_chat_id value
                $customerChatCheck = get_class_methods($customer->getCustomAttribute('telegram_chat_id'));

                if (isset($customerChatCheck)) {
                    $chatValue = $customer->getCustomAttribute('telegram_chat_id')->getValue();
                } else {
                    $chatValue = 0;
                }

                $orderStatus = $order->getState();

                $customerName = $customer->getFirstname();

                $items = $order->getAllVisibleItems();
                $itemsData = '';
                foreach ($items as $item) {
                    if ($item->getData()) {
                        $itemsData .= " " . $item->getName() . " ::" . $item->getPrice();
                    }
                }

                $apiToken = $this->scopeConfig->getValue(self::TELEGRAM_API_TOKEN);
                $data = [
                    'chat_id' => $chatValue,
                    'text' => "Hi $customerName, you order($orderId) updated to  $orderStatus status. Items: $itemsData."
                ];

                try {
                    file_get_contents("https://api.telegram.org/bot$apiToken/sendMessage?" . http_build_query($data));
                } catch (Exception $e) {
                    $this->logger->critical("Telegram connection is wrong. " . $e->getMessage());
                }
            }
        }
    }
}