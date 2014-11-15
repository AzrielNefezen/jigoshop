<?php

namespace Jigoshop\Admin\Page;

use Jigoshop\Core\Options;
use Jigoshop\Core\Types;
use Jigoshop\Entity\Product\Attributes\Attribute;
use Jigoshop\Exception;
use Jigoshop\Helper\Render;
use Jigoshop\Helper\Scripts;
use Jigoshop\Helper\Styles;
use Jigoshop\Service\ProductServiceInterface;
use WPAL\Wordpress;

class Product
{
	/** @var \WPAL\Wordpress */
	private $wp;
	/** @var \Jigoshop\Core\Options */
	private $options;
	/** @var \Jigoshop\Service\ProductServiceInterface */
	private $productService;
	/** @var Types\Product */
	private $type;

	public function __construct(Wordpress $wp, Options $options, Types\Product $type, ProductServiceInterface $productService, Styles $styles, Scripts $scripts)
	{
		$this->wp = $wp;
		$this->options = $options;
		$this->productService = $productService;
		$this->type = $type;

		$wp->addAction('wp_ajax_jigoshop.admin.product.find', array($this, 'ajaxFindProduct'), 10, 0);
		$wp->addAction('wp_ajax_jigoshop.admin.product.save_attribute', array($this, 'ajaxSaveAttribute'), 10, 0);

		$that = $this;
		$wp->addAction('add_meta_boxes_'.Types::PRODUCT, function() use ($wp, $that){
			$wp->addMetaBox('jigoshop-product-data', __('Product Data', 'jigoshop'), array($that, 'box'), Types::PRODUCT, 'normal', 'high');
			$wp->removeMetaBox('commentstatusdiv', null, 'normal');
		});
		$wp->addAction('admin_enqueue_scripts', function() use ($wp, $styles, $scripts){
			if ($wp->getPostType() == Types::PRODUCT) {
				$styles->add('jigoshop.admin.product', JIGOSHOP_URL.'/assets/css/admin/product.css');
				$scripts->add('jigoshop.helpers', JIGOSHOP_URL.'/assets/js/helpers.js');
				$scripts->add('jigoshop.admin.product', JIGOSHOP_URL.'/assets/js/admin/product.js', array('jquery', 'jigoshop.helpers'));
				$scripts->localize('jigoshop.admin.product', 'jigoshop_admin_product', array(
					'ajax' => $wp->getAjaxUrl(),
				));
			}
		});
	}

	/**
	 * Displays the product data box, tabbed, with several panels covering price, stock etc
	 *
	 * @since 		1.0
	 */
	public function box()
	{
		$post = $this->wp->getGlobalPost();
		$product = $this->productService->findForPost($post);
		$types = array();

		foreach ($this->type->getEnabledTypes() as $type) {
			$types[$type] = $this->type->getTypeName($type);
		}

		$menu = $this->wp->applyFilters('jigoshop\admin\product\menu', array(
			'general' => array('label' => __('General', 'jigoshop'), 'visible' => true),
			'advanced' => array('label' => __('Advanced', 'jigoshop'), 'visible' => true),
			'stock' => array('label' => __('Stock', 'jigoshop'), 'visible' => true),
			'sales' => array('label' => __('Sales', 'jigoshop'), 'visible' => array('simple')),
			'attributes' => array('label' => __('Attributes', 'jigoshop'), 'visible' => true),
//			'inventory' => __('Inventory', 'jigoshop'),
		));
		$taxClasses = array();
		foreach ($this->options->get('tax.classes') as $class) {
			$taxClasses[$class['class']] = $class['label'];
		}

		$attributes = array(
			'' => '',
		);
		foreach($this->productService->findAllAttributes() as $attribute) {
			/** @var $attribute Attribute */
			$attributes[$attribute->getId()] = $attribute->getLabel();
		}

		$tabs = $this->wp->applyFilters('jigoshop\admin\product\tabs', array(
			'general' => array(
				'product' => $product,
			),
			'stock' => array(
				'product' => $product,
			),
			'sales' => array(
				'product' => $product,
			),
			'advanced' => array(
				'product' => $product,
				'taxClasses' => $taxClasses,
			),
			'attributes' => array(
				'product' => $product,
				'availableAttributes' => $attributes,
				'attributes' => $this->productService->getAttributes($product->getId()),
			),
		));

//		add_action('admin_footer', 'jigoshop_meta_scripts');
//		wp_nonce_field('jigoshop_save_data', 'jigoshop_meta_nonce');

		Render::output('admin/product/box', array(
			'product' => $product,
			'types' => $types,
			'menu' => $menu,
			'tabs' => $tabs,
			'current_tab' => 'general',
		));
	}

	public function ajaxSaveAttribute()
	{
		try {
			if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
				throw new Exception(__('Product was not specified.', 'jigoshop'));
			}
			if (!is_numeric($_POST['product_id'])) {
				throw new Exception(__('Invalid product ID.', 'jigoshop'));
			}
			if (!isset($_POST['attribute_id']) || empty($_POST['attribute_id'])) {
				throw new Exception(__('Attribute was not specified.', 'jigoshop'));
			}
			if (!is_numeric($_POST['attribute_id'])) {
				throw new Exception(__('Invalid attribute ID.', 'jigoshop'));
			}

			$product = $this->productService->find((int)$_POST['product_id']);

			if (!$product->getId()) {
				throw new Exception(__('Product does not exists.', 'jigoshop'));
			}

			if ($product->hasAttribute((int)$_POST['attribute_id'])) {
				$attribute = $product->removeAttribute((int)$_POST['attribute_id']);
			} else {
				$attribute = $this->productService->getAttribute((int)$_POST['attribute_id']);
			}

			if ($attribute === null) {
				throw new Exception(__('Attribute does not exists.', 'jigoshop'));
			}

			if (isset($_POST['value'])) {
				$attribute->setValue(trim(htmlspecialchars(strip_tags($_POST['value']))));
			}

			$product->addAttribute($attribute);
			$this->productService->save($product);
			echo json_encode(array(
				'success' => true,
				'html' => Render::get('admin/product/box/attributes/attribute', array('attribute' => $attribute)),
			));
		} catch(Exception $e) {
			echo json_encode(array(
				'success' => false,
				'error' => $e->getMessage(),
			));
		}

		exit;
	}

	public function ajaxFindProduct()
	{
		// TODO: Add invalid data protection.
		$products = $this->productService->findLike($_POST['product']);

		$result = array(
			'success' => true,
			'results' => array_map(function($item){
				/** @var $item Product */
				return array(
					'id' => $item->getId(),
					'text' => $item->getName(),
				);
			}, $products),
		);

		echo json_encode($result);
		exit;
	}
}
