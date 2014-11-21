<?php
use Jigoshop\Admin\Helper\Forms;
use Jigoshop\Entity\Product\Attribute;
use Jigoshop\Helper\Product as ProductHelper;

/**
 * @var $variation \Jigoshop\Entity\Product\Variable\Variation Variation to display.
 * @var $attributes array List of attributes for variation.
 * @var $allowedSubtypes array List of types allowed as variations.
 */
$product = $variation->getProduct();
?>
<li class="list-group-item" data-id="<?php echo $variation->getId(); ?>">
	<h4 class="list-group-item-heading">
		<button type="button" class="remove-variation btn btn-default pull-right" title="<?php _e('Remove', 'jigoshop'); ?>"><span class="glyphicon glyphicon-remove"></span></button>
		<button type="button" class="show-variation btn btn-default pull-right" title="<?php _e('Show', 'jigoshop'); ?>"><span class="glyphicon glyphicon-collapse-down"></span></button>
		<?php foreach($attributes as $attribute): /** @var $attribute Attribute */?>
			<?php Forms::select(array(
				'name' => 'product[variation]['.$variation->getId().'][attribute]['.$attribute->getId().']',
				'classes' => array('variation-attribute'),
				'placeholder' => $attribute->getLabel(),
				'value' => $variation->getAttribute($attribute->getId())->getValue(),
				'options' => ProductHelper::getSelectOption($attribute->getOptions(), sprintf(__('Any of %s', 'jigoshop'), $attribute->getLabel())),
				'size' => 12,
			)); ?>
		<?php endforeach; ?>
	</h4>
	<div class="list-group-item-text clearfix">
		<?php Forms::select(array(
			'name' => 'product[variation]['.$variation->getId().'][product][type]',
			'label' => __('Type', 'jigoshop'),
			'value' => $product->getType(),
			'options' => $allowedSubtypes,
		)); ?>
		<?php Forms::text(array(
			'name' => 'product[variation]['.$variation->getId().'][product][regular_price]',
			'label' => __('Price', 'jigoshop'),
			'value' => $product->getPrice(),
		)); ?>
		<?php Forms::text(array(
			'name' => 'product[variation]['.$variation->getId().'][product][sku]',
			'label' => __('SKU', 'jigoshop'),
			'value' => $product->getSku(),
			'placeholder' => $variation->getParent()->getId().' - '.$variation->getId(),
		)); ?>
		<?php Forms::text(array(
			'name' => 'product[variation]['.$variation->getId().'][product][stock][stock]',
			'label' => __('Stock', 'jigoshop'),
			'value' => $product->getStock()->getStock(),
		)); ?>
		<?php Forms::text(array(
			'name' => 'product[variation]['.$variation->getId().'][product][sales][price]',
			'label' => __('Sale price', 'jigoshop'),
			'value' => $product->getSales()->getPrice(),
			'placeholder' => ProductHelper::formatNumericPrice(0),
		)); ?>
	</div>
</li>
