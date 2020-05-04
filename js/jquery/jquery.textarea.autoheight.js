jQuery.fn.extend({
	autoHeight: function () {
		function worker(element) {
			return jQuery(element)
				.css({ 'height': 'auto', 'overflow-y': 'hidden' })
				.height(element.scrollHeight);
		}
		return this.each(function() {
			worker(this).on('input', function() {
				worker(this);
			});
		});
	}
});