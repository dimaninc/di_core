var diAdminFilters = function (_opts) {
  this.MODE_COOKIE = 1;
  this.MODE_GET = 2;

  this.filters = {};
  this.enteredDateFields = {};

  var self = this,
    opts = $.extend(
      {
        table: null,
        fields: null,
        mode: this.MODE_COOKIE
      },
      _opts || {}
    ),
    e = {
      $form: $('form[name="admin_filter_form\\[{0}\\]"]'.format(opts.table))
    },
    baseUri,
    uriGlue;

  this.constructor = function () {
    if (!opts.fields) {
      opts.fields = filters_ar[opts.table];
    }

    baseUri = kill_uri_params(window.location.href, opts.fields);
    uriGlue = baseUri.indexOf('?') === -1 ? '?' : '&';

    e.$form.submit(function () {
      return self.apply();
    });

    e.$form
      .find('[data-submit-on-change="true"],[data-submit-on-change="1"]')
      .on('change', function () {
        return self.apply();
      });

    e.$form.find('button[data-purpose="reset"]').click(function () {
      self.reset();

      return false;
    });

    this.setupResetFilterButtons().setupDateInputs().setupCopyToClipboard();

    return this;
  };

  this.setDateEntered = function (field, idx, state) {
    var $f;

    if (field instanceof $ && !idx && !state) {
      $f = field.closest('.admin-filter-date-wrapper');
      field = $f.data('field');
      idx = $f.data('idx');
      state = !$f.hasClass('set');
    } else {
      $f = $(
        '.admin-filter-date-wrapper[data-field="{0}"][data-idx="{1}"]'.format(
          field,
          idx
        ),
        e.$form
      );
      state = !!state;
    }

    this.setFilledDateField(field, idx, state);

    $f.toggleClass('set', state);

    return this;
  };

  this.setFilledDateField = function (field, idx, state) {
    if (typeof this.enteredDateFields[field] === 'undefined') {
      this.enteredDateFields[field] = {};
    }

    this.enteredDateFields[field][idx] = state;

    return this;
  };

  this.setupDateInputs = function () {
    var $wrappers = $('.admin-filter-date-wrapper');

    $wrappers.each(function () {
      var $f = $(this);

      field = $f.data('field');
      idx = $f.data('idx');
      state = $f.hasClass('set');

      self.setFilledDateField(field, idx, state);
    });

    $wrappers.find('.empty-dates, .reset-filter', e.$form).on('click', function () {
      self.setDateEntered($(this));

      return false;
    });

    return this;
  };

  this.setupResetFilterButtons = function () {
    $('[data-purpose="reset-filter"]', e.$form).on('click', function () {
      var $this = $(this),
        $row = $this.closest('.row'),
        field = $this.data('field'),
        $input = e.$form
          .find('input,select,textarea')
          .filter('[name="{0}"],[name="admin_filter\\[{0}\\]"]'.format(field));

      if ($input.val() && $input.val() !== '0') {
        $input.val('');

        self.apply();
      } else if ($row.find('.admin-filter-date-wrapper').length) {
        self.setDateEntered(field, 1, false);
        self.setDateEntered(field, 2, false);

        self.apply();
      }

      return false;
    });

    return this;
  };

  this.apply = function () {
    switch (opts.mode) {
      case this.MODE_COOKIE:
        this.gatherFilterValues();
        $.cookie(
          'admin_filter__' + opts.table,
          JSON.stringify(di.objectFilter(this.filters)),
          {
            expires: 365,
            path: '/_admin/'
          }
        );
        this.reloadPageInCookiesMode();
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

  this.reset = function () {
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
      location.href = baseUri;
    } else {
      this.reloadPageInCookiesMode();
    }

    return this;
  };

  this.reloadPageInCookiesMode = function () {
    if (baseUri !== location.href) {
      location.href = baseUri;
    } else {
      location.reload();
    }

    return this;
  };

  this.gatherFilterValues = function () {
    var i, k, v, selector;
    var $f;
    var dateRangeFields = {};
    var regs;

    for (i = 0; i < opts.fields.length; i++) {
      k = opts.fields[i];
      selector = ('#admin_filter[' + k + ']')
        .replace(/\[/g, '\\[')
        .replace(/]/g, '\\]');
      $f = $(selector, e.$form);
      v = $f.val();
      regs = k.match(/^([^\[\]]+)]\[(\d+)]\[(.+)$/);

      if (regs && typeof regs[3] !== 'undefined') {
        // regs[1]: field name: date, etc.
        if (typeof dateRangeFields[regs[1]] === 'undefined') {
          dateRangeFields[regs[1]] = {};
        }

        // regs[2]: index of range: 1 or 2
        if (typeof dateRangeFields[regs[1]][regs[2]] === 'undefined') {
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
            if (typeof this.filters[k] === 'undefined') {
              this.filters[k] = [];
            }

            v = [
              dateRangeFields[k][i].dy,
              dateRangeFields[k][i].dm,
              dateRangeFields[k][i].dd
            ].join('-');

            // store null instead of selects value when unset
            if (typeof this.enteredDateFields[k][i] !== 'undefined') {
              if (!this.enteredDateFields[k][i]) {
                v = null;
              }
            }

            if (v !== null) {
              this.filters[k].push(v);
            }
          }
        }
      }
    }

    return this;
  };

  this.getFilterValues = function () {
    this.gatherFilterValues();

    return this.filters;
  };

  this.setupCopyToClipboard = function () {
    di.loadScript('/assets/js/_core/vendor/clipboard.min.js', () => {
      const $btn = $('[data-clipboard-text]');
      const clipboard = new Clipboard('[data-clipboard-text]');

      clipboard.on('success', function (e) {
        $btn.addClass('success');

        setTimeout(() => {
          $btn.removeClass('success');
        }, 1000);
      });

      clipboard.on('error', function (e) {
        $btn.addClass('error');
      });
    });

    return this;
  };

  this.constructor();
};
