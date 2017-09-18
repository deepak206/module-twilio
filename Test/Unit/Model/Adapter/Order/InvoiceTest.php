<?php
/**
 * Pmclain_Twilio extension
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the GPL v3 License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://www.gnu.org/licenses/gpl.txt
 *
 * @category       Pmclain
 * @package        Twilio
 * @copyright      Copyright (c) 2017
 * @license        https://www.gnu.org/licenses/gpl.txt GPL v3 License
 */

namespace Pmclain\Twilio\Test\Unit\Model\Adapter\Order;

use Pmclain\Twilio\Model\Adapter\Order\Invoice as InvoiceAdapter;
use PHPUnit_Framework_MockObject_MockObject as MockObject;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address;
use Magento\Sales\Model\Order\Invoice;
use Pmclain\Twilio\Helper\Data as Helper;
use Twilio\Rest\ClientFactory as TwilioClientFactory;
use Twilio\Rest\Client as TwilioClient;
use Twilio\Rest\Api\V2010\Account\MessageList;
use Psr\Log\LoggerInterface;
use Pmclain\Twilio\Helper\MessageTemplateParser;
use Magento\Store\Model\StoreManagerInterface;

/** @codeCoverageIgnore */
class InvoiceTest extends \PHPUnit_Framework_TestCase
{
    /** @var \Pmclain\Twilio\Model\Adapter\Order\Invoice */
    protected $invoiceAdapter;

    /** @var \Magento\Sales\Model\Order\Invoice|MockObject */
    protected $invoiceMock;

    /** @var \Magento\Sales\Model\Order|MockObject */
    protected $orderMock;

    /** @var \Magento\Sales\Model\Order\Address|MockObject */
    protected $addressMock;

    /** @var \Pmclain\Twilio\Helper\Data|MockObject */
    protected $helperMock;

    /** @var \Twilio\Rest\ClientFactory|MockObject */
    protected $twilioClientFactoryMock;

    /** @var \Twilio\Rest\Client|MockObject */
    protected $twilioClientMock;

    /** @var \Twilio\Rest\Api\V2010\Account\MessageList|MockObject */
    protected $twilioMessagesMock;

    /** @var \Psr\Log\LoggerInterface|MockObject */
    protected $loggerMock;

    /** @var \Pmclain\Twilio\Helper\MessageTemplateParser */
    protected $messageTemplateParser;

    /** @var \Magento\Store\Model\StoreManagerInterface|MockObject */
    protected $storeManager;

    protected function setUp()
    {
        $objectManager = new \Magento\Framework\TestFramework\Unit\Helper\ObjectManager($this);

        $this->helperMock = $this->getMockBuilder(Helper::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'isInvoiceMessageEnabled',
                'getRawInvoiceMessage',
                'getAccountSid',
                'getAccountAuthToken',
                'getTwilioPhone',
                'isLogEnabled'
            ])
            ->getMock();

        $this->helperMock->expects($this->once())
            ->method('isInvoiceMessageEnabled')
            ->willReturn(true);
        $this->helperMock->expects($this->once())
            ->method('getRawInvoiceMessage')
            ->willReturn('Invoice {{invoice.increment_id}} for {{invoice.grandtotal}} was created for {{order.increment_id}}');
        $this->helperMock->expects($this->once())
            ->method('getAccountSid')
            ->willReturn('accountsid');
        $this->helperMock->expects($this->once())
            ->method('getAccountAuthToken')
            ->willReturn('accounttoken');
        $this->helperMock->expects($this->once())
            ->method('getTwilioPhone')
            ->willReturn('5559285362');
        $this->helperMock->expects($this->once())
            ->method('isLogEnabled')
            ->willReturn(false);

        $this->storeManager = $this->getMockForAbstractClass(
            StoreManagerInterface::class,
            [],
            '',
            false,
            true,
            true,
            ['getWebsite', 'getStore', 'getName', 'getWebsiteId']
        );

        $this->storeManager->expects($this->any())
            ->method('getWebsite')
            ->willReturnSelf();

        $this->storeManager->expects($this->any())
            ->method('getStore')
            ->willReturnSelf();

        $this->loggerMock = $this->getMockForAbstractClass(
            LoggerInterface::class,
            [],
            '',
            false,
            true,
            true,
            ['addCritical']
        );

        $this->twilioMessagesMock = $this->getMockBuilder(MessageList::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->twilioMessagesMock->expects($this->once())
            ->method('create');

        $this->twilioClientMock = $this->getMockBuilder(TwilioClient::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->twilioClientMock->messages = $this->twilioMessagesMock;

        $this->twilioClientFactoryMock = $this->getMockBuilder(TwilioClientFactory::class)
            ->disableOriginalConstructor()
            ->setMethods(['create'])
            ->getMock();

        $this->twilioClientFactoryMock->expects($this->once())
            ->method('create')
            ->with(['username' => 'accountsid', 'password' => 'accounttoken'])
            ->willReturn($this->twilioClientMock);

        $this->addressMock = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->setMethods(['getTelephone'])
            ->getMock();

        $this->addressMock->expects($this->once())
            ->method('getTelephone')
            ->willReturn('5553821442');

        $this->orderMock = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getIncrementId',
                'getTotalQtyOrdered',
                'getCustomerFirstname',
                'getCustomerLastname',
                'getGrandTotal',
                'getStoreName',
                'getBillingAddress'
            ])
            ->getMock();

        $this->orderMock->expects($this->atLeastOnce())
            ->method('getBillingAddress')
            ->willReturn($this->addressMock);

        $this->orderMock->expects($this->once())
            ->method('getIncrementId')
            ->willReturn('100000123');

        $this->invoiceMock = $this->getMockBuilder(Invoice::class)
            ->disableOriginalConstructor()
            ->setMethods([
                'getOrder',
                'getTotalQty',
                'getGrandTotal',
                'getIncrementId'
            ])
            ->getMock();

        $this->invoiceMock->expects($this->atLeastOnce())
            ->method('getOrder')
            ->willReturn($this->orderMock);

        $this->messageTemplateParser = $objectManager->getObject(MessageTemplateParser::class);

        $this->invoiceAdapter = $objectManager->getObject(
            InvoiceAdapter::class,
            [
                '_helper' => $this->helperMock,
                '_logger' => $this->loggerMock,
                '_twilioClientFactory' => $this->twilioClientFactoryMock,
                '_messageTemplateParser' => $this->messageTemplateParser,
                '_storeManager' => $this->storeManager
            ]
        );
    }

    public function testSendOrderSms()
    {
        $this->invoiceAdapter->sendOrderSms($this->invoiceMock);
    }
}
