<?php

namespace Jigoshop\Entity;

use Jigoshop\Core\Types;
use Jigoshop\Entity\Customer\Guest;
use Jigoshop\Entity\Order\Item;
use Jigoshop\Entity\Order\Status;
use Jigoshop\Exception;
use Jigoshop\Payment\Method as PaymentMethod;
use Jigoshop\Service\TaxServiceInterface;
use Jigoshop\Shipping\Method as ShippingMethod;
use WPAL\Wordpress;

/**
 * Order class.
 * TODO: Fully implement the class.
 *
 * @package Jigoshop\Entity
 * @author Amadeusz Starzykiewicz
 */
class Order implements EntityInterface, OrderInterface
{
	/** @var int */
	private $id;
	/** @var string */
	private $number;
	/** @var \DateTime */
	private $created_at;
	/** @var \DateTime */
	private $updated_at;
	/** @var Customer */
	private $customer;
	/** @var array */
	private $items = array();
	/** @var Order\Address */
	private $billingAddress;
	/** @var Order\Address */
	private $shippingAddress;
	/** @var ShippingMethod */
	private $shippingMethod;
	/** @var PaymentMethod */
	private $payment;
	/** @var float */
	private $productSubtotal;
	/** @var float */
	private $subtotal = 0.0;
	/** @var float */
	private $total = 0.0;
	/** @var float */
	private $discount = 0.0;
	/** @var array */
	private $tax = array();
	/** @var array */
	private $shippingTax = array();
	/** @var float */
	private $shippingPrice = 0.0;
	/** @var string */
	private $status = Status::CREATED;
	/** @var string */
	private $customerNote;

	/** @var \WPAL\Wordpress */
	protected $wp;

	public function __construct(Wordpress $wp, array $taxClasses)
	{
		$this->wp = $wp;

		$this->customer = new Guest();
		$this->billingAddress = new Order\Address();
		$this->shippingAddress = new Order\Address();
		$this->created_at = new \DateTime();
		$this->updated_at = new \DateTime();

		foreach ($taxClasses as $class) {
			$this->tax[$class['class']] = 0.0;
			$this->shippingTax[$class['class']] = 0.0;
		}
	}

	/**
	 * Adds a note to the order.
	 *
	 * @param $note string Note text.
	 * @param $private bool Is note private?
	 * @return int Note ID.
	 */
	public function addNote($note, $private = true)
	{
		// TODO: Remove WP calls
		$comment = array(
			'comment_post_ID' => $this->id,
			'comment_author' => __('Jigoshop', 'jigoshop'),
			'comment_author_email' => '',
			'comment_author_url' => '',
			'comment_content' => $note,
			'comment_type' => 'order_note',
			'comment_agent' => __('Jigoshop', 'jigoshop'),
			'comment_parent' => 0,
			'comment_date' => current_time('timestamp'),
			'comment_date_gmt' => current_time('timestamp', true),
			'comment_approved' => true
		);

		$comment_id = wp_insert_comment($comment);
		add_comment_meta($comment_id, 'private', $private);

		return $comment_id;
	}

	/**
	 * @return int Entity ID.
	 */
	public function getId()
	{
		return $this->id;
	}

	/**
	 * @param $id int Order ID.
	 */
	public function setId($id)
	{
		$this->id = $id;
	}

	/**
	 * @return string Title of the order.
	 */
	public function getTitle()
	{
		return sprintf(__('Order %d', 'jigoshop'), $this->getNumber());
	}

	/**
	 * @return int Order number.
	 */
	public function getNumber()
	{
		return $this->number;
	}

	/**
	 * @param string $number The order number.
	 */
	public function setNumber($number)
	{
		$this->number = $number;
	}

	/**
	 * @return Order\Address Billing address.
	 */
	public function getBillingAddress()
	{
		return $this->billingAddress;
	}

	/**
	 * @param Order\Address $billingAddress
	 */
	public function setBillingAddress($billingAddress)
	{
		$this->billingAddress = $billingAddress;
	}

	/**
	 * @return Order\Address Shipping address.
	 */
	public function getShippingAddress()
	{
		return $this->shippingAddress;
	}

	/**
	 * @param Order\Address $shippingAddress
	 */
	public function setShippingAddress($shippingAddress)
	{
		$this->shippingAddress = $shippingAddress;
	}

	/**
	 * @return \DateTime Time the order was created at.
	 */
	public function getCreatedAt()
	{
		return $this->created_at;
	}

	/**
	 * @param \DateTime $created_at Creation time.
	 */
	public function setCreatedAt($created_at)
	{
		$this->created_at = $created_at;
	}

	/**
	 * @return \DateTime Time the order was updated at.
	 */
	public function getUpdatedAt()
	{
		return $this->updated_at;
	}

	/**
	 * @param \DateTime $updated_at Last update time.
	 */
	public function setUpdatedAt($updated_at)
	{
		$this->updated_at = $updated_at;
	}

	/**
	 * @return Customer The customer.
	 */
	public function getCustomer()
	{
		return $this->customer;
	}

	/**
	 * @param Customer $customer
	 */
	public function setCustomer($customer)
	{
		$this->customer = $customer;
	}

	/**
	 * @return float Value of discounts added to the order.
	 */
	public function getDiscount()
	{
		return $this->discount;
	}

	/**
	 * @param float $discount Total value of discounts for the order.
	 */
	public function setDiscount($discount)
	{
		$this->discount = $discount;
	}

	/**
	 * @return array List of items bought.
	 */
	public function getItems()
	{
		return $this->items;
	}

	/**
	 * Removes all items, shipping method and associated taxes from the order.
	 */
	public function removeItems()
	{
		$this->removeShippingMethod();
		$this->items = array();
		$this->productSubtotal = 0.0;
		$this->subtotal = 0.0;
		$this->total = 0.0;
		$this->tax = array_map(function() { return 0.0; }, $this->tax);
	}

	/**
	 * Returns item of selected ID.
	 *
	 * @param $item int Item ID to fetch.
	 * @return Item Order item.
	 * @throws Exception When item is not found.
	 */
	public function getItem($item)
	{
		if (!isset($this->items[$item])) {
			throw new Exception(sprintf(__('No item with ID %d in order %d', 'jigoshop'), $item, $this->id));
		}

		return $this->items[$item];
	}

	/**
	 * @param Item $item Item to add.
	 */
	public function addItem(Item $item)
	{
		$this->items[$item->getId()] = $item;
		$this->productSubtotal += $item->getCost();
		$this->subtotal += $item->getCost();
		$this->total += $item->getCost() + $item->getTotalTax();

		foreach ($item->getTax() as $class => $tax) {
			$this->tax[$class] += $tax * $item->getQuantity();
		}
	}

	/**
	 * @param $item int Item ID to remove.
	 * @return Item Removed item.
	 */
	public function removeItem($item)
	{
		$item = $this->items[$item];

		/** @var Item $item */
		$this->productSubtotal -= $item->getCost();
		$this->subtotal -= $item->getCost();
		$this->total -= $item->getCost() + $item->getTotalTax();

		foreach ($item->getTax() as $class => $tax) {
			$this->tax[$class] -= $tax * $item->getQuantity();
		}

		unset($this->items[$item->getId()]);
		return $item;
	}

	/**
	 * @return PaymentMethod Payment gateway object.
	 */
	public function getPayment()
	{
		return $this->payment;
	}

	/**
	 * @param PaymentMethod $payment Method used to pay.
	 */
	public function setPayment($payment)
	{
		$this->payment = $payment;
	}

	/**
	 * @return ShippingMethod Shipping method.
	 */
	public function getShippingMethod()
	{
		return $this->shippingMethod;
	}

	/**
	 * @param ShippingMethod $method Method used for shipping the order.
	 * @param TaxServiceInterface $taxService Tax service to calculate tax value of shipping.
	 * @param Customer $customer Customer for tax calculation.
	 */
	public function setShippingMethod(ShippingMethod $method, TaxServiceInterface $taxService, Customer $customer = null)
	{
		// TODO: Refactor to abstract between cart and order = AbstractOrder
		$this->removeShippingMethod();

		$this->shippingMethod = $method;
		$this->shippingPrice = $method->calculate($this);
		$this->subtotal += $this->shippingPrice;
		$this->total += $this->shippingPrice + $taxService->calculateShipping($method, $this->shippingPrice, $customer);
		foreach ($method->getTaxClasses() as $class) {
			$this->shippingTax[$class] = $taxService->getShipping($method, $this->shippingPrice, $class, $customer);
		}
	}

	/**
	 * Removes shipping method and associated taxes from the order.
	 */
	public function removeShippingMethod()
	{
		$this->subtotal -= $this->shippingPrice;
		$this->total -= $this->shippingPrice + array_reduce($this->shippingTax, function($value, $item){ return $value + $item; }, 0.0);

		$this->shippingMethod = null;
		$this->shippingPrice = 0.0;
		$this->shippingTax = array_map(function() { return 0.0; }, $this->shippingTax);
	}

	/**
	 * Checks whether given shipping method is set for current cart.
	 *
	 * @param $method Method Shipping method to check.
	 * @return bool Is the method selected?
	 */
	public function hasShippingMethod($method)
	{
		if ($this->shippingMethod != null) {
			return $this->shippingMethod->getId() == $method->getId();
		}

		return false;
	}

	/**
	 * @return string Current order status.
	 */
	public function getStatus()
	{
		return $this->status;
	}

	/**
	 * @param string $status New order status.
	 */
	public function setStatus($status)
	{
		$this->status = $status;
	}

	/**
	 * @param $status string New order status.
	 * @param $message string Message to add.
	 * @since 2.0
	 */
	public function updateStatus($status, $message = '')
	{
		if ($status) {
			if ($this->status != $status) {
				// Do actions for changing statuses
				$this->wp->doAction('jigoshop\order\before\\'.$status, $this);
				$this->wp->doAction('jigoshop\order\\'.$this->status.'_to_'.$status, $this);

				$this->addNote($message.sprintf(__('Order status changed from %s to %s.', 'jigoshop'), Status::getName($this->status), Status::getName($status)));
				$this->status = $status;

				// Date
				if ($status == Status::COMPLETED) {
					// TODO: Add completion date and save overall quantity sold.
//					update_post_meta($this->id, 'completed_date', current_time('timestamp'));
//					foreach ($this->items as $item) {
//						/** @var \Jigoshop\Entity\Order\Item $item */
//						$sales = get_post_meta($item->getProduct()->getId(), 'quantity_sold', true) + $item->getQuantity();
//						update_post_meta($item->getProduct()->getId(), 'quantity_sold', $sales);
//					}
				}

				$this->wp->doAction('jigoshop\order\after\\'.$status, $this);
			}
		}
	}

	/**
	 * @return string Customer's note on the order.
	 */
	public function getCustomerNote()
	{
		return $this->customerNote;
	}

	/**
	 * @param string $customerNote Customer's note on the order.
	 */
	public function setCustomerNote($customerNote)
	{
		$this->customerNote = $customerNote;
	}

	/**
	 * @return float
	 */
	public function getProductSubtotal()
	{
		return $this->productSubtotal;
	}

	/**
	 * @param float $productSubtotal
	 */
	public function setProductSubtotal($productSubtotal)
	{
		$this->productSubtotal = $productSubtotal;
	}

	/**
	 * @return float Subtotal value of the cart.
	 */
	public function getSubtotal()
	{
		return $this->subtotal;
	}

	/**
	 * @param float $subtotal New subtotal value.
	 */
	public function setSubtotal($subtotal)
	{
		$this->subtotal = $subtotal;
	}

	/**
	 * @return float Total value of the cart.
	 */
	public function getTotal()
	{
		return $this->total;
	}

	/**
	 * @param float $total New total value.
	 */
	public function setTotal($total)
	{
		$this->total = $total;
	}

	/**
	 * @return array List of applied tax classes with it's values.
	 */
	public function getTax()
	{
		return $this->tax;
	}

	/**
	 * @param array $tax Tax data array.
	 */
	public function setTax($tax)
	{
		$this->tax = $tax;
	}

	/**
	 * @return array List of applied tax classes for shipping with it's values.
	 */
	public function getShippingTax()
	{
		return $this->shippingTax;
	}

	/**
	 * @param array $shippingTax Tax data array for shipping.
	 */
	public function setShippingTax($shippingTax)
	{
		$this->shippingTax = $shippingTax;
	}

	/**
	 * Updates stored tax array with provided values.
	 *
	 * @param array $tax Tax divided by classes.
	 */
	public function updateTaxes(array $tax)
	{
		foreach ($tax as $class => $value) {
			$this->tax[$class] += $value;
		}
	}

	/**
	 * @return float Total tax of the order.
	 */
	public function getTotalTax()
	{
		// TODO: Probably nice idea to keep it stored
		return array_reduce($this->tax, function($value, $item){ return $value + $item; }, 0.0);
	}

	/**
	 * Updates quantity of selected item by it's key.
	 *
	 * @param $key string Item key in the order.
	 * @param $quantity int Quantity to set.
	 * @throws Exception When product does not exists or quantity is not numeric.
	 */
	public function updateQuantity($key, $quantity)
	{
		if (!isset($this->items[$key])) {
			throw new Exception(__('Item does not exists', 'jigoshop')); // TODO: Will be nice to get better error message
		}

		if (!is_numeric($quantity)) {
			throw new Exception(__('Quantity has to be numeric value', 'jigoshop'));
		}

		if ($quantity <= 0) {
			$this->removeItem($key);
			return;
		}

		/** @var Item $item */
		$item = $this->items[$key];
		$difference = $quantity - $item->getQuantity();
		$this->total += ($item->getPrice() + $item->getTax()) * $difference;
		$this->subtotal += $item->getPrice() * $difference;
		$this->productSubtotal += $item->getPrice() * $difference;
		foreach ($item->getProduct()->getTaxClasses() as $class) {
			// TODO: Pass tax service as well
			$this->tax[$class] += $this->taxService->get($item->getProduct(), $class) * $difference;
		}
		$item->setQuantity($quantity);
	}

	/**
	 * @return array List of fields to update with according values.
	 */
	public function getStateToSave()
	{
		return array(
			'id' => $this->id,
			'number' => $this->number,
			'updated_at' => $this->updated_at->getTimestamp(),
			'items' => $this->items,
			'billing_address' => serialize($this->billingAddress),
			'shipping_address' => serialize($this->shippingAddress),
			'customer' => $this->customer->getId(),
			'shipping' => array(
				'method' => $this->shippingMethod ? $this->shippingMethod->getState() : false,
				'price' => $this->shippingPrice,
			),
			'payment' => $this->payment ? $this->payment->getId() : false, // TODO: Maybe a state as for shipping methods?
			'customer_note' => $this->customerNote,
			'total' => $this->total,
			'subtotal' => $this->subtotal,
			'discount' => $this->discount,
			'shipping_tax' => $this->shippingTax,
			'status' => $this->status,
		);
	}

	/**
	 * @param array $state State to restore entity to.
	 */
	public function restoreState(array $state)
	{
		if (isset($state['number'])) {
			$this->number = $state['number'];
		}
		if (isset($state['updated_at'])) {
			$this->updated_at->setTimestamp($state['updated_at']);
		}
		if (isset($state['items'])) {
			foreach ($state['items'] as $item) {
				$this->addItem($item);
			}
		}
		if (isset($state['billing_address'])) {
			$this->billingAddress = unserialize($state['billing_address']);
		}
		if (isset($state['shipping_address'])) {
			$this->shippingAddress = unserialize($state['shipping_address']);
		}
		// TODO: Properly keep Customer here! Instead of fromOrder() method in Customer service
		if (isset($state['customer'])) {
			$this->customer = $state['customer'];
		}
		if (isset($state['shipping']) && is_array($state['shipping'])) {
			$this->shippingMethod = $state['shipping']['method'];
			$this->shippingPrice = $state['shipping']['price'];
		}
		if (isset($state['payment']) && !empty($state['payment'])) {
			$this->payment = $state['payment'];
		}
		if (isset($state['customer_note'])) {
			$this->customerNote = $state['customer_note'];
		}
		if (isset($state['shipping_tax'])) {
			$tax = unserialize($state['shipping_tax']);
			foreach ($tax as $class => $value) {
				$this->shippingTax[$class] += $value;
			}
		}
		if (isset($state['product_subtotal'])) {
			$this->productSubtotal = (float)$state['product_subtotal'];
		}
		if (isset($state['subtotal'])) {
			$this->subtotal = (float)$state['subtotal'];
		}
		if (isset($state['discount'])) {
			$this->discount = (float)$state['discount'];
		}

		$this->total = $this->subtotal + array_reduce($this->tax, function($value, $item){ return $value + $item; }, 0.0)
			+ array_reduce($this->shippingTax, function($value, $item){ return $value + $item; }, 0.0) - $this->discount;

		if (isset($state['status'])) {
			$this->status = $state['status'];
		}
	}
}
