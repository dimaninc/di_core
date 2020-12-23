var diAdminConsole = function(_opts) {
	var self = this,
		lines = 0,
		opts = $.extend({
			highLightTimeout: 2,
			scrollTimeout: 1
		}, _opts || {}),
		e = {
			$container: $('.console-container'),
			$console: $('.console')
		};

	function constructor() {
		e.$container.find('.up,.down').click(function() {
			var sign = $(this).hasClass('up') ? -1 : 1;
			var diff = sign * e.$container.height();

			e.$container.stop(true, true).animate({
				scrollTop: e.$container.get(0).scrollTop + diff
			}, opts.scrollTimeout * 300);
		});
	}

	function getLineHtml(line)
	{
		var time = new Date().toTimeString().split(' ')[0];

		return sprintf('<div><i>%s</i><span>%s</span></div>', time, line);
	}

	function toggleNavy(state)
	{
		if (typeof state == 'undefined') {
			state = lines > 2;
		}

		e.$container.toggleClass('navy-needed', !!state);
	}

	this.add = function(line)
	{
		e.$console.append(getLineHtml(line));

		e.$container.stop(true, true).animate({
			scrollTop: e.$console.get(0).scrollHeight
		}, opts.scrollTimeout * 1000);

		lines++;
		toggleNavy();

		this.highlight();

		return this;
	};

	this.clear = function()
	{
		e.$console.html('');
		lines = 0;
		toggleNavy();

		return this;
	};

	this.set = function(line)
	{
		this.clear().add(line);

		return this;
	};

	this.highlight = function()
	{
		e.$container.addClass('highlighted');

		setTimeout(function() {
			e.$container.removeClass('highlighted');
		}, opts.highLightTimeout * 1000);

		return this;
	};

	constructor();
};