/**
 * Created by dimaninc on 04.05.2016.
 */

var di = {
	workerPrefix: '/_core/php/workers/',
	workerAdminPrefix: '/_core/php/admin/workers/',

	getWorkerBasePath: function(controller, action, params, options) {
		options = $.extend({
			underscoreSuffix: true
		}, options || {});
		suffixAr = [];

		if (controller)
		{
			if (di.isArray(controller) && !action && !params)
			{
				suffixAr = controller;
			}
			else
			{
				suffixAr.push(controller);

				if (action)
				{
					suffixAr.push(action);
				}

				if (params)
				{
					if (!di.isArray(params))
					{
						params = [params];
					}

					suffixAr = suffixAr.concat(params);
				}
			}
		}

		if (suffixAr)
		{
			if (options.underscoreSuffix) {
				suffixAr = suffixAr.map(di.underscore);
			}

			suffixAr.push('');
		}

		return suffixAr.join('/');

	},

	getWorkerPath: function(controller, action, params, options) {
		return di.workerPrefix + di.getWorkerBasePath(controller, action, params, options);
	},

	getAdminWorkerPath: function(controller, action, params, options) {
		return di.workerAdminPrefix + di.getWorkerBasePath(controller, action, params, options);
	},

	asap: function(whenToStart, whatToDo, delay, maxTries) {
		var opts = $.extend({
				whenToStart: whenToStart || function() {
					return true;
				},
				whatToDo: whatToDo || function() {
					return true;
				},
				maxTries: maxTries,
				delay: delay || 50
			}, typeof whenToStart == 'object' ? whenToStart : {}),
			interval,
			tries = 0;

		function start() {
			interval = setInterval(function() {
				if (opts.whenToStart()) {
					opts.whatToDo();
					stop();
				} else if (opts.maxTries && ++tries == opts.maxTries) {
					stop();
				}
			}, opts.delay);
		}

		function stop() {
			clearInterval(interval);
		}

		start();
	},

	wordWrap: function(str, width, separator, cut) {
		separator = separator || null;
		width = width || 75;
		cut = cut || false;

		if (!str)
		{
			return separator === null ? [str] : str;
		}

		var regex = '.{1,' + width + '}(\\s|$)' + (cut ? '|.{' + width + '}|.+$' : '|\\S+?(\\s|$)'),
			lines = str.match(RegExp(regex, 'g'));

		return separator === null ? lines : lines.join(separator);
	},

	trim: function(s) {
		return s.replace(/^\s+|\s+$/g, '');
	},

	ltrim: function(s) {
		return s.replace(/^\s+/, '');
	},

	rtrim: function(s) {
		return s.replace(/\s+$/, '');
	},

	nl2br: function(s) {
		return s.replace(/[\r\n]+/, '<br>\n');
	},

	underscore: function(s) {
		return (s + '').toUnderscore();
	},

	camelize: function(s) {
		return (s + '').toCamelCase();
	},

	urlencode: function (s) {
		return encodeURIComponent(s);
	},

	urldecode: function(s) {
		return decodeURIComponent((s + '').replace(/\+/g, '%20'));
	},

	escapeHtml: function (text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};

		return text.replace(/[&<>"']/g, function (m) {
			return map[m];
		});
	},

	round: function (number, precision) {
		if (!precision) {
			return Math.round(number);
		}

		var factor = Math.pow(10, precision);
		var tempNumber = number * factor;
		var roundedTempNumber = Math.round(tempNumber);

		return roundedTempNumber / factor;
	},

	supported: {
		advancedUploading: (function() {
			var div = document.createElement('div');
			return (('draggable' in div) || ('ondragstart' in div && 'ondrop' in div)) &&
				'FormData' in window && 'FileReader' in window;
		})()
	},

	isArray: function(ar) {
		return Object.prototype.toString.call(ar) === '[object Array]';
	},

	keys: function(array) {
		return $.map(array, function(val, key) { return key; });
	},

	values: function(array) {
		return $.map(array, function(val, key) { return val; });
	}
};
