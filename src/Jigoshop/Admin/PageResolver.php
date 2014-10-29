<?php

namespace Jigoshop\Admin;

use Jigoshop\Core\Types;
use Symfony\Component\DependencyInjection\Container;
use WPAL\Wordpress;

/**
 * Factory that decides what current page is and provides proper page object.
 *
 * @package Jigoshop\Admin
 */
class PageResolver
{
	/** @var \WPAL\Wordpress */
	private $wp;

	public function __construct(Wordpress $wp)
	{
		$this->wp = $wp;
	}

	public function resolve(Container $container)
	{
		if (defined('DOING_AJAX') && DOING_AJAX) {
			// Instantiate page to install Ajax actions
			$this->getPage($container);
		} else {
			$that = $this;
			$this->wp->addAction('current_screen', function () use ($container, $that){
				$page = $that->getPage($container);
				$container->set('jigoshop.page.current', $page);
			});
		}
	}

	public function getPage(Container $container)
	{
		if ($this->isProductsList()) {
			return $container->get('jigoshop.admin.page.products');
		}

		if ($this->isProduct()) {
			return $container->get('jigoshop.admin.page.product');
		}

		if ($this->isOrdersList()) {
			return $container->get('jigoshop.admin.page.orders');
		}

		if ($this->isOrder()) {
			return $container->get('jigoshop.admin.page.order');
		}

		return null;
	}

	private function isProductsList()
	{
		$screen = $this->wp->getCurrentScreen();

		if ($screen !== null) {
			return $screen->post_type === Types::PRODUCT && $screen->id === 'edit-'.Types::PRODUCT;
		}

		return DOING_AJAX && isset($_POST['action']) && strpos($_POST['action'], 'admin.products') !== false;
	}

	private function isProduct()
	{
		$screen = $this->wp->getCurrentScreen();

		if ($screen !== null) {
			return $screen->post_type === Types::PRODUCT && $screen->id === Types::PRODUCT;
		}

		return DOING_AJAX && isset($_POST['action']) && strpos($_POST['action'], 'admin.product') !== false;
	}

	private function isOrdersList()
	{
		$screen = $this->wp->getCurrentScreen();

		if ($screen !== null) {
			return $screen->post_type === Types::ORDER && $screen->id === 'edit-'.Types::ORDER;
		}

		return DOING_AJAX && isset($_POST['action']) && strpos($_POST['action'], 'admin.orders') !== false;
	}

	private function isOrder()
	{
		$screen = $this->wp->getCurrentScreen();

		if ($screen !== null) {
			return $screen->post_type === Types::ORDER && $screen->id === Types::ORDER;
		}

		return DOING_AJAX && isset($_POST['action']) && strpos($_POST['action'], 'admin.order') !== false;
	}
}
