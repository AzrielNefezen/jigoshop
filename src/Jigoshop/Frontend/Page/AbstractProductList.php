<?php

namespace Jigoshop\Frontend\Page;

use Jigoshop\Core\Messages;
use Jigoshop\Core\Options;
use Jigoshop\Core\Pages;
use Jigoshop\Core\Types;
use Jigoshop\Entity\Order\Item;
use Jigoshop\Entity\Product;
use Jigoshop\Helper\Render;
use Jigoshop\Helper\Scripts;
use Jigoshop\Helper\Styles;
use Jigoshop\Service\CartServiceInterface;
use Jigoshop\Service\ProductServiceInterface;
use WPAL\Wordpress;

abstract class AbstractProductList implements PageInterface
{
	/** @var \WPAL\Wordpress */
	protected $wp;
	/** @var \Jigoshop\Core\Options */
	protected $options;
	/** @var ProductServiceInterface */
	protected $productService;
	/** @var CartServiceInterface */
	protected $cartService;
	/** @var Messages  */
	protected $messages;

	public function __construct(Wordpress $wp, Options $options, ProductServiceInterface $productService, CartServiceInterface $cartService, Messages $messages, Styles $styles,
		Scripts $scripts)
	{
		$this->wp = $wp;
		$this->options = $options;
		$this->productService = $productService;
		$this->cartService = $cartService;
		$this->messages = $messages;

		$styles->add('jigoshop.shop', JIGOSHOP_URL.'/assets/css/shop.css');
		$styles->add('jigoshop.shop.list', JIGOSHOP_URL.'/assets/css/shop/list.css');
		$scripts->add('jigoshop.helpers', JIGOSHOP_URL.'/assets/js/helpers.js');
		$scripts->add('jigoshop.shop', JIGOSHOP_URL.'/assets/js/shop.js');
	}


	public function action()
	{
		if (isset($_POST['action']) && $_POST['action'] == 'add-to-cart') {
			$product = $this->productService->find($_POST['item']);
			$item = $this->formatItem($product);
			$cart = $this->cartService->get($this->cartService->getCartIdForCurrentUser());

			try {
				$cart->addItem($item);
				$this->cartService->save($cart);

				$url = false;
				$button = '';
				switch ($this->options->get('shopping.redirect_add_to_cart')) {
					case 'cart':
						$url = $this->wp->getPermalink($this->options->getPageId(Pages::CART));
						break;
					case 'checkout':
						$url = $this->wp->getPermalink($this->options->getPageId(Pages::CHECKOUT));
						break;
					case 'product':
					default:
						$url = $this->wp->getPermalink($product->getId());
					case 'same_page':
					case 'product_list':
						$button = sprintf('<a href="%s" class="btn btn-warning pull-right">%s</a>', $this->wp->getPermalink($this->options->getPageId(Pages::CART)), __('View cart', 'jigoshop'));
				}

				$this->messages->addNotice(sprintf(__('%s successfully added to your cart. %s', 'jigoshop'), $product->getName(), $button));
				if ($url !== false) {
					$this->messages->preserveMessages();
					$this->wp->wpRedirect($url);
					exit;
				}
			} catch(Exception $e) {
				// TODO: Could be improved with `NotEnoughStockException` and others
				$this->messages->addError(sprintf(__('A problem ocurred when adding to cart: %s', 'jigoshop'), $e->getMessage()), false);
			}
		}
	}

	public function render()
	{
		$query = $this->wp->getWpQuery();
		$products = $this->productService->findByQuery($query);
		return Render::get('shop', array(
			'content' => $this->getContent(),
			'products' => $products,
			'product_count' => $query->max_num_pages,
			'messages' => $this->messages,
			'title' => $this->getTitle(),
		));
	}

	/**
	 * @param $product Product|Product\Purchasable The product to format.
	 * @return Item Prepared item.
	 */
	private function formatItem($product)
	{
		$item = new Item();
		$item->setType($product->getType());
		$item->setName($product->getName());
		$item->setPrice($product->getPrice());
		$item->setQuantity(1);
		$item->setProduct($product);

		return $item;
	}

	public abstract function getTitle();
	public abstract function getContent();
}
