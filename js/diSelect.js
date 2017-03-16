var diSelect = function(_opts)
{
	var self = this,
		e = {
			$blocks: null
		},
		opts = $.extend({
			selector: '.di-select' // string selector or dom/jquery object
		}, _opts || {});

	function constructor()
	{
		initBlocks();
	}

	function initBlocks()
	{
		e.$blocks = opts.selector instanceof jQuery ? opts.selector : $(opts.selector);

		e.$blocks.each(function() {
			var $block = $(this),
				$sel = $('ul', $block),
				$options = $('li', $sel),
				$value = $('.value', $block),
				$input = $('input:hidden', $block),
				name = $block.data('name'),
				value = $block.data('value');

			var setValue = function(id)
			{
				id = parseInt(id) || 0;

				$input.val(id);
				$block.attr('data-value', id).data('value', id);

				$value.html(!id ? $block.data('placeholder') : $options.filter('[data-id="{0}"]'.format(id)).html());

				$block
					.removeClass('choose');
			};

			var chooseValues = function()
			{
				$block.toggleClass('choose');
			};

			if ($value.length == 0)
			{
				$value = $('<div class="value"></div>').prependTo($block);
			}

			if ($input.length == 0)
			{
				$input = $('<input type="hidden" name="{0}" value="{1}">'.format(name, value)).prependTo($block);
			}

			$value.on('click', function(event) {
				chooseValues();
			});

			$options.on('click', function() {
				setValue($(this).data('id'));
			});

			setValue(value);
		});
	}

	constructor();
};