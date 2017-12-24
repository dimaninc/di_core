// (c) 2013-2014 dimaninc
// dimaninc@gmail.com, http://dalance.ru/users/dimaninc/

/* required: jquery.dicrossfade.js */

// changes history:
// 2014-10-23 random order support added
// 2014-10-03 some improvements

(function($) {

	$.fn.diTrequartista = function(settings) {

		// slides order
		var slides_order = {
			IN_ORDER: 0,
			RANDOM: 1
		};

		var random_algorithm = {
			SHUFFLE_ON_START: 0,
			EACH_TIME_PICK: 1
		};

		settings = $.extend({
		    adjust_buttons_height: $(this).data('adjust-buttons-height') || false,
			screen_selector: $(this).data('screen-selector') || '[data-role="screen"]',
			buttons_selector: $(this).data('buttons-selector') || '[data-role="button"]',
			inner_button_selector: $(this).data('inner-button-selector') || 'em',
			title_selector: $(this).data('title-selector') || '[data-role="block-title"]',
			content_selector: $(this).data('content-selector') || '[data-role="block-content"]',
			selected_class: $(this).data('selected-class') || 'selected',

			$prev: $(this).data('prev-selector') || '[data-role="prev"]',
			$next: $(this).data('next-selector') || '[data-role="next"]',

			prev_reverse_animation: $(this).data('prev-reverse-animation') || false,

			afterChange: function($u, $screen, $block) {},

			transition: $(this).data('transition') || 0,
			transition_style: $(this).data('transition-style') || 0,
			slides_order: $(this).data('slides-order') || slides_order.IN_ORDER,
			random_algorithm: $(this).data('random-algorithm') || random_algorithm.SHUFFLE_ON_START,
			ignore_hover_hold: $(this).data('ignore-hover-hold') || 0,

			auto_play: $(this).data('auto-play'),
			duration_of_change: $(this).data('duration-of-change') || 1000,
			duration_of_autochange: $(this).data('duration-of-autochange') || $(this).data('duration-of-change') || 1000,
			duration_of_show: $(this).data('duration-of-show') || 0
		}, settings || {});

		if (settings.auto_play === null)
		{
			settings.auto_play = true;
		}

		return this.each(function() {

			var timer_id,

				selected_idx = -1,
				auto = true,
				slides_ar = {},

				$container = $(this),
				$screen = $(settings.screen_selector, $container),
				$buttons = $(settings.buttons_selector, $container),
				$title = $(settings.title_selector, $container),
				$content = $(settings.content_selector, $container),

				$prev = settings.$prev && typeof settings.$prev == 'string' ? $container.find(settings.$prev) : settings.$prev,
				$next = settings.$next && typeof settings.$next == 'string' ? $container.find(settings.$next) : settings.$next,

				each_height = Math.floor(($screen.height() - 4) / $buttons.length),
				last_height = ($screen.height() - 4) - each_height * ($buttons.length - 1);

			if ($container.data('ditrequartista-inited'))
			{
			    console.log($container.attr('id'), 'diTrequartista already initiated');

				return true;
			}

			$container.data('ditrequartista-inited', true);

			if (!$screen.length)
			{
				$screen = $container;
			}

			function constructor()
			{
			    // making vertical buttons fill whole column
			    if (settings.adjust_buttons_height)
			    {
				    $(settings.inner_button_selector, $buttons).css({height: each_height})
					    .filter(':last').css({height: last_height});
			    }

				if (
					settings.slides_order == slides_order.RANDOM &&
					settings.random_algorithm == random_algorithm.SHUFFLE_ON_START
				   )
				{
					//console.log(typeof $buttons, typeof []);
					$buttons = shuffle($buttons);
				}

				$buttons.each(function() {
					var $slide = $(this);

					// precache
					if ($slide.data('pic'))
					{
						var $img = $('<img />').attr('src', $slide.data('pic'));
					}

					slides_ar[$buttons.index(this)] = {
						$slide: $slide,

						pic: $slide.data('pic'),
						href: $slide.attr('href') || $slide.data('href'),
						hrefTarget: $slide.data('href-target'),

						title: $slide.data('title') || '',
						content: $slide.data('content') || '',

						transition: $slide.data('transition')*1 || false,
						transition_style: $slide.data('transition-style')*1 || false,

						duration_of_change: $slide.data('duration-of-change')*1 || false,
						duration_of_autochange: $slide.data('duration-of-autochange')*1 || $slide.data('duration-of-change')*1 || false,
						duration_of_show: $slide.data('duration-of-show')*1 || false
					};
				}).hover(function() {
					select($buttons.index(this), false);
				}, function() {
					set_auto(settings.auto_play);
				});

				if ($prev)
				{
					$prev.click(function() {
						select_prev();
					});
				}

				if ($next)
				{
					$next.click(function() {
						select_next();
					});
				}

				if (!settings.ignore_hover_hold)
				{
					$screen.hover(function() {
						set_auto(false);
					}, function() {
						set_auto(settings.auto_play);
					});
				}

				select(0, false, true);

				set_auto(settings.auto_play);
			}

			function set_auto(state)
			{
			    //console.log('set auto:', state);
			    //console.trace();

			    auto = !!state;

				if (auto)
				{
					set_timer_to_next();
				}
				else
				{
					stop_timer();
				}
			}

			function get_random_idx(idx)
			{
			    do
			    {
			        idx = Math.floor(Math.random() * $buttons.length);

			    	if (idx != selected_idx)
			    	{
						return idx;
					}
			    } while (true);
			}

			function get_next_idx(idx)
			{
			    // random
			    if (
				    settings.slides_order == slides_order.RANDOM &&
				    settings.random_algorithm == random_algorithm.EACH_TIME_PICK &&
				    $buttons.length > 1
			       )
			    {
			    	return get_random_idx(idx);
			    }

				idx = idx || selected_idx;
				idx++;

				if (idx > $buttons.length - 1)
				{
					idx = 0;
				}

				return idx;
			}

			function get_prev_idx(idx)
			{
			    // random
				if (
					settings.slides_order == slides_order.RANDOM &&
					settings.random_algorithm == random_algorithm.EACH_TIME_PICK &&
					$buttons.length > 1
				   )
			    {
			    	return get_random_idx(idx);
			    }

				idx = idx || selected_idx;
				idx--;

				if (idx < 0)
				{
					idx = $buttons.length - 1;
				}

				return idx;
			}

			function select_next(auto)
			{
				select(get_next_idx(), auto || false);
			}

			function select_prev(auto)
			{
				select(get_prev_idx(), auto || false, false, settings.prev_reverse_animation);
			}

			function set_timer_to_next()
			{
			    var durationOfShow = get_duration_of_show(get_next_idx());

			    //console.log('set timer to next, duration =', get_duration_of_show(get_next_idx()), 'id = ', get_next_idx());

			    if (durationOfShow > 0)
				{
					stop_timer();

					timer_id = setTimeout(function() {
						select_next(true);
					}, durationOfShow);
				}
			}

			function stop_timer()
			{
				clearTimeout(timer_id);
			}

			function select(idx, auto_selected, instant, reverse_animation)
			{
				if (!auto && auto_selected)
				{
					stop_timer();

					return false;
				}
				else if (!auto_selected)
				{
					set_auto(false);
				}

				if (idx == selected_idx)
				{
					return false;
				}

				$buttons.eq(idx).addClass(settings.selected_class);

				if (selected_idx >= 0)
				{
					$buttons.eq(selected_idx).removeClass(settings.selected_class);
				}

				$screen.stop(true, true).diCrossfade({
					$slide: slides_ar[idx].$slide,

					href: slides_ar[idx].href,
					hrefTarget: slides_ar[idx].hrefTarget,
					pic: slides_ar[idx].pic,

					transition: get_transition(idx),
					transition_style: get_transition_style(idx),

					reverse_animation: reverse_animation || false,

					duration: instant ? 0 : get_duration_of_change(idx)
				});

				if (auto_selected && settings.auto_play)
				{
					set_auto(true);
				}

				$title.html(slides_ar[idx].title);
				$content.html(slides_ar[idx].content);

				selected_idx = idx;

				if (settings.afterChange)
				{
					settings.afterChange($buttons.eq(idx), $screen, $container);
				}
			}

			function get_duration_of_change(idx)
			{
			    var v = 'duration_of_' + (auto ? 'autochange' : 'change');

				return slides_ar[idx][v] !== false && slides_ar[idx][v] != -1 ? slides_ar[idx][v] : settings[v];
			}

			function get_duration_of_show(idx)
			{
				return slides_ar[idx].duration_of_show !== false && slides_ar[idx].duration_of_show != -1 ? slides_ar[idx].duration_of_show : settings.duration_of_show;
			}

			function get_transition(idx)
			{
				return slides_ar[idx].transition || settings.transition;
			}

			function get_transition_style(idx)
			{
				return slides_ar[idx].transition_style || settings.transition_style;
			}

			constructor();

		});

	}

}(jQuery));
