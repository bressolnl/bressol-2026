// Bressol Core Tracking - base
(function () {
    window.dataLayer = window.dataLayer || [];
  
    // Helper opcional para estandarizar eventos
    window.bressolDataLayerPush = function (eventName, payload) {
      window.dataLayer.push(Object.assign({ event: eventName }, payload || {}));
    };
  })();