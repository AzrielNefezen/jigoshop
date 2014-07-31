<?php
use Jigoshop\Entity\Product;
use Jigoshop\Helper\Forms;
use Jigoshop\Helper\Product as ProductHelper;

/**
 * @var $product Product The product.
 */
?>
<fieldset>
	<?php
	Forms::text(array(
		'name' => 'product[regular_price]',
		'label' => __('Price', 'jigoshop').' ('.ProductHelper::currencySymbol().')',
		'value' => $product->getRegularPrice(),
	));
	Forms::text(array(
		'name' => 'product[sku]',
		'label' => __('SKU', 'jigoshop'),
		'value' => $product->getSku(),
		'placeholder' => $product->getId(),
	));
	?>
</fieldset>
<fieldset>
	<?php
	Forms::text(array(
		'name' => 'product[size][weight]',
		'label' => __('Weight', 'jigoshop').' ('.ProductHelper::weightUnit().')',
		'value' => $product->getSize()->getWeight(),
	));
	Forms::text(array(
		'name' => 'product[size][length]',
		'label' => __('Length', 'jigoshop').' ('.ProductHelper::dimensionsUnit().')',
		'value' => $product->getSize()->getLength(),
	));
	Forms::text(array(
		'name' => 'product[size][width]',
		'label' => __('Width', 'jigoshop').' ('.ProductHelper::dimensionsUnit().')',
		'value' => $product->getSize()->getWidth(),
	));
	Forms::text(array(
		'name' => 'product[size][height]',
		'label' => __('Height', 'jigoshop').' ('.ProductHelper::dimensionsUnit().')',
		'value' => $product->getSize()->getHeight(),
	));
	?>
</fieldset>
<fieldset>
	<?php
	Forms::select(array(
		'name' => 'product[visibility]',
		'label' => __('Visibility', 'jigoshop'),
		'options' => array(
			Product::VISIBILITY_PUBLIC => __('Catalog & Search', 'jigoshop'),
			Product::VISIBILITY_CATALOG => __('Catalog Only', 'jigoshop'),
			Product::VISIBILITY_SEARCH => __('Search Only', 'jigoshop'),
			Product::VISIBILITY_NONE => __('Hidden', 'jigoshop')
		),
		'value' => $product->getVisibility(),
	));
	Forms::checkbox(array(
		'name' => 'product[featured]',
		'label' => __('Featured?', 'jigoshop'),
		'value' => $product->isFeatured(),
		'description' => __('Enable this option to feature this product', 'jigoshop'),
	));
	?>
</fieldset>
<?php do_action('jigoshop\product\tabs\general'); ?>
