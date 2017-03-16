var diDynamicRows = function(opts)
{
	var self = this,
		$anc, $src;

	opts = opts || {};

	this.counters = {};
	this.signs = {};
	this.field_titles = {};

	function constructor()
	{
		opts = $.extend({
			field: null,
			fieldTitle: null,
			sign: 1,
			counter: 0,
			afterInit: function(DynamicRows) {},
			afterAddRow: function(DynamicRows, $row, id) {},
			afterDelRow: function(DynamicRows, id) {}
		}, opts);

		if (opts.field)
		{
			self.init(opts.field, opts.fieldTitle, opts.sign, opts.counter);
		}

	}

	function setupEvents()
	{
		var validatePassword = function() {
			var $password = $(this).parent().find('input[type="password"]:not(.password-confirm)'),
				$password2 = $(this).parent().find('input[type="password"].password-confirm');

			$password2.get(0).setCustomValidity($password.val() != $password2.val()
				? 'Введенные пароли не совпадают'
				: ''
			);
		};

		$anc.parent().on('change', 'input[type="password"]:not(.password-confirm)', validatePassword);
		$anc.parent().on('keyup', 'input[type="password"].password-confirm', validatePassword);
	}

	this.init = function(field, field_title, sign, counter)
	{
		this.counters[field] = typeof counter == 'undefined' ? 0 : counter * 1;
		this.field_titles[field] = field_title;
		this.signs[field] = typeof sign == 'undefined' || sign > 0 ? 1 : -1;

		// back compatibility
		if (!opts.field)
		{
			opts.field = field;
		}

		if (!opts.fieldTitle)
		{
			opts.fieldTitle = field_title;
		}

		if (!opts.sign)
		{
			opts.sign = sign;
		}

		if (!opts.counter)
		{
			opts.counter = counter;
		}
		//

		$src = $('#js_' + opts.field + '_resource');
		$anc = $('#' + opts.field + '_anchor_div');

		setupEvents();

		if (opts.afterInit)
		{
			opts.afterInit(this);
		}
	};

	this.getRows = function()
	{
		return $anc.parent().find('.dynamic-row');
	};

	this.is_field_inited = function(field)
	{
		return typeof this.counters[field] != 'undefined';
	};

	this.add = function(field)
	{
		if (!this.is_field_inited(field))
		{
			console.log('diDynamicRows: field {0} not inited'.format(field));

			return false;
		}

		if (!$src.length || !$anc.length)
		{
			console.log('diDynamicRows: no $src or $anc');

			return false;
		}

		this.counters[field] += this.signs[field];

		var id = - this.counters[field];
		var html = $src.html();
		html = html.substr(html.indexOf('>') + 1);
		html = html.substr(0, html.length - 6);
		html = html.replace(/%NEWID%/g, id);

		var $e = $('<div>');

		$e.attr('id', field + '_div[' + id + ']').attr('data-id', id).data('id', id).addClass('dynamic-row')
			.html(html)
			.insertBefore($anc);

		$e.find(':checkbox,:radio').diControls();

		$(_ge(field + '_order_num[' + id + ']')).val(- id);

		if (Math.abs(id) == 1)
		{
			$(_ge(field + '_by_default[' + id + ']')).prop('checked', true);
		}

		if (admin_form)
		{
			$e.find('[data-purpose="color-picker"]').each(function() {
				admin_form.setupColorPicker($(this));
			});
		}

		$('html, body').animate({
			scrollTop: $e.offset().top - 5
		});

		if (opts.afterAddRow)
		{
			opts.afterAddRow(this, $e, id);
		}

		return false;
	};

	this.remove = function(field, id)
	{
		if (!this.is_field_inited(field))
		{
			return false;
		}

		if (!confirm('Удалить ' + this.field_titles[field] + '?'))
		{
			return false;
		}

		$(_ge(field + '_div[' + id + ']')).remove();

		if (opts.afterDelRow)
		{
			opts.afterDelRow(this, id);
		}

		return false;
	};

	constructor();
};
