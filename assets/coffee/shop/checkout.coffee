class Checkout
  params:
    ajax: ''
    assets: ''
    i18n:
      loading: 'Loading...'

  constructor: (@params) ->
    @_prepareStateField("#jigoshop_order_billing_address_state")
    @_prepareStateField("#jigoshop_order_shipping_address_state")

    jQuery('#jigoshop-login').on 'click', (event) ->
      event.preventDefault()
      jQuery('#jigoshop-login-form').slideToggle()
    jQuery('#create-account').on 'change', ->
      jQuery('#registration-form').slideToggle()
    jQuery('#different_shipping_address').on 'change', ->
      jQuery('#shipping-address').slideToggle()
      if (jQuery(this).is(':checked'))
        jQuery('#jigoshop_order_shipping_address_country').change()
      else
        jQuery('#jigoshop_order_billing_address_country').change()
    jQuery('#payment-methods').on 'change', 'li input[type=radio]', ->
      jQuery('#payment-methods li > div').slideUp()
      jQuery('div', jQuery(this).closest('li')).slideDown()
    jQuery('#shipping_address-calculator')
      .on 'click', 'input[type=radio]', @selectShipping
    jQuery('#jigoshop_order_billing_address_country').on 'change', (event) =>
      @updateCountry('billing_address', event)
    jQuery('#jigoshop_order_shipping_address_country').on 'change', (event) =>
      @updateCountry('shipping_address', event)
    jQuery('#jigoshop_order_billing_address_state').on 'change', @updateState.bind(@,
      'billing_address')
    jQuery('#jigoshop_order_shipping_address_state').on 'change', @updateState.bind(@,
      'shipping_address')
    jQuery('#jigoshop_order_billing_address_postcode').on 'change', @updatePostcode.bind(@,
      'billing_address')
    jQuery('#jigoshop_order_shipping_address_postcode').on 'change', @updatePostcode.bind(@,
      'shipping_address')
    jQuery('#jigoshop_coupons')
      .on 'change', @updateDiscounts
      .select2
        tags: []
        tokenSeparators: [',']
        multiple: true
        formatNoMatches: ''

  # TODO: Copy shipping_address changing etc. here from Cart
    # TODO: Refactor Cart and Checkout (for sure) to create one place for many shared parameters and functions
  block: =>
    jQuery('#checkout > button').block
      message: '<img src="' + @params.assets + '/images/loading.gif" alt="' + @params.i18n.loading + '" />'
      css:
        padding: '20px'
        width: 'auto'
        height: 'auto'
        border: '1px solid #83AC31'
      overlayCss:
        opacity: 0.01

  unblock: ->
    jQuery('#checkout > button').unblock()

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

  selectShipping: =>
    $method = jQuery('#shipping_address-calculator input[type=radio]:checked')
    $rate = jQuery('.shipping_address-method-rate', $method.closest('li'))
    jQuery.ajax(@params.ajax,
      type: 'post'
      dataType: 'json'
      data:
        action: 'jigoshop_cart_select_shipping_address'
        method: $method.val()
        rate: $rate.val()
    )
    .done (result) =>
      if result.success
        @_updateTotals(result.html.total, result.html.subtotal)
        @_updateDiscount(result)
        @_updateTaxes(result.tax, result.html.tax)
      else
        addMessage('danger', result.error, 6000)

  updateCountry: (field, event) =>
    @block()
    jQuery('.noscript_state_field').remove()
    jQuery.ajax(@params.ajax,
      type: 'post'
      dataType: 'json'
      data:
        action: 'jigoshop_checkout_change_country'
        field: field
        differentShipping: jQuery('#different_shipping_address').is(':checked')
        value: jQuery(event.target).val()
    )
    .done (result) =>
      if result.success? and result.success
        @_updateTotals(result.html.total, result.html.subtotal)
        @_updateDiscount(result)
        @_updateTaxes(result.tax, result.html.tax)
        @_updateShipping(result.shipping_address, result.html.shipping_address)
        stateClass = '#' + jQuery(event.target).attr('id').replace(/country/, 'state')

        if result.has_states
          data = []
          for own state, label of result.states
            data.push
              id: state
              text: label
          jQuery(stateClass).select2
            data: data
        else
          jQuery(stateClass).attr('type', 'text').select2('destroy').val('')
      else
        addMessage('danger', result.error, 6000)
      @unblock()

  updateState: (field) =>
    fieldClass = "#jigoshop_order_#{field}_state"
    @_updateShippingField('jigoshop_checkout_change_state', field, jQuery(fieldClass).val())

  updatePostcode: (field) =>
    fieldClass = "#jigoshop_order_#{field}_postcode"
    @_updateShippingField('jigoshop_checkout_change_postcode', field, jQuery(fieldClass).val())

  updateDiscounts: (event) =>
    $item = jQuery(event.target)
    @block()
    jQuery.ajax(@params.ajax,
      type: 'post'
      dataType: 'json'
      data:
        action: 'jigoshop_cart_update_discounts'
        coupons: $item.val()
    )
    .done (result) =>
      if result.success? && result.success
        if result.empty_cart? == true
          $empty = jQuery(result.html).hide()
          $cart = jQuery('#cart')
          $cart.after($empty)
          $cart.slideUp()
          $empty.slideDown()
          @unblock()
          return

        jQuery('td#product-subtotal').html(result.html.product_subtotal)
        @_updateTotals(result.html.total, result.html.subtotal)
        @_updateDiscount(result)
        @_updateTaxes(result.tax, result.html.tax)
        @_updateShipping(result.shipping_address, result.html.shipping_address)
      else
        addMessage('danger', result.error, 6000)
      @unblock()

  _updateShippingField: (action, field, value) =>
    @block()
    jQuery.ajax(@params.ajax,
      type: 'post'
      dataType: 'json'
      data:
        action: action
        field: field
        differentShipping: jQuery('#different_shipping_address').is(':checked')
        value: value
    )
    .done (result) =>
      if result.success? and result.success
        @_updateTotals(result.html.total, result.html.subtotal)
        @_updateDiscount(result)
        @_updateTaxes(result.tax, result.html.tax)
        @_updateShipping(result.shipping_address, result.html.shipping_address)
      else
        addMessage('danger', result.error, 6000)
      @unblock()

  _updateTotals: (total, subtotal) ->
    jQuery('#cart-total > td > strong').html(total)
    jQuery('#cart-subtotal > td').html(subtotal)

  _updateDiscount: (data) ->
    if data.coupons?
      jQuery('input#jigoshop_coupons').select2('val', data.coupons.split(','))
      $parent = jQuery('tr#cart-discount')
      if data.discount > 0
        jQuery('td', $parent).html(data.html.discount)
        $parent.show()
      else
        $parent.hide()
      if data.html.coupons?
        addMessage('warning', data.html.coupons)

  _updateShipping: (shipping_address, html) ->
    for own shipping_addressClass, value of shipping_address
      $method = jQuery(".shipping_address-#{shipping_addressClass}")
      $method.addClass('existing')
      if $method.length > 0
        if value > -1
          $item = jQuery(html[shipping_addressClass].html).addClass('existing')
          $method.replaceWith($item)
        else
          $method.slideUp -> jQuery(this).remove()
      else if html[shipping_addressClass]?
        $item = jQuery(html[shipping_addressClass].html)
        $item.hide().addClass('existing').appendTo(jQuery('#shipping_address-methods')).slideDown()
    # Remove non-existent methods
    jQuery('#shipping_address-methods > li:not(.existing)').slideUp -> jQuery(this).remove()
    jQuery('#shipping_address-methods > li').removeClass('existing')

  _updateTaxes: (taxes, html) ->
    for own taxClass, tax of html
      $tax = jQuery("#tax-#{taxClass}")
      jQuery("th", $tax).html(tax.label)
      jQuery("td", $tax).html(tax.value)
      if taxes[taxClass] > 0
        $tax.show()
      else
        $tax.hide()

jQuery ->
  new Checkout(jigoshop_checkout)
