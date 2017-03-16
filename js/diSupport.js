/**
 * Created by dimaninc on 30.07.2015.
 */

var diSupport = {
	cache: {},

	check: function(what)
	{
		if (typeof this.cache[what] == 'undefined')
		{
			this.cache[what] = this[what]();
		}

		return this.cache[what];
	},

	flash: function()
	{
		return typeof navigator.plugins == 'undefined' || navigator.plugins.length == 0
			? !!(new ActiveXObject('ShockwaveFlash.ShockwaveFlash'))
			: navigator.plugins['Shockwave Flash'];
	},

	transitions: function()
	{
		var b = document.body || document.documentElement,
			s = b.style,
			p = 'transition';

		if (typeof s[p] == 'string')
		{
			return true;
		}

		var v = ['Moz', 'webkit', 'Webkit', 'Khtml', 'O', 'ms'];
		p = p.charAt(0).toUpperCase() + p.substr(1);

		for (var i = 0; i < v.length; i++)
		{
			if (typeof s[v[i] + p] == 'string')
			{
				return true;
			}
		}

		return false;
	},

	transform3d: function()
	{
		if (!window.getComputedStyle)
		{
			return false;
		}

		var el = document.createElement('p'),
			has3d,
			transforms = {
				'webkitTransform': '-webkit-transform',
				'OTransform': '-o-transform',
				'msTransform': '-ms-transform',
				'MozTransform': '-moz-transform',
				'transform': 'transform'
			};

		document.body.insertBefore(el, null);

		for (var t in transforms)
		{
			if (el.style[t] !== undefined)
			{
				el.style[t] = 'translate3d(1px,1px,1px)';
				has3d = window.getComputedStyle(el).getPropertyValue(transforms[t]);
			}
		}

		document.body.removeChild(el);

		return has3d !== undefined && has3d.length > 0 && has3d !== "none";
	},

	html5: function()
	{
		var elem = document.createElement('canvas');

		return elem.getContext && elem.getContext('2d');
	},

	audio: function()
	{
		var a = document.createElement('audio'),
			ar = [];

		if (a.canPlayType)
		{
			if (a.canPlayType('audio/mpeg')) ar.push('mp3');
			if (a.canPlayType('audio/ogg; codecs="vorbis"')) ar.push('ogg');
			if (a.canPlayType('audio/mp4; codecs="mp4a.40.5"')) ar.push('aac');
		}

		return ar.join(',');
	}
};
