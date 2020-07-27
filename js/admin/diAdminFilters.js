var diAdminFilters = function(_opts) {
	this.MODE_COOKIE = 1;
	this.MODE_GET = 2;

	this.filters = {};

    var self = this,
        opts = $.extend({
            table: null,
	        fields: null,
	        mode: this.MODE_COOKIE
        }, _opts || {}),
	    e = {
		    $form: $('form[name="admin_filter_form\\[{0}\\]"]'.format(opts.table))
	    },
	    baseUri,
	    uriGlue;

    this.constructor = function() {
	    if (!opts.fields) {
		    opts.fields = filters_ar[opts.table];
	    }

	    baseUri = kill_uri_params(window.location.href, opts.fields);
	    uriGlue = baseUri.indexOf('?') === -1 ? '?' : '&';

	    e.$form.submit(function() {
            return self.apply();
        }).find('button[data-purpose="reset"]').click(function() {
            self.reset();

            return false;
        });

	    setupResetFilterButtons();

	    return this;
    };

	function setupResetFilterButtons() {
		$('[data-purpose="reset-filter"]').on('click', function() {
			var $this = $(this),
				field = $this.data('field'),
				$input = e.$form.find('input,select,textarea')
					.filter('[name="{0}"],[name="admin_filter\\[{0}\\]"]'.format(field));

			if ($input.val() && $input.val() !== '0') {
				$input.val('');

				self.apply();
			}

			return false;
		});
	}

	this.apply = function() {
		switch (opts.mode) {
			case this.MODE_COOKIE:
				this.gatherFilterValues();
				$.cookie('admin_filter__' + opts.table, JSON.stringify(di.objectFilter(this.filters)), {
					expires: 365,
					path: '/_admin/'
				});
				reloadPageInCookiesMode();
				return false;

			case this.MODE_GET:
				/*
				window.location.href = baseUri + uriGlue + Object.keys(this.filters).map(function(key) {
					return key + '=' + this.filters[key];
				}).join('&');
				*/
				return true;

			default:
				throw 'Unknown filters mode: ' + opts.mode;
		}
	};

	this.reset = function() {
		var k, name;

		// delete cookie
		name = 'admin_filter__' + opts.table;
		$.removeCookie(name, { path: '/_admin/' });
		$.removeCookie(name);

		// old cookie approach
		for (var i = 0; i < opts.fields.length; i++) {
			k = opts.fields[i];
			name = 'admin_filter[' + opts.table + '][' + k + ']';

			$.removeCookie(name, { path: '/_admin/' });
			$.removeCookie(name);
		}

		if (opts.mode === self.MODE_GET) {
			window.location.href = baseUri;
		} else {
			reloadPageInCookiesMode();
		}

		return this;
	};

	function reloadPageInCookiesMode() {
		if (baseUri !== window.location.href) {
			window.location.href = baseUri;
		} else {
			window.location.reload();
		}
	}

	this.gatherFilterValues = function() {
		var i, k, v, selector;
		var $f;
		var dateRangeFields = {};
		var regs;

		for (i = 0; i < opts.fields.length; i++) {
			k = opts.fields[i];
			selector = ('#admin_filter[' + k + ']')
				.replace(/\[/g, '\\[')
				.replace(/\]/g, '\\]');
			$f = $(selector, e.$form);
			v = $f.val();

			regs = k.match(/^([^\[\]]+)\]\[(\d+)\]\[(.+)$/);

			if (regs && typeof regs[3] != 'undefined') {
				// regs[1]: field name: date, etc.
				if (typeof dateRangeFields[regs[1]] == 'undefined') {
					dateRangeFields[regs[1]] = {};
				}

				// regs[2]: index of range: 1 or 2
				if (typeof dateRangeFields[regs[1]][regs[2]] == 'undefined') {
					dateRangeFields[regs[1]][regs[2]] = {};
				}

				// regs[3]: part of date: dd/dm/dy
				dateRangeFields[regs[1]][regs[2]][regs[3]] = v;
			} else {
				this.filters[k] = v;
			}
		}

		// building string dates YYYY-mm-dd
		for (k in dateRangeFields) {
			if (dateRangeFields.hasOwnProperty(k)) {
				for (i in dateRangeFields[k]) {
					if (dateRangeFields[k].hasOwnProperty(i)) {
						if (typeof this.filters[k] == 'undefined') {
							this.filters[k] = [];
						}

						this.filters[k].push([
							dateRangeFields[k][i].dy,
							dateRangeFields[k][i].dm,
							dateRangeFields[k][i].dd
						].join('-'));
					}
				}
			}
		}

		return this;
	};

	this.getFilterValues = function() {
		this.gatherFilterValues();

		return this.filters;
	};

    this.constructor();
};