<?php

namespace Jigoshop\Service;

use Jigoshop\Core\Types;
use Jigoshop\Entity\EntityInterface;
use Jigoshop\Entity\Product\Attributes\Attribute;
use Jigoshop\Exception;
use Jigoshop\Factory\Product as ProductFactory;
use WPAL\Wordpress;

/**
 * Product service.
 *
 * @package Jigoshop\Service
 * @author Amadeusz Starzykiewicz
 */
class Product implements ProductServiceInterface
{
	/** @var \WPAL\Wordpress */
	private $wp;
	/** @var \Jigoshop\Factory\Product */
	private $factory;

	public function __construct(Wordpress $wp, ProductFactory $factory)
	{
		$this->wp = $wp;
		$this->factory = $factory;
		$wp->addAction('save_post_'.Types\Product::NAME, array($this, 'savePost'), 10);
	}

	/**
	 * Adds new type to managed types.
	 *
	 * @param $type string Unique type name.
	 * @param $class string Class name.
	 * @throws \Jigoshop\Exception When type already exists.
	 */
	public function addType($type, $class)
	{
		$this->factory->addType($type, $class);
	}

	/**
	 * Finds product specified by ID.
	 *
	 * @param $id int Product ID.
	 * @return \Jigoshop\Entity\Product
	 */
	public function find($id)
	{
		$post = null;

		if ($id !== null) {
			$post = $this->wp->getPost($id);
		}

		return $this->factory->fetch($post);
	}

	/**
	 * Finds item for specified WordPress post.
	 *
	 * @param $post \WP_Post WordPress post.
	 * @return Product Item found.
	 */
	public function findForPost($post)
	{
		return $this->factory->fetch($post);
	}

	/**
	 * Finds item specified by state.
	 *
	 * @param array $state State of the product to be found.
	 * @return \Jigoshop\Entity\Product Item found.
	 */
	public function findForState(array $state)
	{
		$post = $this->wp->getPost($state['id']);
		$product = $this->factory->fetch($post);
		// TODO: Restore state if needed
		return $product;
	}

	/**
	 * Finds items by trying to match their name.
	 *
	 * @param $name string Post name to match.
	 * @return array List of matched products.
	 */
	public function findLike($name)
	{
		$query = new \WP_Query(array(
			'post_type' => Types::PRODUCT,
			's' => $name,
		));

		return $this->findByQuery($query);
	}

	/**
	 * Finds items specified using WordPress query.
	 * TODO: Replace \WP_Query in order to make Jigoshop testable
	 *
	 * @param $query \WP_Query WordPress query.
	 * @return array Collection of found items.
	 */
	public function findByQuery($query)
	{
		$results = $query->get_posts();
		$products = array();

		// TODO: Maybe it is good to optimize this to fetch all found products data at once?
		foreach ($results as $product) {
			$products[] = $this->findForPost($product);
		}

		return $products;
	}

	/**
	 * Saves product to database.
	 *
	 * @param \Jigoshop\Entity\EntityInterface $object Product to save.
	 * @throws Exception
	 */
	public function save(EntityInterface $object)
	{
		if (!($object instanceof \Jigoshop\Entity\Product)) {
			throw new Exception('Trying to save not a product!');
		}

		$fields = $object->getStateToSave();

		if (isset($fields['id']) || isset($fields['name']) || isset($fields['description'])) {
			// We do not need to save ID, name and description (excerpt) as they are saved by WordPress itself.
			unset($fields['id'], $fields['name'], $fields['description']);
		}

		if (isset($fields['attributes'])) {
			$this->_removeAllProductAttributesExcept($object->getId(), array_map(function($item){
				return $item->getId();
			}, $fields['attributes']));

			foreach ($fields['attributes'] as $attribute) {
				$this->_saveProductAttribute($object, $attribute);
			}

			unset($fields['attributes']);
		}

		foreach ($fields as $field => $value) {
			$this->wp->updatePostMeta($object->getId(), $field, $value);
		}
	}

	/**
	 * @param $productId int Product ID.
	 * @param $ids array List of existing attribute IDs.
	 */
	private function _removeAllProductAttributesExcept($productId, $ids)
	{
		$wpdb = $this->wp->getWPDB();
		$ids = join(',', array_filter(array_map(function($item){ return (int)$item; }, $ids)));
		// Support for removing all items
		if (empty($ids)) {
			$ids = '0';
		}
		$query = $wpdb->prepare("DELETE FROM {$wpdb->prefix}jigoshop_product_attribute WHERE attribute_id NOT IN ({$ids}) AND product_id = %d", array($productId));
		$wpdb->query($query);
	}

	/**
	 * @param $object \Jigoshop\Entity\Product
	 * @param $attribute Attribute
	 */
	private function _saveProductAttribute($object, $attribute)
	{
		$wpdb = $this->wp->getWPDB();

		$value = $attribute->getValue();
		if (is_array($value)) {
			$value = join('|', $value);
		}

		$data = array(
			'product_id' => $object->getId(),
			'attribute_id' => $attribute->getId(),
			'value' => $value,
		);

		$wpdb->replace($wpdb->prefix.'jigoshop_product_attribute', $data);
	}

	/**
	 * @return array List of products that are out of stock.
	 */
	public function findOutOfStock()
	{
		// TODO: Replace \WP_Query in order to make Jigoshop testable
		$query = new \WP_Query(array(
			'post_type' => Types::PRODUCT,
			'post_status' => 'publish',
			'ignore_sticky_posts' => 1,
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => 'stock_manage',
					'value' => 1,
					'compare' => '=',
				),
				array(
					'key' => 'stock_stock',
					'value' => 0,
					'compare' => '=',
				),
			),
		));

		return $this->findByQuery($query);
	}

	/**
	 * @param $threshold int Threshold where to assume product is low in stock.
	 * @return array List of products that are low in stock.
	 */
	public function findLowStock($threshold)
	{
		// TODO: Replace \WP_Query in order to make Jigoshop testable
		$query = new \WP_Query(array(
			'post_type' => Types::PRODUCT,
			'post_status' => 'publish',
			'ignore_sticky_posts' => 1,
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => 'stock_manage',
					'value' => 1,
					'compare' => '=',
				),
				array(
					'key' => 'stock_stock',
					'value' => $threshold,
					'compare' => '<',
				),
			),
		));

		return $this->findByQuery($query);
	}

	/**
	 * Save the product data upon post saving.
	 *
	 * @param $id int Post ID.
	 */
	public function savePost($id)
	{
		$product = $this->factory->create($id);
		$this->save($product);
	}

	/**
	 * @param \Jigoshop\Entity\Product $product Product to find thumbnails for.
	 * @return array List of thumbnails attached to the product.
	 */
	public function getThumbnails(\Jigoshop\Entity\Product $product)
	{
		$query = new \WP_Query();
		$args = array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'orderby' => 'menu_order',
			'order' => 'asc',
			'numberposts' => -1,
			'post_status' => 'inherit',
			'post_parent' => $product->getId(),
			'suppress_filters' => true,
			'post__not_in' => array($this->wp->getPostThumbnailId($product->getId())),
		);

		$thumbnails = array();
		foreach ($query->query($args) as $thumbnail) {
			$thumbnails[$thumbnail->ID] = array(
				'title' => $thumbnail->post_title,
				'url' => $this->wp->wpGetAttachmentUrl($thumbnail->ID),
				'image' => $this->wp->wpGetAttachmentImage($thumbnail->ID, 'shop_thumbnail'),
			);
		}

		return $thumbnails;
	}

	/**
	 * Finds and returns list of available attributes.
	 *
	 * @return array List of available product attributes
	 */
	public function findAllAttributes()
	{
		$wpdb = $this->wp->getWPDB();
		$query = "
		SELECT a.id, a.is_local, a.slug, a.label, a.type, ao.id AS option_id, ao.value AS option_value, ao.label as option_label
		FROM {$wpdb->prefix}jigoshop_attribute a
			LEFT JOIN {$wpdb->prefix}jigoshop_attribute_option ao ON a.id = ao.attribute_id
			WHERE a.is_local = 0
		";
		$results = $wpdb->get_results($query, ARRAY_A);
		$attributes = array();

		for ($i = 0, $endI = count($results); $i < $endI;) {
			$attribute = new Attribute();
			$attribute->setId((int)$results[$i]['id']);
			$attribute->setSlug($results[$i]['slug']);
			$attribute->setLabel($results[$i]['label']);
			$attribute->setType((int)$results[$i]['type']);
			$attribute->setLocal((bool)$results[$i]['is_local']);

			while ($i < $endI && $results[$i]['id'] == $attribute->getId()) {
				if ($results[$i]['option_id'] !== null) {
					$option = new Attribute\Option();
					$option->setId($results[$i]['option_id']);
					$option->setLabel($results[$i]['option_label']);
					$option->setValue($results[$i]['option_value']);
					$option->setAttribute($attribute);
					$attribute->addOption($option);
				}
				$i++;
			}

			$attributes[$attribute->getId()] = $attribute;
		}

		return $attributes;
	}

	/**
	 * Finds and returns list of attributes associated with selected product by it's ID.
	 *
	 * @param $productId int Product ID.
	 * @return array List of attributes attached to selected product.
	 */
	public function getAttributes($productId)
	{
		return $this->factory->getAttributes($productId);
	}

	/**
	 * Finds attribute for selected ID.
	 *
	 * If attribute is not found - returns null.
	 *
	 * @param int $id Attribute ID.
	 * @return Attribute
	 */
	public function getAttribute($id)
	{
		return $this->factory->getAttribute($id);
	}

	/**
	 * Saves attribute to database.
	 *
	 * @param Attribute $attribute Attribute to save.
	 * @return \Jigoshop\Entity\Product\Attributes\Attribute Saved attribute.
	 */
	public function saveAttribute(Attribute $attribute)
	{
		$wpdb = $this->wp->getWPDB();
		$data = array(
			'label' => $attribute->getLabel(),
			'slug' => $attribute->getSlug(),
			'type' => $attribute->getType(),
			'is_local' => $attribute->isLocal(),
		);

		if ($attribute->getId()) {
			$wpdb->update($wpdb->prefix.'jigoshop_attribute', $data, array('id' => $attribute->getId()));
		} else {
			$wpdb->insert($wpdb->prefix.'jigoshop_attribute', $data);
			$attribute->setId($wpdb->insert_id);
		}

		$this->removeAllAttributesExcept(array_map(function($item){
			return $item->getId();
		}, $attribute->getOptions()));

		foreach ($attribute->getOptions() as $option) {
			/** @var $option Attribute\Option */
			$data = array(
				'attribute_id' => $option->getAttribute()->getId(),
				'label' => $option->getLabel(),
				'value' => $option->getValue(),
			);
			if ($option->getId()) {
				$wpdb->update($wpdb->prefix.'jigoshop_attribute_option', $data, array('id' => $option->getId()));
			} else {
				$wpdb->insert($wpdb->prefix.'jigoshop_attribute_option', $data);
				$option->setId($wpdb->insert_id);
			}
		}

		return $attribute;
	}

	/**
	 * @param $ids array IDs to preserve.
	 */
	private function removeAllAttributesExcept($ids)
	{
		$wpdb = $this->wp->getWPDB();
		$ids = join(',', array_filter(array_map(function($item){ return (int)$item; }, $ids)));
		// Support for removing all items
		if (empty($ids)) {
			$ids = '0';
		}
		$query = "DELETE FROM {$wpdb->prefix}jigoshop_attribute_option WHERE id NOT IN ({$ids})";
		$wpdb->query($query);
	}

	/**
	 * Removes attribute from database.
	 *
	 * @param int $id Attribute ID.
	 */
	public function removeAttribute($id)
	{
		$wpdb = $this->wp->getWPDB();
		$wpdb->delete($wpdb->prefix.'jigoshop_attribute', array('id' => $id));
	}
}
