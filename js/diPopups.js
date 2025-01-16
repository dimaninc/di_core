function diPopups() {
  var self = this;
  var states = {};
  var events = {};

  this.e = {
    $overlay: $('.dipopup-overlay')
  };
  this.$popups = {};
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
    var $e = this.getPopupElement(name);

    if (typeof _opts === 'object') {
      opts = $.extend(opts, _opts);
    } else if (typeof _opts !== 'undefined') {
      opts.showBackground = _opts;
    }

    if (!this.exists(name)) {
      if (opts.content) {
        this.create(
          $.extend(
            {
              name: name
            },
            opts
          )
        );
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
    if (typeof this.$popups[name] === 'undefined') {
      this.$popups[name] = $(
        [
          '#' + this.id_prefix + name + this.id_suffix,
          '.dipopup[data-name="' + name + '"]',
          '[data-type="dipopup"][data-name="' + name + '"]'
        ].join(',')
      );
    }

    return !!this.$popups[name].length;
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
