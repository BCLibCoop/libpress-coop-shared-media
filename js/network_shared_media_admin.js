/**
 * @package Network Shared Media
 * @copyright BC Libraries Coop 2013
 *
 **/

; (function ($, window) {
  var _overlay,
    selected = {},
    self;

  var CoopNSM = function () {
    self = this;
    return this.init();
  }

  CoopNSM.prototype = {

    init: function () {
      $('#coop-nsm-shared-text-selector').on('change', self.fetch_text);
      $('#coop-nsm-apply-text').on('click', self.maybe_deselect_text_inclusion);
    },

    add_nsm_button: function () {
      var btn = $('<a id="coop_nsm_btn" class="button add_media" title="Add Shared Media" data-editor="content" href="#">Shared Media</a>').prepend('<span class="wp-media-buttons-icon"></span>');

      $('#wp-content-media-buttons').append(btn);
      $('#coop_nsm_btn').on('click', self.mask_overlay);
    },

    fetch_shared_images: function () {
      var data = {
        action: 'coop-nsm-fetch-shared-images'
      };

      $.post(ajaxurl, data).done(function (res) {
        var imgbox = $('.coop-nsm-shared-images');
        if (res.images !== null) {
          var imgs = res.images;

          for (var i = 0; i < imgs.length; i++) {
            var img = $('<img class="coop-nsm-image" src="' + imgs[i].thumbnail + '" width="100" />');
            var d = $('<div class="coop-nsm-image-frame" data-img-id="' + imgs[i].id + '"/>').append(img).on('click', self.toggle_image_selection);
            imgbox.append(d);
          }
        }
      });
    },

    clear_preview_img: function () {
      $('.coop-nsm-item-meta-img').empty();
    },

    set_preview_img: function () {
      var selected = $('.coop-nsm-meta-image-selector option').filter(':selected');

      if (undefined === selected) {
        selected = $('.coop-nsm-meta-image-selector option');
        selected = $(selected)[0];
      }

      var imgfile = $(selected).val();
      var w = selected.data('w');
      var h = selected.data('h');

      var img = $('<img src="' + self.dir + imgfile + '" width="' + w + '" height="' + h + '" >');
      $('.coop-nsm-item-meta-img').empty().append(img);
    },

    maybe_deselect_text_inclusion: function () {
      var sel = $('#coop-nsm-shared-text-selector option').filter(":selected");
      var nsm_text_id = sel.val();

      if (-1 == nsm_text_id && $('#coop-nsm-apply-text').is(':checked')) {
        $('#coop-nsm-apply-text').trigger('click');
      } else {
        $('#coop-nsm-shared-text-selector').val(-1);
      }
    },

    fetch_image_metadata: function (div) {
      var img_id = $(div).data('img-id');
      var data = {
        action: 'coop-nsm-fetch-image-metadata',
        img_id: img_id
      };

      $.post(ajaxurl, data)
      .always(function () {
        $('.coop-nsm-item-meta-img').empty();
        $('.coop-nsm-meta-image-selector').empty();
        $('.coop-nsm-item-meta-imgname').empty();
        $('.coop-nsm-item-meta-imgdate').empty();
        $('.coop-nsm-item-meta-imgsize').empty();
        $('#coop-nsm-img-insert-btn').prop('disabled', true);
      })
      .done(function (res) {
        if (res.result === 'success') {
          self.date = res.date;
          self.dir = res.folder;
          self.fs = res.fullsize;
          self.ms = res.midsize;
          self.ts = res.thumb;

          $('.coop-nsm-item-meta-imgname').append(self.fs.file);
          $('.coop-nsm-item-meta-imgdate').append(self.date);
          $('.coop-nsm-item-meta-imgsize').append(self.fs.width + ' x ' + self.fs.height);

          var sm, med, lrg;

          if (self.ts.file !== '') {
            sm = $('<option value="' + self.ts.file + '" data-w="' + self.ts.width + '" data-h="' + self.ts.height + '">Small ' + self.ts.width + ' x ' + self.ts.height + '</option>');
            $('.coop-nsm-meta-image-selector').append(sm);
          }

          if (self.ms.file !== '') {
            med = $('<option value="' + self.ms.file + '" data-w="' + self.ms.width + '" data-h="' + self.ms.height + '">Medium ' + self.ms.width + ' x ' + self.ms.height + '</option>');
            $('.coop-nsm-meta-image-selector').append(med);
          }

          if (self.fs.file !== '') {
            lrg = $('<option value="' + self.fs.file + '" data-w="' + self.fs.width + '" data-h="' + self.fs.height + '">Full ' + self.fs.width + ' x ' + self.fs.height + '</option>');
            $('.coop-nsm-meta-image-selector').append(lrg);
          }

          $('#coop-nsm-img-insert-btn').prop('disabled', false);

          self.set_preview_img();
        }
      });
    },

    /**
    *
    *	Fetch one body of text to display in the Shared Text widget area.
    *
    **/
    fetch_text: function () {
      var sel = $('#coop-nsm-shared-text-selector option').filter(":selected");
      var nsm_text_id = sel.val();

      if (-1 == nsm_text_id) {
        self.maybe_deselect_text_inclusion();
        return;
      }

      var data = {
        action: 'coop-nsm-fetch-preview',
        nsm_text_id: nsm_text_id
      }

      $.post(ajaxurl, data).done(function (res) {
        if (res.nsm_preview != null) {
          if (res.result == 'success' && res.nsm_preview.length > 4) {
            $('#coop-nsm-apply-text').attr('checked', 'checked');
          }
          $('.coop-nsm-shared-text-preview').empty().append(res.nsm_preview);
        }
      });
    },

    /**
    *	One time routine to set up
    *	markup layout and framework
    *	controls.
    **/
    insert_layout: function () {
      var _content = $('.coop-nsm-content');
      var hdr = $('<div class="coop-nsm-header"/>');

      hdr.append($('<h2/>').append('Coop Shared Media Library'));
      hdr.append($('<a href="#" id="coop_nsm_close">Close</a>').on('click', self.remove_overlay));

      var imgbox = $('<div class="coop-nsm-shared-images"/>');

      var metabox = $('<div class="coop-nsm-item-meta"/>');
      var hd2 = $('<div class="coop-nsm-item-meta-header"><h3>Attachment Details</h3></div>');

      var detailbox = $('<div class="coop-nsm-item-meta-details"/>');
      var imgslot = $('<div class="coop-nsm-item-meta-img"></div>');
      var imgname = $('<div class="coop-nsm-item-meta-imgname"></div>');
      var imgdate = $('<div class="coop-nsm-item-meta-imgdate"></div>');
      var imgsize = $('<div class="coop-nsm-item-meta-imgsize"></div>');

      //	var imglink = $('<a class="coop-nsm-item-meta-imglink" href="">Edit Image</a><br/>');
      //	var imgdele = $('<a class="coop-nsm-item-meta-imgdele" href="">Delete Permanently</a>');
      detailbox.append(imgslot).append(imgname).append(imgdate).append(imgsize); //.append(imglink).append(imgdele);

      metabox.append(hd2);
      metabox.append(detailbox);

      var hd3 = $('<div class="coop-nsm-item-meta-header"><h3>Attachment Display Settings</h3></div>');
      var pos = $('<select class="coop-nsm-item-meta-position" />');
      pos.append($('<option value="none">None</option>'))
        .append($('<option value="right">Right</option>'))
        .append($('<option value="center">Centre</option>'))
        .append($('<option value="left">Left</option>'));

      metabox.append(hd3);
      var tbl = $('<table class="coop-nsm-item-meta-rows"></table>');

      var tr = $('<tr/>');
      var td = $('<td/>').append($('<label>Alignment</label>'));
      var td2 = $('<td/>').append(pos);
      tr.append(td).append(td2)
      tbl.append(tr);


      var tr2 = $('<tr/>');
      var td3 = $('<td/>').append($('<label>Link To</label>'));
      var td4 = $('<td/>').append($('<input id="coop-nsm-add-link" type="text" value="" placeholder="custom url"/>'));
      tr2.append(td3).append(td4)
      tbl.append(tr2);


      var sel = $('<select class="coop-nsm-meta-image-selector" />');

      var tr3 = $('<tr/>');
      var td5 = $('<td/>').append($('<label>Size</label>'));
      var td6 = $('<td/>').append(sel);
      tr3.append(td5).append(td6)
      tbl.append(tr3);


      var btn = $('<p>&nbsp;</p><button id="coop-nsm-img-insert-btn" class="button button-primary button-large" disabled="disabled">Insert Image</button>');
      btn.on('click', self.insert_image);

      metabox.append(tbl);
      metabox.append(btn);
      metabox.hide();

      _content.append(hdr);
      _content.append(imgbox);
      _content.append(metabox);

      $('.coop-nsm-meta-image-selector').on('change', self.set_preview_img);

      self.fetch_shared_images();
    },

    resize_mask: function () {
      if (self._overlay) {
        var h = parseInt($('body').prop('scrollHeight'));
        self._overlay.css('height', h);
        $('.coop-nsm-content').css('height', parseInt(h - 150) + "px");
      }
    },

    mask_overlay: function () {
      if (self._overlay == null) {
        var div = $('<div class="coop-nsm-content"/>');
        self._overlay = $('<div class="coop-nsm-overlay"/>').append(div);
        $('body').append(self._overlay);
        self.insert_layout();
      }

      self.resize_mask();
      self._overlay.show();
    },

    remove_overlay: function () {
      self._overlay.hide();
    },

    select_image: function (div) {
      if (typeof self.selected == 'object') {
        self.clear_preview_img();
        self.selected.removeClass('coop-nsm-image-halo');
      }

      self.selected = div;
      div.addClass('coop-nsm-image-halo');
      self.fetch_image_metadata(div);
      $('.coop-nsm-item-meta').show();
    },

    deselect_image: function (div) {
      div.removeClass('coop-nsm-image-halo');
      self.clear_preview_img();
      $('.coop-nsm-item-meta').hide();
    },

    toggle_image_selection: function () {
      var div = $(this);
      if (div.hasClass('coop-nsm-image-halo')) {
        self.deselect_image(div);
      } else {
        self.select_image(div);
      }
    },

    /**
    *	Insert the selected image as an image tag
    *	perhaps wrapped in an anchor tag
    **/
    insert_image: function () {
      var selected = $('.coop-nsm-meta-image-selector option').filter(':selected');
      if (undefined === selected) {
        selected = $('.coop-nsm-meta-image-selector option');
        selected = $(selected)[0];
      }

      var imgfile = $(selected).val();
      var w = selected.data('w');
      var h = selected.data('h');

      var alignment = $('.coop-nsm-item-meta-position option').filter(':selected');
      if (undefined == alignment) {
        alignment = ''; // none
      } else {
        alignment = 'align' + $(alignment).val();
      }

      var anchor = $('#coop-nsm-add-link').val();
      var tail = '';
      if (undefined !== anchor && anchor.length > 10) {
        anchor = '<a href="' + anchor + '">';
        tail = '</a>';
      } else {
        anchor = '';
      }

      var img = anchor + '<img class="' + alignment + '" src="' + self.dir + imgfile + '" width="' + w + '" height="' + h + '" >' + tail;

      if (tinyMCE.activeEditor) {
        tinymce.activeEditor.execCommand('mceInsertContent', false, img);
      } else {
        alert("Please switch to the Visual Editor to insert shared media");
      }

      self.clear_preview_img();
      self.remove_overlay();
    }

  }

  $.fn.coop_nsm = function () {
    return new CoopNSM();
  }
}(jQuery, window));


jQuery().ready(function () {
  window._nsm = jQuery().coop_nsm();
  window._nsm.fetch_text(); // immediately fetch onload if text has been selected

  if (window.pagenow == 'page' || window.pagenow == 'post' || window.pagenow == 'highlight') {
    jQuery(window).resize(_nsm.resize_mask);
    window._nsm.add_nsm_button();
  }
});
