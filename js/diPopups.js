/*
    // dimaninc popups class

    * 2012/01/07
        * reorganized into a class
*/

var diPopups = function() {
	var self = this,
		states = {},
		events = {};

	var globalOptions = {
        positioning: true,
        positioningX: true,
        positioningY: true,
        mobilePositioning: true,
        livePosition: true
    };

	this.e = {
		$bg: $('#gray-bg')
	};
	this.$e_ar = {};
	this.id_prefix = '';
	this.id_suffix = '-dipopup';

	this.defaultCallbacks = {
		realShow: function (obj, id) {
			obj.getPopupElement(id).fadeIn();
			obj.update_position(id);
		},
		realHide: function (obj, id) {
			obj.getPopupElement(id).fadeOut();
		}
	};

	this.callbacks = {};

	function constructor() {
		$(window)
			.off('resize.dipopup-live orientationchange.dipopup-live')
			.on('resize.dipopup-live orientationchange.dipopup-live', function() {
				$.each(self.$e_ar, function(id, $popup) {
					if ($popup.data('live-position')) {
						self.update_position(id);
					}
				});
			});
	}

	this.setGlobalOptions = function(o) {
		globalOptions = $.extend(globalOptions, o || {});

		return this;
	};

	this.disableAutoPosition = function() {
		return this.setGlobalOptions({
            positioning: false,
            positioningX: false,
            positioningY: false,
            mobilePositioning: false,
            livePosition: false
		});
	};

	this.setEvent = function(id, eventName, callback) {
		if (typeof events[id] == 'undefined') {
			events[id] = {};
		}

		events[id][eventName] = callback;

		return this;
	};

	this.fireEvent = function(id, eventName) {
		if (typeof events[id] != 'undefined' && typeof events[id][eventName] != 'undefined') {
			events[id][eventName]({
				name: eventName,
				id: id,
				element: this.getPopupElement(id),
				diPopup: this
			});
		}
	};

	this.setCallback = function(name, callback) {
		this.callbacks[name] = callback;

		return this;
	};

	this.getCallback = function(name) {
		return this.callbacks[name] || this.defaultCallbacks[name];
	};

	this.checkBg = function() {
		if (!this.e.$bg.length) {
			this.e.$bg = $('<div id="gray-bg"></div>').appendTo($(document.body)).click(function() {
				self.hide_all();
			});
		}

		return this;
	};

	this.onBg = function(eventName, callback) {
		this.e.$bg.on(eventName, callback);

		return this;
	};

	this.offBg = function(eventName) {
		this.e.$bg.off(eventName);

		return this;
	};

	this.show_bg = function() {
		this.checkBg();

		if (!this.e.$bg.is(':visible')) {
			this.e.$bg.css({
				opacity: 0
			}).show().animate({
				opacity: 1
			});
		}

		return this;
	};

	this.hide_bg = function() {
		this.e.$bg.fadeOut();
	};

	this.getPopupElement = function(id) {
		return this.exists(id)
			? this.$e_ar[id]
			: $();
	};

	function realShow(id) {
		self.getCallback('realShow')(self, id);
	}

	function realHide(id) {
		self.getCallback('realHide')(self, id);
	}

	this.show = function(name, _opts/* or showBackground */) {
		var opts = {
			showBackground: true,
			content: null,
            positioning: globalOptions.positioning,
            positioningX: globalOptions.positioningX,
            positioningY: globalOptions.positioningY,
            mobilePositioning: globalOptions.mobilePositioning,
            livePosition: globalOptions.livePosition,
			afterUpdatePosition: null
		};
		var $e = this.getPopupElement(name);

		if (typeof _opts === 'object') {
			opts = $.extend(opts, _opts);
		} else if (typeof _opts !== 'undefined') {
			opts.showBackground = _opts;
		}

		if (!this.exists(name)) {
			if (opts.content) {
				this.create($.extend({
					name: name
				}, opts));
			}
		} else if (opts.content) {
			this.setContent({
				name: name,
				content: opts.content
			});
		}

		if ($e.data('detach') && !$e.parent().is('body')) {
			$(document.body).append($e.detach());
		}

		this.checkCloseButton(name);

		realShow(name);

		states[name] = true;

		if (opts.showBackground) {
			this.show_bg();
		}

		$e.children('input[type="text"]:visible,textarea:visible').eq(0).focus();

		this.fireEvent(name, 'show');

		return false;
	};

	this.checkCloseButton = function (name) {
		if (!$('.close', this.$e_ar[name]).length && !this.$e_ar[name].data('no-close')) {
			this.$e_ar[name].prepend($('<u class="close"></u>').click(function() {
				self.hide(name);
				self.hide_bg();
			}));
		}

		return this;
	};

	this.create = function (name/* or options*/, content/* = null*/, options/* = null*/) {
		if (typeof name === 'object' && typeof content === 'undefined' && typeof options === 'undefined') {
			options = name;
		} else {
			options = options || {};
			options.name = name;
			options.content = content;
		}

		options = $.extend({
			name: null,
			content: null,
			showCloseButton: true,
			positioning: globalOptions.positioning, // calc position on show
			positioningX: globalOptions.positioningX, // calc x-position on show
			positioningY: globalOptions.positioningY, // calc y-position on show
			mobilePositioning: globalOptions.mobilePositioning, // calc position on mobiles on show
			livePosition: globalOptions.livePosition, // auto update position on window resize
			afterUpdatePosition: null // after update position callback
		}, options);

		var $el = $('<div/>');
		$el
			.addClass('dipopup')
			.data('type', 'dipopup')
			.attr('data-type', 'dipopup')
			.data('name', options.name)
			.attr('data-name', options.name)
			.attr('data-no-close', !options.showCloseButton)
			.data('no-close', !options.showCloseButton)
			.data('after-update-position', options.afterUpdatePosition)
			.data('positioning', options.positioning)
			.attr('data-positioning', options.positioning)
			.data('positioning-x', options.positioningX)
			.attr('data-positioning-x', options.positioningX)
			.data('positioning-y', options.positioningY)
			.attr('data-positioning-y', options.positioningY)
			.data('mobile-positioning', options.mobilePositioning)
			.attr('data-mobile-positioning', options.mobilePositioning)
			.data('live-position', options.livePosition)
			.attr('data-live-position', options.livePosition)
			.html(options.content)
			.appendTo(document.body);

		this.$e_ar[options.name] = $el;

		return this;
	};

	this.exists = function (name) {
		if (typeof this.$e_ar[name] === 'undefined') {
			this.$e_ar[name] = $([
				'#' + this.id_prefix + name + this.id_suffix,
				'.dipopup[data-name="' + name + '"]',
				'[data-type="dipopup"][data-name="' + name + '"]'
			].join(','));
		}

		return !!this.$e_ar[name].length;
	};

	this.setContent = function (options) {
		options = $.extend({
			name: null,
			content: null,
			create: false
		}, options);

		if (!this.exists(options.name)) {
			if (options.create) {
				this.create(options);
			} else {
				return this;
			}
		}

		this.getPopupElement(options.name).html(options.content);

		this
			.checkCloseButton(options.name)
			.update_position(options.name);

		return this;
	};

	this.update_position = function(id) {
		if (this.$e_ar[id]) {
			var positioning = this.$e_ar[id].data('positioning');
			var mobilePositioning = this.$e_ar[id].data('mobile-positioning');

			if (is_mobile && mobilePositioning !== undefined) {
				positioning = mobilePositioning;
			} else if (positioning === undefined) {
				positioning = true;
			}

			if (!positioning) {
				return this;
			}

			var properties = {};

			if (!this.$e_ar[id].data('manual-x') && this.$e_ar[id].data('positioning-x') !== false) {
				properties.marginLeft = this.$e_ar[id].outerWidth() / -2;
			}

			if (!this.$e_ar[id].data('manual-y') && this.$e_ar[id].data('positioning-y') !== false) {
				properties.marginTop = this.$e_ar[id].outerHeight() / -2;
			}

			this.$e_ar[id].css(properties);

			var cb;

			if (cb = this.$e_ar[id].data('after-update-position')) {
				cb(this.$e_ar[id]);
			}
		}

		return this;
	};

	this.visible = function(id) {
		return states[id];
	};

	this.hide = function(id) {
		realHide(id);

		this.fireEvent(id, 'hide');

		states[id] = false;

		var atLeastOneVisible = false;

		for (var i in this.$e_ar) {
			if (this.$e_ar.hasOwnProperty(i)) {
				if (this.visible(i)) {
					atLeastOneVisible = true;

					break;
				}
			}
		}

		if (!atLeastOneVisible) {
			this.hide_bg();
		}
	};

	this.hide_all = function() {
		this.hide_bg();

		for (var id in this.$e_ar) {
			if (this.$e_ar.hasOwnProperty(id)) {
				this.hide(id);
			}
		}

		return false;
	};

	this.thanks = function(title, content) {
		var id = 'thanks';

		this.show(id);

		$('.window_title', this.$e_ar[id]).html(title);
		$('.window_text', this.$e_ar[id]).html(content);
	};

	constructor();
};

var dip = new diPopups();
