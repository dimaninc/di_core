var diAdminFilters = function(_opts) {
	this.MODE_COOKIE = 1;
	this.MODE_GET = 2;

    var self = this,
        opts = $.extend({
            table: null,
	        fields: null,
	        mode: this.MODE_COOKIE
        }, _opts || {}),
	    e = {
		    $form: $('form[name="admin_filter_form\\[{0}\\]"]'.format(opts.table))
	    },
	    filters = {},
	    baseUri,
	    uriGlue;

    function constructor() {
	    if (!opts.fields) {
		    opts.fields = filters_ar[opts.table];
	    }

	    baseUri = kill_uri_params(window.location.href, opts.fields);
	    uriGlue = baseUri.indexOf('?') === -1 ? '?' : '&';

	    e.$form.submit(function() {
            return apply();
        }).find('button[data-purpose="reset"]').click(function() {
            reset();

            return false;
        });

	    setupResetFilterButtons();
    }

	function setupResetFilterButtons() {
		$('[data-purpose="reset-filter"]').on('click', function() {
			var $this = $(this),
				field = $this.data('field'),
				$input = e.$form.find('input,select,textarea').filter('[name="{0}"],[name="admin_filter\\[{0}\\]"]'.format(field));

			if ($input.val() && $input.val() !== '0') {
				$input.val('');

				apply();
			}

			return false;
		});
	}

	function apply() {
		if (opts.mode == self.MODE_COOKIE) {
			gatherFilterValues();

			$.each(filters, function(k, v) {
				$.cookie('admin_filter[' + opts.table + '][' + k + ']', v, {
					expires: 365
				});
			});

			reloadPageInCookiesMode();

			return false;
		} else if (opts.mode == self.MODE_GET) {
			/*
			window.location.href = baseUri + uriGlue + Object.keys(filters).map(function(key) {
					return key + '=' + filters[key];
				}).join('&');
			*/

			return true;
		}
	}

	function reset() {
		var k;

		for (var i = 0; i < opts.fields.length; i++) {
			k = opts.fields[i];

			$.removeCookie('admin_filter[' + opts.table + '][' + k + ']');
		}

		if (opts.mode == self.MODE_COOKIE) {
			reloadPageInCookiesMode();
		} else if (opts.mode == self.MODE_GET) {
			window.location.href = baseUri;
		}
	}

	function reloadPageInCookiesMode() {
		if (baseUri != window.location.href) {
			window.location.href = baseUri;
		} else {
			window.location.reload();
		}
	}

	function gatherFilterValues() {
		var k, $f, selector;

		for (var i = 0; i < opts.fields.length; i++) {
			k = opts.fields[i];
			selector = ('#admin_filter[' + k + ']').replace(/\[/g, '\\[').replace(/\]/g, '\\]');
			$f = $(selector, e.$form);

			filters[k] = $f.val();
		}
	}

	this.getFilterValues = function() {
		gatherFilterValues();

		return filters;
	};

    constructor();
};