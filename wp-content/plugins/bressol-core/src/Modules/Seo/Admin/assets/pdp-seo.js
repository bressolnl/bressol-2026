(function ($) {
  function parseIds(raw) {
    if (!raw) {
      return [];
    }
    try {
      var parsed = JSON.parse(raw);
      if (Array.isArray(parsed)) {
        return parsed.map(function (id) {
          return parseInt(id, 10);
        }).filter(function (id) {
          return Number.isInteger(id) && id > 0;
        });
      }
    } catch (e) {
      return [];
    }
    return [];
  }

  function renderPreview($container, ids) {
    $container.empty();
    ids.forEach(function (id) {
      if (!id) {
        return;
      }
      var attachment = wp.media.attachment(id);
      attachment.fetch().done(function () {
        var sizes = attachment.get('sizes') || {};
        var thumb = sizes.thumbnail ? sizes.thumbnail.url : attachment.get('url');
        if (thumb) {
          var img = $('<img>', { src: thumb, alt: '' });
          var wrap = $('<span>', { 'class': 'bressol-seo-usage-thumb', 'data-id': id }).append(img);
          $container.append(wrap);
        }
      });
    });
  }

  $(function () {
    var $input = $('#bressol_seo_usage_images');
    var $preview = $('#bressol-seo-usage-preview');
    var $add = $('#bressol-seo-usage-add');
    var $clear = $('#bressol-seo-usage-clear');

    if (!$input.length) {
      return;
    }

    renderPreview($preview, parseIds($input.val()));

    $add.on('click', function (e) {
      e.preventDefault();
      var frame = wp.media({
        title: 'Select usage images',
        library: { type: 'image' },
        multiple: true
      });
      frame.on('select', function () {
        var selection = frame.state().get('selection');
        var ids = selection.map(function (model) {
          return model.get('id');
        });
        $input.val(JSON.stringify(ids));
        renderPreview($preview, ids);
      });
      frame.open();
    });

    $clear.on('click', function (e) {
      e.preventDefault();
      $input.val('');
      $preview.empty();
    });
  });
})(jQuery);
