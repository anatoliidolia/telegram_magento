<?php
declare(strict_types=1);

namespace PeachCode\Telegram\Observer;

use Exception;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Model\QuoteRepository;
use Psr\Log\LoggerInterface;

/**
 * Event for send data to Telegram Bot
 */
class TelegramObserver implements ObserverInterface
{
    public const IS_ENABLE = 'checkout/custom/telegram_integration';

    public const TELEGRAM_API_TOKEN = 'checkout/custom/telegram_api_token';

    public const TELEGRAM_CHAT_ID = 'checkout/custom/telegram_chat_id';

    /**
     * @var QuoteRepository
     */
    private $quoteRepository;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param QuoteRepository $quoteRepository
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        QuoteRepository      $quoteRepository,
        ScopeConfigInterface $scopeConfig,
        LoggerInterface      $logger
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Execute for send message to the bot
     *
     * @param Observer $observer
     * @return $this|string
     * @throws NoSuchEntityException
     */
    public function execute(Observer $observer)
    {
        $order = $observer->getEvent()->getOrder();
        if ($this->scopeConfig->getValue(self::IS_ENABLE)) { //check if module enable
            $quote = $this->quoteRepository->get($order->getQuoteId());
            $customerName = $quote->getCustomerFirstname();
            $finalPrice = $quote->getBaseSubtotal();

            $items = $quote->getAllVisibleItems();
            $itemsData = '';
            foreach ($items as $item) {
                if ($item->getData()) {
                    $itemsData .= " Product: ".$item->getName() . " QTY: " . $item->getQty() . " Price: " . $item->getPrice();
                }
            }
            $apiToken = $this->scopeConfig->getValue(self::TELEGRAM_API_TOKEN);
            $data = [
                'chat_id' => $this->scopeConfig->getValue(self::TELEGRAM_CHAT_ID),
                'text' => "New Order From: $customerName, Items: $itemsData. Price: $finalPrice"
            ];

            try {
                return file_get_contents("https://api.telegram.org/bot$apiToken/sendMessage?" . http_build_query($data));
            } catch (Exception $e) {
                $this->logger->critical("Telegram connection is wrong. " . $e->getMessage());
                return $this;
            }
        }

        return $this;
    }
}