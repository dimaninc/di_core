function diPopups() {
  var self = this;
  var states = {};
  var events = {};
  var templateSources = {}; // tracks which popups came from templates
  const DEFAULT_REMOVE_DELAY = 500;

  this.e = {
    $overlay: $('.dipopup-overlay')
  };
  this.$popups = {};
  this.$templates = {};
  this.optsAr = {};
  this.id_prefix = '';
  this.id_suffix = '-dipopup';

  this.defaultCallbacks = {
    realShow: function (obj, id) {
      obj.getPopupElement(id).addClass('dipopup--visible');
    },
    realHide: function (obj, id) {
      obj.getPopupElement(id).removeClass('dipopup--visible');
    }
  };

  this.callbacks = {};

  this.setEvent = function (id, eventName, callback) {
    if (typeof events[id] === 'undefined') {
      events[id] = {};
    }

    events[id][eventName] = callback;

    return this;
  };

  this.fireEvent = function (id, eventName) {
    if (
      typeof events[id] !== 'undefined' &&
      typeof events[id][eventName] !== 'undefined'
    ) {
      events[id][eventName]({
        name: eventName,
        id: id,
        element: this.getPopupElement(id),
        diPopup: this
      });
    }
  };

  this.setCallback = function (name, callback) {
    this.callbacks[name] = callback;

    return this;
  };

  this.getCallback = function (name) {
    return this.callbacks[name] || this.defaultCallbacks[name];
  };

  this.checkOverlay = function () {
    if (!this.e.$overlay.length) {
      this.e.$overlay = $('<div class="dipopup-overlay"></div>')
        .appendTo($(document.body))
        .on('click', function () {
          self.hideAll();
        });
    }

    return this;
  };

  this.onOverlay = function (eventName, callback) {
    this.e.$overlay.on(eventName, callback);

    return this;
  };

  this.offOverlay = function (eventName) {
    this.e.$overlay.off(eventName);

    return this;
  };

  this.showOverlay = function () {
    this.checkOverlay();

    if (
      !this.e.$overlay.is(':visible') ||
      this.e.$overlay.css('visibility') === 'hidden'
    ) {
      this.e.$overlay.addClass('dipopup-overlay--visible');
    }

    return this;
  };

  this.hideOverlay = function () {
    this.checkOverlay();

    $('html').removeClass('dipopup-shown');
    this.e.$overlay.removeClass('dipopup-overlay--visible');

    return this;
  };

  this.getPopupElement = function (id) {
    return this.exists(id) ? this.$popups[id] : $();
  };

  function realShow(id) {
    self.getCallback('realShow')(self, id);

    return self;
  }

  function realHide(id) {
    self.getCallback('realHide')(self, id);

    return self;
  }

  this.setOptsFor = function (name, opts) {
    this.optsAr[name] = opts || {};

    var $e = this.getPopupElement(name);

    if ($e) {
      this.copyOptsToDom(name, $e);
    }

    return this;
  };

  this.show = function (name, _opts /* or showBackground */) {
    var opts = {
      name: name,
      showCloseButton: true,
      showBackground: true,
      content: null
    };

    if (typeof _opts === 'object') {
      opts = $.extend(opts, _opts);
    } else if (typeof _opts !== 'undefined') {
      opts.showBackground = _opts;
    }

    // Prepare popup (find in DOM or instantiate from template)
    var $e = this.prepare(name);

    if (!$e.length) {
      // Create dynamically if content provided
      if (opts.content) {
        this.create(
          $.extend(
            {
              name: name
            },
            opts
          )
        );
        $e = this.getPopupElement(name);
      }
    } else if (opts.content) {
      this.setContent({
        name: name,
        content: opts.content
      });
    }

    this.setOptsFor(name, opts);

    if ($e.data('detach') && !$e.parent().is('body')) {
      $(document.body).append($e.detach());
    }

    this.checkCloseButton(name);

    realShow(name);

    states[name] = true;

    if (opts.showBackground) {
      this.showOverlay();
    }

    $e.children('input[type="text"],input[type="email"],input[type="tel"],textarea')
      .filter(':visible')
      .eq(0)
      .focus();

    this.fireEvent(name, 'show');

    $('html').addClass('dipopup-shown');

    return this;
  };

  this.checkCloseButton = function (name) {
    if (
      !$('.dipopup--close', this.$popups[name]).length &&
      !this.$popups[name].data('no-close')
    ) {
      this.$popups[name].prepend(
        $('<div class="dipopup--close"></div>').on('click', function () {
          self.hide(name);
          self.hideOverlay();
        })
      );
    }

    return this;
  };

  this.create = function (
    name /* or options*/,
    content /* = null*/,
    options /* = null*/
  ) {
    if (
      typeof name === 'object' &&
      typeof content === 'undefined' &&
      typeof options === 'undefined'
    ) {
      options = name;
    } else {
      options = options || {};
      options.name = name;
      options.content = content;
    }

    options = $.extend(
      {
        name: null,
        content: null,
        showCloseButton: true
      },
      options
    );

    var $el = $('<div/>');
    this.setOptsFor(options.name, options).copyOptsToDom(options.name, $el);
    $el
      .addClass('dipopup')
      .data('type', 'dipopup')
      .attr('data-type', 'dipopup')
      .html(options.content)
      .appendTo(document.body);

    this.$popups[options.name] = $el;

    return this;
  };

  this.copyOptsToDom = function (name, $el) {
    var options = this.optsAr[name] || {};

    $el
      .data('name', name || options.name)
      .attr('data-name', name || options.name)
      .attr('data-no-close', !options.showCloseButton)
      .data('no-close', !options.showCloseButton);

    return this;
  };

  this.exists = function (name) {
    // Check if already cached and in DOM
    if (typeof this.$popups[name] !== 'undefined' && this.$popups[name].length) {
      return true;
    }

    // Search in DOM for existing popup
    this.$popups[name] = $(
      [
        '#' + this.id_prefix + name + this.id_suffix,
        '.dipopup[data-name="' + name + '"]',
        '[data-type="dipopup"][data-name="' + name + '"]'
      ].join(',')
    );

    if (this.$popups[name].length) {
      return true;
    }

    // Check for template
    return this.hasTemplate(name);
  };

  this.hasTemplate = function (name) {
    return !!this.getTemplate(name).length;
  };

  this.getTemplate = function (name) {
    if (typeof this.$templates[name] === 'undefined') {
      this.$templates[name] = $('template[data-popup-name="' + name + '"]');
    }

    return this.$templates[name];
  };

  this.isFromTemplate = function (name) {
    return !!templateSources[name];
  };

  this.instantiateFromTemplate = function (name) {
    var $template = this.getTemplate(name);

    if (!$template.length) {
      return null;
    }

    // Clone template content and insert right after template
    var templateContent = $template[0].content;
    var $popup = $(templateContent.firstElementChild.cloneNode(true));

    $popup.insertAfter($template);

    // Cache the popup and mark as template-sourced
    this.$popups[name] = $popup;
    templateSources[name] = true;

    return $popup;
  };

  /**
   * Prepare popup for manipulation before showing.
   * - If popup exists in DOM, returns it
   * - If template exists, instantiates it and returns the element
   * - Otherwise returns empty jQuery object
   *
   * @param {string} name - Popup name
   * @returns {jQuery} - Popup element ready for manipulation
   */
  this.prepare = function (name) {
    // Check if already in DOM (cached)
    if (
      typeof this.$popups[name] !== 'undefined' &&
      this.$popups[name].length
    ) {
      return this.$popups[name];
    }

    // Search in DOM
    this.$popups[name] = $(
      [
        '#' + this.id_prefix + name + this.id_suffix,
        '.dipopup[data-name="' + name + '"]',
        '[data-type="dipopup"][data-name="' + name + '"]'
      ].join(',')
    );

    if (this.$popups[name].length) {
      return this.$popups[name];
    }

    // Try to instantiate from template
    if (this.hasTemplate(name)) {
      return this.instantiateFromTemplate(name);
    }

    return $();
  };

  this.removeFromDom = function (name) {
    if (this.$popups[name] && this.$popups[name].length) {
      this.$popups[name].remove();
      delete this.$popups[name];
    }

    return this;
  };

  this.setContent = function (options) {
    options = $.extend(
      {
        name: null,
        content: null,
        create: false
      },
      options
    );

    if (!this.exists(options.name)) {
      if (options.create) {
        this.create(options);
      } else {
        return this;
      }
    }

    this.getPopupElement(options.name).html(options.content);

    this.checkCloseButton(options.name);

    return this;
  };

  this.isMobile = function () {
    return is_mobile; //$(window).width() < 450;
  };

  this.visible = function (id) {
    return states[id];
  };

  this.hide = function (id) {
    realHide(id);

    this.fireEvent(id, 'hide');

    // Remove template-based popups from DOM after hide animation completes
    if (this.isFromTemplate(id)) {
      var popupToRemove = this.$popups[id];
      var removeDelay = this.getRemoveDelay(id);

      if (removeDelay > 0) {
        setTimeout(function () {
          popupToRemove.remove();
        }, removeDelay);
      } else {
        popupToRemove.remove();
      }

      delete this.$popups[id];
      delete templateSources[id];
    }

    states[id] = false;

    var atLeastOneVisible = false;

    for (var i in this.$popups) {
      if (this.$popups.hasOwnProperty(i)) {
        if (this.visible(i)) {
          atLeastOneVisible = true;

          break;
        }
      }
    }

    if (!atLeastOneVisible) {
      this.hideOverlay();
    }

    return this;
  };

  this.getRemoveDelay = function (id) {
    var $e = this.getPopupElement(id);
    var delay = $e.data('remove-delay');

    // Default delay to allow CSS transitions to complete
    return typeof delay !== 'undefined' ? parseInt(delay, 10) : DEFAULT_REMOVE_DELAY;
  };

  this.hideAll = function () {
    this.hideOverlay();

    for (var id in this.$popups) {
      if (this.$popups.hasOwnProperty(id)) {
        this.hide(id);
      }
    }

    return this;
  };

  /** @deprecated */
  this.checkBg = this.checkOverlay;
  /** @deprecated */
  this.onBg = this.onOverlay;
  /** @deprecated */
  this.offBg = this.offOverlay;
  /** @deprecated */
  this.show_bg = this.showOverlay;
  /** @deprecated */
  this.hide_bg = this.hideOverlay;
  /** @deprecated */
  this.hide_all = this.hideAll;
}

var dip = new diPopups();
