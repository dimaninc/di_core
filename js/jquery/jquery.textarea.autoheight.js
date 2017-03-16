jQuery.fn.extend({
	autoHeight: function ()
	{
		function worker(element)
		{
			element.style.height = 'auto';
			element.style.height = element.scrollHeight + 'px';
		}

		return this.each(function() {
			this.setAttribute('style', 'height: ' + this.scrollHeight + 'px; overflow-y: hidden;');

			worker(this);

			jQuery(this).on('input', function() {
				worker(this);
			});
		});
	}
});