(function($) {

$.fn.diControls = function()
{
	return this.each(function() {

		// !!! not working
		return true;

		var $inp = $(this);

		if ($inp.hasClass('direal') || ($inp.attr('id') || '').indexOf('%NEWID%') > -1)
			return true;

		var	checked_class = $inp.prop('checked') ? ' checked' : '',
			disabled_class = $inp.prop('disabled') ? ' disabled' : '',
			more_classes = $inp.attr('class') ? ' '+$inp.attr('class') : '',
			$visual = $('<div class="di'+$inp.attr('type')+checked_class+disabled_class+more_classes+'"></div>')
				.insertBefore($inp);

		// copying all attributes
		for (var att, i = 0, atts = $inp.get(0).attributes, n = atts.length; i < n; i++)
		{
		    if (in_array(atts[i].nodeName, ['class', 'checked', 'disabled', 'id', 'type']))
		    	continue;

			$visual.get(0).setAttribute(atts[i].nodeName, atts[i].value || atts[i].nodeValue);
		}

		$inp.click(function() {

			if (!$(this).prop('disabled'))
			{
		        // resetting 'checked' prop on all grouped radio buttons
		    	if ($inp.attr('type') == 'radio')
			    {
			    	$('.diradio[name="'+$inp.attr('name')+'"]').removeClass('checked');
			    }

				$visual.toggleClass('checked', $(this).prop('checked'));
			}

		}).addClass('direal');

		$visual.click(function() {

			$inp
				//.prop('checked', !$inp.prop('checked'))
				.trigger('click');

		});

	});
}

}(jQuery));
