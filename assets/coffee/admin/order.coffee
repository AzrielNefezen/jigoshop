class AdminOrder
  params:
    ajax: ''
    tax_shipping: false
    ship_to_billing: false

  constructor: (@params) ->
    @newItemSelect()
    @_prepareStateField("#order_billing_address_state")
    @_prepareStateField("#order_shipping_address_state")

    jQuery('#add-item').on 'click', @newItemClick
    jQuery('.jigoshop-order table').on 'click', 'a.remove', @removeItemClick
    jQuery('.jigoshop-order table').on 'change', '.price input, .quantity input', @updateItem
    jQuery('.jigoshop-data')
      .on 'change', "#order_billing_address_country", @updateCountry
      .on 'change', "#order_shipping_address_country", @updateCountry
      .on 'change', '#order_billing_address_state', @updateState
      .on 'change', '#order_shipping_address_state', @updateState
      .on 'change', '#order_billing_address_postcode', @updatePostcode
      .on 'change', '#order_shipping_address_postcode', @updatePostcode

    jQuery('.jigoshop-totals')
      .on 'click', 'input[type=radio]', @selectShipping

  selectShipping: (e) =>
    $parent = jQuery(e.target).closest('div.jigoshop')
    $method = jQuery(e.target)
    $rate = jQuery('.shipping-method-rate', $method.closest('li'))

    jQuery.ajax(@params.ajax,
      type: 'post'
      dataType: 'json'
      data:
        action: 'jigoshop.admin.order.change_shipping_method'
        order: $parent.data('order')
        method: $method.val()
        rate: $rate.val()
    )
    .done (result) =>
      if result.success? and result.success
        @_updateTotals(result.html.total, result.html.subtotal)
        @_updateTaxes(result.tax, result.html.tax)
      else
        # TODO: It would be nice to have kind of helper for error messages
        alert result.error

  newItemSelect: =>
    jQuery('#new-item').select2
      minimumInputLength: 3
      ajax:
        url: @params.ajax
        type: 'post'
        dataType: 'json'
        data: (term) ->
          return {
            query: term
            action: 'jigoshop.admin.product.find'
          }
        results: (data) ->
          if data.success?
            return results: data.results
          return results: []

  newItemClick: (e) =>
    e.preventDefault()
    value = jQuery('#new-item').val()
    if value == ''
      return false

    $parent = jQuery(e.target).closest('table')
    $existing = jQuery("tr[data-product=#{value}]", $parent)

    if $existing.length > 0
      $quantity = jQuery('.quantity input', $existing)
      $quantity.val(parseInt($quantity.val()) + 1).trigger('change')
      return

    jQuery.ajax
      url: @params.ajax
      type: 'post'
      dataType: 'json'
      data:
        action: 'jigoshop.admin.order.add_product'
        product: value
        order: $parent.data('order')
    .done (data) =>
      if data.success? and data.success
        jQuery(data.html.row).appendTo($parent)
        jQuery('#product-subtotal', $parent).html(data.html.product_subtotal)
        @_updateTotals(data.html.total, data.html.subtotal)
        @_updateTaxes(data.tax, data.html.tax)
        # TODO: Show shipping methods if required

  updateItem: (e) =>
    e.preventDefault()
    $row = jQuery(e.target).closest('tr')
    $parent = $row.closest('table')

    jQuery.ajax
      url: @params.ajax
      type: 'post'
      dataType: 'json'
      data:
        action: 'jigoshop.admin.order.update_product'
        product: $row.data('id')
        order: $parent.data('order')
        price: jQuery('.price input', $row).val()
        quantity: jQuery('.quantity input', $row).val()
    .done (data) =>
      if data.success? and data.success
        if data.item_cost > 0
          jQuery('.total p', $row).html(data.html.item_cost)
        else
          $row.remove()
        jQuery('#product-subtotal', $parent).html(data.html.product_subtotal)
        @_updateTotals(data.html.total, data.html.subtotal)
        @_updateTaxes(data.tax, data.html.tax)

  removeItemClick: (e) =>
    e.preventDefault()
    $row = jQuery(e.target).closest('tr')
    $parent = $row.closest('table')

    jQuery.ajax
      url: @params.ajax
      type: 'post'
      dataType: 'json'
      data:
        action: 'jigoshop.admin.order.remove_product'
        product: $row.data('id')
        order: $parent.data('order')
    .done (data) =>
      if data.success? and data.success
        $row.remove()
        jQuery('#product-subtotal', $parent).html(data.html.product_subtotal)
        @_updateTaxes(data.tax, data.html.tax)
        @_updateTotals(data.html.total, data.html.subtotal)

  updateCountry: (e) =>
    $target = jQuery(e.target)
    $parent = $target.closest('.jigoshop')
    id = $target.attr('id')
    type = id.replace(/order_/, '').replace(/_country/, '')

    jQuery.ajax(@params.ajax,
      type: 'post'
      dataType: 'json'
      data:
        action: 'jigoshop.admin.order.change_country'
        value: $target.val()
        order: $parent.data('order')
        type: type
    )
    .done (result) =>
      if result.success? and result.success
        @_updateTotals(result.html.total, result.html.subtotal)
        @_updateTaxes(result.tax, result.html.tax)
        @_updateShipping(result.shipping, result.html.shipping)

        fieldId = "#order_#{type}_state"
        $field = jQuery(fieldId)

        if result.has_states
          data = []
          for own state, label of result.states
            data.push
              id: state
              text: label
          $field.select2
            data: data
        else
          $field.attr('type', 'text').select2('destroy').val('')
      else
        addMessage('danger', result.error, 6000)

  updateState: (e) =>
    $target = jQuery(e.target)
    $parent = $target.closest('.jigoshop')
    id = $target.attr('id')
    type = id.replace(/order_/, '').replace(/_state/, '')

    jQuery.ajax(@params.ajax,
      type: 'post'
      dataType: 'json'
      data:
        action: 'jigoshop.admin.order.change_state'
        value: $target.val()
        order: $parent.data('order')
        type: type
    )
    .done (result) =>
      if result.success? and result.success
        @_updateTotals(result.html.total, result.html.subtotal)
        @_updateTaxes(result.tax, result.html.tax)
        @_updateShipping(result.shipping, result.html.shipping)
      else
        addMessage('danger', result.error, 6000)

  updatePostcode: (e) =>
    $target = jQuery(e.target)
    $parent = $target.closest('.jigoshop')
    id = $target.attr('id')
    type = id.replace(/order_/, '').replace(/_postcode/, '')

    jQuery.ajax(@params.ajax,
      type: 'post'
      dataType: 'json'
      data:
        action: 'jigoshop.admin.order.change_postcode'
        value: $target.val()
        order: $parent.data('order')
        type: type
    )
    .done (result) =>
      if result.success? and result.success
        @_updateTotals(result.html.total, result.html.subtotal)
        @_updateTaxes(result.tax, result.html.tax)
        @_updateShipping(result.shipping, result.html.shipping)
      else
        addMessage('danger', result.error, 6000)

  _updateTaxes: (taxes, html) ->
    for own taxClass, tax of html
      fieldClass = ".order_tax_#{taxClass}_field"
      $tax = jQuery(fieldClass)
      jQuery("label", $tax).html(tax.label)
      jQuery("p", $tax).html(tax.value).show()
      if taxes[taxClass] > 0
        $tax.show()
      else
        $tax.hide()

  _updateTotals: (total, subtotal) ->
    jQuery('#subtotal').html(subtotal)
    jQuery('#total').html(total)

  _updateShipping: (shipping, html) ->
    for own shippingClass, value of shipping
      $method = jQuery(".shipping-#{shippingClass}")
      $method.addClass('existing')
      if $method.length > 0
        if value > -1
          $item = jQuery(html[shippingClass].html).addClass('existing')
          $method.replaceWith($item)
        else
          $method.slideUp -> jQuery(this).remove()
      else if html[shippingClass]?
        $item = jQuery(html[shippingClass].html)
        $item.hide().addClass('existing').appendTo(jQuery('#shipping-methods')).slideDown()
    # Remove non-existent methods
    jQuery('#shipping-methods > li:not(.existing)').slideUp -> jQuery(this).remove()
    jQuery('#shipping-methods > li').removeClass('existing')

  _prepareStateField: (id) ->
    $field = jQuery(id)
    if !$field.is('select')
      return
    $replacement = jQuery(document.createElement('input'))
      .attr('type', 'text')
      .attr('id', $field.attr('id'))
      .attr('name', $field.attr('name'))
      .attr('class', $field.attr('class'))
      .val($field.val())
    data = []
    jQuery('option', $field).each ->
      data.push
        id: jQuery(this).val()
        text: jQuery(this).html()
    $field.replaceWith($replacement)
    $replacement.select2
      data: data

jQuery ->
  new AdminOrder(jigoshop_admin_order)
