<?php

namespace Jigoshop\Core\Types\Product;

use Jigoshop\Admin\Helper\Forms;
use Jigoshop\Entity\Order;
use Jigoshop\Entity\Order\Item;
use Jigoshop\Entity\Product;
use Jigoshop\Entity\Product\Attribute;
use Jigoshop\Exception;
use Jigoshop\Factory\Product\Variable as VariableFactory;
use Jigoshop\Helper\Render;
use Jigoshop\Helper\Scripts;
use Jigoshop\Helper\Styles;
use Jigoshop\Service\Product\Variable as VariableService;
use Jigoshop\Service\ProductServiceInterface;
use WPAL\Wordpress;

/**
 * Variable product type definition.
 *
 * @package Jigoshop\Core\Types\Product
 */
class Variable implements Type
{
	const TYPE = 'product_variation';

	/** @var Wordpress */
	private $wp;
	/** @var VariableService */
	private $service;
	/** @var VariableFactory */
	private $factory;
	/** @var ProductServiceInterface */
	private $productService;
	/** @var array */
	private $allowedSubtypes = array();

	public function __construct(Wordpress $wp, ProductServiceInterface $productService, VariableService $service, VariableFactory $factory)
	{
		$this->wp = $wp;
		$this->productService = $productService;
		$this->service = $service;
		$this->factory = $factory;
	}

	/**
	 * Returns identifier for the type.
	 *
	 * @return string Type identifier.
	 */
	public function getId()
	{
		return Product\Variable::TYPE;
	}

	/**
	 * Returns human-readable name for the type.
	 *
	 * @return string Type name.
	 */
	public function getName()
	{
		return __('Variable', 'jigoshop');
	}

	/**
	 * Returns class name to use as type entity.
	 * This class MUST extend {@code \Jigoshop\Entity\Product}!
	 *
	 * @return string Fully qualified class name.
	 */
	public function getClass()
	{
		return '\Jigoshop\Entity\Product\Variable';
	}

	/**
	 * @return array
	 */
	public function getAllowedSubtypes()
	{
		return $this->allowedSubtypes;
	}

	/**
	 * Initializes product type.
	 *
	 * @param Wordpress $wp WordPress Abstraction Layer
	 * @param array $enabledTypes List of all available types.
	 */
	public function initialize(Wordpress $wp, array $enabledTypes)
	{
		$wp->addFilter('jigoshop\cart\add', array($this, 'addToCart'), 10, 2);
		$wp->addFilter('jigoshop\cart\generate_item_key', array($this, 'generateItemKey'), 10, 2);
		$wp->addFilter('jigoshop\checkout\is_shipping_required', array($this, 'isShippingRequired'), 10, 2);
		$wp->addAction('jigoshop\product\assets', array($this, 'addFrontendAssets'), 10, 3);

		$wp->addAction('jigoshop\admin\product\assets', array($this, 'addAdminAssets'), 10, 3);
		$wp->addAction('jigoshop\admin\product\attribute\options', array($this, 'addVariableAttributeOptions'));
		$wp->addFilter('jigoshop\admin\product\menu', array($this, 'addProductMenu'));
		$wp->addFilter('jigoshop\admin\product\tabs', array($this, 'addProductTab'), 10, 2);

		$wp->addAction('wp_ajax_jigoshop.admin.product.add_variation', array($this, 'ajaxAddVariation'), 10, 0);
		$wp->addAction('wp_ajax_jigoshop.admin.product.save_variation', array($this, 'ajaxSaveVariation'), 10, 0);
		$wp->addAction('wp_ajax_jigoshop.admin.product.remove_variation', array($this, 'ajaxRemoveVariation'), 10, 0);

		$allowedSubtypes = $wp->applyFilters('jigoshop\core\types\variable\subtypes', array(
			Product\Simple::TYPE,
		));
		$this->allowedSubtypes = array_filter($enabledTypes, function($type) use ($allowedSubtypes){
			/** @var $type Type */
			return in_array($type->getId(), $allowedSubtypes);
		});

		// TODO: Move this to Installer class (somehow).
		$this->createTables();
	}

	/**
	 * @param $status boolean
	 * @param $item Item
	 * @return boolean
	 */
	public function isShippingRequired($status, $item)
	{
		if ($status) {
			return true;
		}

		$product = $item->getProduct();
		if ($product instanceof Product\Variable) {
			$product = $product->getVariation($item->getMeta('variation_id')->getValue())->getProduct();

			if ($product instanceof Product\Shippable && $product->isShippable()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param $parts array
	 * @param $item Item
	 * @return array
	 */
	public function generateItemKey($parts, $item)
	{
		if ($item->getProduct() instanceof Product\Variable) {
			foreach ($item->getAllMeta() as $meta) {
				/** @var $meta Item\Meta */
				$parts[] = $meta->getValue();
			}
		}

		return $parts;
	}

	public function addToCart($value, $product)
	{
		if ($product instanceof Product\Variable) {
			$item = new Item();
			$item->setProduct($product);
			$item->setType($product->getType());

			$variation = $this->factory->getVariation($product, $_POST['variation_id']);

			foreach ($variation->getAttributes() as $attribute) {
				/** @var $attribute \Jigoshop\Entity\Product\Variable\Attribute */
				if ($attribute->getValue() === '') {
					$meta = new Item\Meta();
					$meta->setKey($attribute->getAttribute()->getSlug());
					$meta->setValue($_POST['attributes'][$attribute->getAttribute()->getId()]);
					$item->addMeta($meta);
				}
			}

			$item->setName($variation->getTitle());
			$item->setPrice($variation->getProduct()->getPrice());
			$item->setQuantity($_POST['quantity']);

			$meta = new Item\Meta();
			$meta->setKey('variation_id');
			$meta->setValue($variation->getId());
			$item->addMeta($meta);

			return $item;
		}

		return $value;
	}

	/**
	 * Adds variable options to attribute field.
	 *
	 * @param Attribute|Attribute\Variable $attribute Attribute.
	 */
	public function addVariableAttributeOptions(Attribute $attribute)
	{
		if ($attribute instanceof Attribute\Variable) {
			/** @var $attribute Attribute|Attribute\Variable */
			Forms::checkbox(array(
				'name' => 'product[attributes]['.$attribute->getId().'][is_variable]',
				'id' => 'product_attributes_'.$attribute->getId().'_variable',
				'classes' => array('attribute-options'),
				'label' => __('Is for variations?', 'jigoshop'),
				'checked' => $attribute->isVariable(),
				'size' => 6,
				// TODO: Visibility based on current product - if not variable should be hidden
			));
		}
	}

	/**
	 * Updates product menu.
	 *
	 * @param $menu array
	 * @return array
	 */
	public function addProductMenu($menu)
	{
		$menu['variations'] = array('label' => __('Variations', 'jigoshop'), 'visible' => array(Product\Variable::TYPE));
		$menu['sales']['visible'][] = Product\Variable::TYPE;
		return $menu;
	}

	/**
	 * Updates product tabs.
	 *
	 * @param $tabs array
	 * @param $product Product
	 * @return array
	 */
	public function addProductTab($tabs, $product)
	{
		$types = array();
		foreach ($this->allowedSubtypes as $type) {
			/** @var $type Type */
			$types[$type->getId()] = $type->getName();
		}

		$tabs['variations'] = array(
			'product' => $product,
			'allowedSubtypes' => $types,
		);
		return $tabs;
	}

	/**
	 * @param Wordpress $wp
	 * @param Styles $styles
	 * @param Scripts $scripts
	 */
	public function addAdminAssets(Wordpress $wp, Styles $styles, Scripts $scripts)
	{
		$styles->add('jigoshop.admin.product.variable', JIGOSHOP_URL.'/assets/css/admin/product/variable.css');
		$scripts->add('jigoshop.admin.product.variable', JIGOSHOP_URL.'/assets/js/admin/product/variable.js', array('jquery'));
		$scripts->localize('jigoshop.admin.product.variable', 'jigoshop_admin_product_variable', array(
			'ajax' => $wp->getAjaxUrl(),
			'i18n' => array(
				'confirm_remove' => __('Are you sure?', 'jigoshop'),
				'variation_removed' => __('Variation successfully removed.', 'jigoshop'),
				'saved' => __('Variation saved.', 'jigoshop'),
			),
		));
	}

	/**
	 * @param Wordpress $wp
	 * @param Styles $styles
	 * @param Scripts $scripts
	 */
	public function addFrontendAssets(Wordpress $wp, Styles $styles, Scripts $scripts)
	{
		$post = $wp->getGlobalPost();
		$product = $this->productService->findForPost($post);

		// TODO: Cache $attributes somewhere
		if ($product instanceof Product\Variable) {
			$variations = array();
			foreach ($product->getVariations() as $variation) {
				/** @var $variation Product\Variable\Variation */
				$variations[$variation->getId()] = array(
					'price' => $variation->getProduct()->getPrice(),
					'html' => array(
						'price' => \Jigoshop\Helper\Product::formatPrice($variation->getProduct()->getPrice()),
					),
					'attributes' => array(),
				);
				foreach ($variation->getAttributes() as $attribute) {
					/** @var $attribute Product\Variable\Attribute */
					$variations[$variation->getId()]['attributes'][$attribute->getAttribute()->getId()] = $attribute->getValue();
				}
			}

			$styles->add('jigoshop.product.variable', JIGOSHOP_URL.'/assets/css/shop/product/variable.css');
			$scripts->add('jigoshop.product.variable', JIGOSHOP_URL.'/assets/js/shop/product/variable.js', array('jquery'));
			$scripts->localize('jigoshop.product.variable', 'jigoshop_product_variable', array(
				'ajax' => $wp->getAjaxUrl(),
				'variations' => $variations,
			));
		}
	}

	private function createTables()
	{
		$wpdb = $this->wp->getWPDB();
		$wpdb->hide_errors();

		$collate = '';
		if ($wpdb->has_cap('collation')) {
			if (!empty($wpdb->charset)) {
				$collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
			}
			if (!empty($wpdb->collate)) {
				$collate .= " COLLATE {$wpdb->collate}";
			}
		}

		$query = "
			CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jigoshop_product_variation (
				id INT(9) NOT NULL AUTO_INCREMENT,
				parent_id BIGINT UNSIGNED NOT NULL,
				product_id BIGINT UNSIGNED NOT NULL,
				PRIMARY KEY id (id),
				FOREIGN KEY parent (parent_id) REFERENCES {$wpdb->posts} (ID) ON DELETE CASCADE,
				FOREIGN KEY product (product_id) REFERENCES {$wpdb->posts} (ID) ON DELETE CASCADE
			) {$collate};
		";
		$wpdb->query($query);
		$query = "
			CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jigoshop_product_variation_attribute (
				variation_id INT(9) NOT NULL,
				attribute_id INT(9) NOT NULL,
				value VARCHAR(255),
				PRIMARY KEY id (variation_id, attribute_id),
				FOREIGN KEY variation (variation_id) REFERENCES {$wpdb->prefix}jigoshop_product_variation (id) ON DELETE CASCADE,
				FOREIGN KEY attribute (attribute_id) REFERENCES {$wpdb->prefix}jigoshop_attribute (id) ON DELETE CASCADE
			) {$collate};
		";
		$wpdb->query($query);
		$wpdb->show_errors();
	}

	public function ajaxAddVariation()
	{
		try {
			if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
				throw new Exception(__('Product was not specified.', 'jigoshop'));
			}
			if (!is_numeric($_POST['product_id'])) {
				throw new Exception(__('Invalid product ID.', 'jigoshop'));
			}

			$product = $this->productService->find((int)$_POST['product_id']);

			if (!$product->getId()) {
				throw new Exception(__('Product does not exists.', 'jigoshop'));
			}

			if (!($product instanceof Product\Variable)) {
				throw new Exception(__('Product is not variable - unable to add variation.', 'jigoshop'));
			}

			$variation = $this->factory->createVariation($product);
			$this->wp->doAction('jigoshop\admin\product_variation\add', $variation);

			$product->addVariation($variation);
			$this->productService->save($product);

			$types = array();
			foreach ($this->allowedSubtypes as $type) {
				/** @var $type Type */
				$types[$type->getId()] = $type->getName();
			}

			echo json_encode(array(
				'success' => true,
				'html' => Render::get('admin/product/box/variations/variation', array(
					'variation' => $variation,
					'attributes' => $product->getVariableAttributes(),
					'allowedSubtypes' => $types,
				)),
			));
		} catch(Exception $e) {
			echo json_encode(array(
				'success' => false,
				'error' => $e->getMessage(),
			));
		}

		exit;
	}

	public function ajaxSaveVariation()
	{
		try {
			if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
				throw new Exception(__('Product was not specified.', 'jigoshop'));
			}
			if (!is_numeric($_POST['product_id'])) {
				throw new Exception(__('Invalid product ID.', 'jigoshop'));
			}
			if (!isset($_POST['variation_id']) || empty($_POST['variation_id'])) {
				throw new Exception(__('Variation was not specified.', 'jigoshop'));
			}
			if (!is_numeric($_POST['variation_id'])) {
				throw new Exception(__('Invalid variation ID.', 'jigoshop'));
			}

			if (!isset($_POST['attributes']) || !is_array($_POST['attributes'])) {
				throw new Exception(__('Attribute values are not specified.', 'jigoshop'));
			}

			$product = $this->productService->find((int)$_POST['product_id']);

			if (!$product->getId()) {
				throw new Exception(__('Product does not exists.', 'jigoshop'));
			}

			if (!($product instanceof Product\Variable)) {
				throw new Exception(__('Product is not variable - unable to add variation.', 'jigoshop'));
			}

			if (!$product->hasVariation((int)$_POST['variation_id'])) {
				throw new Exception(__('Variation does not exists.', 'jigoshop'));
			}

			$variation = $product->removeVariation((int)$_POST['variation_id']);
			foreach ($_POST['attributes'] as $attribute => $value) {
				$variation->getAttribute($attribute)->setValue(trim(htmlspecialchars(strip_tags($value))));
			}

			if (isset($_POST['product']) && is_array($_POST['product'])) {
				// For now - always manage variation product stock
				$_POST['product']['stock']['manage'] = 'on';
				$variation->getProduct()->restoreState($_POST['product']);
				$variation->getProduct()->markAsDirty($_POST['product']);
			}

			$this->wp->doAction('jigoshop\admin\product_variation\save', $variation);

			$product->addVariation($variation);
			$this->productService->save($product);

			$types = array();
			foreach ($this->allowedSubtypes as $type) {
				/** @var $type Type */
				$types[$type->getId()] = $type->getName();
			}

			echo json_encode(array(
				'success' => true,
				'html' => Render::get('admin/product/box/variations/variation', array(
					'variation' => $variation,
					'attributes' => $product->getVariableAttributes(),
					'allowedSubtypes' => $types,
				)),
			));
		} catch(Exception $e) {
			echo json_encode(array(
				'success' => false,
				'error' => $e->getMessage(),
			));
		}

		exit;
	}

	public function ajaxRemoveVariation()
	{
		try {
			if (!isset($_POST['product_id']) || empty($_POST['product_id'])) {
				throw new Exception(__('Product was not specified.', 'jigoshop'));
			}
			if (!is_numeric($_POST['product_id'])) {
				throw new Exception(__('Invalid product ID.', 'jigoshop'));
			}
			if (!isset($_POST['variation_id']) || empty($_POST['variation_id'])) {
				throw new Exception(__('Variation was not specified.', 'jigoshop'));
			}
			if (!is_numeric($_POST['variation_id'])) {
				throw new Exception(__('Invalid variation ID.', 'jigoshop'));
			}

			$product = $this->productService->find((int)$_POST['product_id']);

			if (!$product->getId()) {
				throw new Exception(__('Product does not exists.', 'jigoshop'));
			}

			if (!($product instanceof Product\Variable)) {
				throw new Exception(__('Product is not variable - unable to add variation.', 'jigoshop'));
			}

			$variation = $product->removeVariation((int)$_POST['variation_id']);
			$this->service->removeVariation($variation);
			$this->productService->save($product);
			echo json_encode(array(
				'success' => true,
			));
		} catch(Exception $e) {
			echo json_encode(array(
				'success' => false,
				'error' => $e->getMessage(),
			));
		}

		exit;
	}
}
