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

namespace Pmclain\Twilio\Model\Adapter\Order;

use Pmclain\Twilio\Model\Adapter\AdapterAbstract;
use Magento\Sales\Model\Order\Invoice as SalesInvoice;

class Invoice extends AdapterAbstract
{
  /**
   * @param \Magento\Sales\Model\Order\Invoice $invoice
   * @return \Pmclain\Twilio\Model\Adapter\Order\Invoice
   */
  public function sendOrderSms(SalesInvoice $invoice) {
    if(!$this->_helper->isInvoiceMessageEnabled()) { return $this; }

    $this->_message = $this->_messageTemplateParser->parseTemplate(
      $this->_helper->getRawInvoiceMessage(),
      $this->getInvoiceVariables($invoice)
    );

    $order = $invoice->getOrder();

    //TODO: something needs to verify the phone number
    //      and add country code
    $this->_recipientPhone = '+1' . $order->getShippingAddress()->getTelephone();

    try {
      $this->_smsStatus = $this->_sendSms();
    }catch (\Exception $e) {
      $this->_logger->addCritical($e->getMessage());
    }

    return $this;
  }

  /**
   * @param \Magento\Sales\Model\Order\Invoice $invoice
   * @return array
   */
  protected function getInvoiceVariables($invoice) {
    $vars = [];

    $vars['invoice.qty'] = $invoice->getTotalQty();
    $vars['invoice.grandtotal'] = $invoice->getGrandTotal(); //TODO: not properly formatted
    $vars['invoice.increment_id'] = $invoice->getIncrementId();
    $vars['order.increment_id'] = $invoice->getOrder()->getIncrementId();
    $vars['order.qty'] = $invoice->getOrder()->getTotalQtyOrdered();
    $vars['shipment.firstname'] = $invoice->getOrder()->getShippingAddress()->getFirstname();
    $vars['shipment.lastname'] = $invoice->getOrder()->getShippingAddress()->getLastname();
    $vars['storename'] = $this->_storeManager->getWebsite(
        $this->_storeManager->getStore($invoice->getOrder()->getStoreId())->getWebsiteId()
      )->getName();

    return $vars;
  }
}