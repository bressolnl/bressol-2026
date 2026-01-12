(function () {
  window.dataLayer = window.dataLayer || [];

  window.bressolDataLayerPush = function (eventName, payload) {
    window.dataLayer.push(Object.assign({ event: eventName }, payload || {}));
  };

  function parseNameFromAriaLabel(label) {
    // Ejemplo: "Remove Producto Test Bressol from cart"
    if (!label) return null;
    var m = label.match(/^remove\s+(.*?)\s+from\s+cart/i);
    return m && m[1] ? m[1] : null;
  }

  // WooCommerce Blocks cart: botón remove
  document.addEventListener(
    "click",
    function (e) {
      var btn = e.target.closest("button.wc-block-cart-item__remove-link");
      if (!btn) return;

      // Payload mínimo (por Blocks no tenemos product_id fácilmente sin instrumentación extra)
      var name = parseNameFromAriaLabel(btn.getAttribute("aria-label"));

      var payload = name
        ? { ecommerce: { items: [{ item_name: String(name), quantity: 1 }] } }
        : {};

      window.bressolDataLayerPush("remove_from_cart", payload);
    },
    true
  );
})();