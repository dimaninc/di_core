var textareaTuneHeight = function(element) {
	return jQuery(element)
		.css({ 'height': 'auto', 'overflow-y': 'hidden' })
		.height(element.scrollHeight);
};

jQuery.fn.extend({
	autoHeight: function () {
		return this.each(function() {
			textareaTuneHeight(this).on('input', function() {
				textareaTuneHeight(this);
			});
		});
	}
});