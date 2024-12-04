(function ($) {
	var transitions = {
		DEFAULT: 0,
		CROSSFADE: 1,
		SLIDE_L2R: 2,
		SLIDE_R2L: 3,
		SLIDE_T2B: 4,
		SLIDE_B2T: 5
	};

	var transition_styles = {
		DEFAULT: 0,
		BOTH_SLIDING: 1,
		ONLY_NEW_SLIDING: 2
	};

	var slides_mode = {
		SCREEN: 0, // old approach: pic/title/content shown on 'screen'
		SELF: 1 // new approach: each slide gets treated as a 'screen'
	};

	var slide_index = {
		OLD: 1,
		NEW: 2
	};

	$.fn.diCrossfade = function (settings) {
        settings = $.extend({
            $slide: null,
            href: null,
            hrefTarget: null,
            pic: null,
            transition: transitions.CROSSFADE,
            transition_style: transition_styles.BOTH_SLIDING,
            reverse_animation: false,
            duration: 2000
        }, settings || {});

        var mode = settings.pic ? slides_mode.SCREEN : slides_mode.SELF;

        // if set to 'default'
        settings.transition_style = settings.transition_style || transition_styles.BOTH_SLIDING;

        if (settings.reverse_animation) {
            switch (settings.transition) {
                case transitions.SLIDE_B2T:
                    settings.transition = transitions.SLIDE_T2B;
                    break;
                case transitions.SLIDE_T2B:
                    settings.transition = transitions.SLIDE_B2T;
                    break;
                case transitions.SLIDE_L2R:
                    settings.transition = transitions.SLIDE_R2L;
                    break;
                case transitions.SLIDE_R2L:
                    settings.transition = transitions.SLIDE_L2R;
                    break;
            }
        }

        function reset_href($e) {
            if ($e.get(0).tagName.toUpperCase() == 'A') {
                $e.attr('href', '#');
            } else {
                $e.css({cursor: 'default'}).off('click.trequartista');
            }
        }

        function set_href($e, href, hrefTarget) {
            if (!href) {
                $e.css({cursor: 'default'});

                return false;
            }

            hrefTarget = hrefTarget || '_self';

            if ($e.get(0).tagName.toUpperCase() == 'A') {
                $e.attr('href', href).attr('target', hrefTarget);
            } else {
                (function ($e, href, hrefTarget) {
                    $e
                        .css({cursor: 'pointer'})
                        .on('click.trequartista', function () {
                            if (hrefTarget != '_self') {
                                window.open(href, hrefTarget);
                            } else {
                                window.location.href = href;
                            }

                            return false;
                        });
                })($e, href, hrefTarget);
            }
        }

        function dasAnimator($element, properties, duration, callback) {
            if ($.support.transition && $.transit) {
                $element.transit(properties, duration, callback);
            } else {
                $element.animate(properties, duration, callback);
            }
        }

        return this.each(function () {

            function getSlideElement(idx) {
                var $e;

                switch (mode) {
                    default:
                    case slides_mode.SCREEN:
                        $e = $('<div></div>').css({
                            backgroundPosition: 'center center',
                            backgroundImage: idx == slide_index.OLD
                                ? $this.css('background-image')
                                : 'url(' + settings.pic + ')',
                            backgroundSize: 'cover'
                        });
                        break;

                    case slides_mode.SELF:
                        $e = idx == slide_index.OLD
                            ? $('<div></div>').append($this.children().clone())
                            : settings.$slide.clone();
                        break;
                }

                $e.css(css_ar2).css(get_initial_positions(idx));

                return $e;
            }

            var $this = $(this),
                $helper = $this.clone().attr('id', ''),
                w = $this.width(), // 100%
                h = $this.height(), // 100%

                css_ar = {
                    display: 'block',
                    position: 'absolute',
                    //margin: 0,
                    //padding: 0,
                    zIndex: (parseInt($this.css('z-index'), 10) || 0) + 1,
                    width: w,
                    height: h
                },

                css_ar2 = {
                    display: 'block',
                    position: 'absolute',
                    inset: 0,
                    //margin: 0,
                    //padding: 0,
                    width: w,
                    height: h
                };

            if (mode == slides_mode.SELF) {
                $helper.empty();
            }

            switch (settings.transition) {
                case transitions.DEFAULT:
                case transitions.CROSSFADE:

                    $.extend(css_ar, {
                        opacity: 0
                    });

                    switch (mode) {
                        default:
                        case slides_mode.SCREEN:
                            $.extend(css_ar, {
                                backgroundImage: 'url(' + settings.pic + ')'
                            });
                            break;

                        case slides_mode.SELF:
                            $helper.append(getSlideElement(slide_index.NEW));
                            break;
                    }

                    break;

                case transitions.SLIDE_L2R:
                case transitions.SLIDE_R2L:
                case transitions.SLIDE_T2B:
                case transitions.SLIDE_B2T:

                    var $slideContainer = $('<div></div>'),
                        $oldSlide = getSlideElement(slide_index.OLD),
                        $newSlide = getSlideElement(slide_index.NEW);

                    $slideContainer.css($.extend(css_ar2, {
                        width: w * 2,
                        height: h * 2
                    }));

                    $.extend(css_ar, {
                        overflow: 'hidden'
                    });

                    if (settings.transition_style == transition_styles.BOTH_SLIDING) {
                        $helper.append($slideContainer.append($oldSlide).append($newSlide));
                    } else if (settings.transition_style == transition_styles.ONLY_NEW_SLIDING) {
                        $helper.append($newSlide);
                    }

                    break;
            }

            $helper.css(css_ar).insertBefore($this);

            reset_href($helper);
            set_href($helper, settings.href, settings.hrefTarget);
            reset_href($this);
            set_href($this, settings.href, settings.hrefTarget);

            switch (settings.transition) {
                case transitions.DEFAULT:
                case transitions.CROSSFADE:

                    dasAnimator($helper, {opacity: 1}, settings.duration, function () {
                        after_transition();
                    });

                    break;

                case transitions.SLIDE_L2R:
                case transitions.SLIDE_R2L:
                case transitions.SLIDE_T2B:
                case transitions.SLIDE_B2T:

                    var $obj;

                    if (settings.transition_style == transition_styles.BOTH_SLIDING) {
                        $obj = $slideContainer;
                    } else if (settings.transition_style == transition_styles.ONLY_NEW_SLIDING) {
                        $obj = $newSlide;
                    }

                    dasAnimator($obj, get_animation(), settings.duration, function () {
                        after_transition();
                    });

                    break;
            }

            function after_transition() {
                switch (mode) {
                    default:
                    case slides_mode.SCREEN:
                        $this.css({
                            backgroundImage: 'url(' + settings.pic + ')'
                        });
                        break;

                    case slides_mode.SELF:
                        $this.empty();
                        $helper.children().appendTo($this);
                        break;
                }

                $helper.remove();
            }

            function get_initial_positions(slideIdx) {
                var ar = {
                    left: 0,
                    top: 0
                };

                switch (settings.transition) {
                    case transitions.SLIDE_L2R:
                        ar.marginLeft = slideIdx == slide_index.OLD ? 0 : '-' + w;

                        break;

                    case transitions.SLIDE_R2L:
                        ar.marginLeft = slideIdx == slide_index.OLD ? 0 : w;

                        break;

                    case transitions.SLIDE_T2B:
                        ar.marginTop = slideIdx == slide_index.OLD ? 0 : '-' + h;

                        break;

                    case transitions.SLIDE_B2T:
                        ar.marginTop = slideIdx == slide_index.OLD ? 0 : h;

                        break;
                }

                return ar;
            }

            function get_animation() {
                var ret = {};

                switch (settings.transition) {
                    case transitions.SLIDE_L2R:
                        if (settings.transition_style == transition_styles.BOTH_SLIDING)
                            ret.marginLeft = w;
                        else if (settings.transition_style == transition_styles.ONLY_NEW_SLIDING)
                            ret.marginLeft = 0;

                        break;

                    case transitions.SLIDE_R2L:
                        if (settings.transition_style == transition_styles.BOTH_SLIDING)
                            ret.marginLeft = '-' + w;
                        else if (settings.transition_style == transition_styles.ONLY_NEW_SLIDING)
                            ret.marginLeft = 0;

                        break;

                    case transitions.SLIDE_T2B:
                        if (settings.transition_style == transition_styles.BOTH_SLIDING)
                            ret.marginTop = h;
                        else if (settings.transition_style == transition_styles.ONLY_NEW_SLIDING)
                            ret.marginTop = 0;

                        break;

                    case transitions.SLIDE_B2T:
                        if (settings.transition_style == transition_styles.BOTH_SLIDING)
                            ret.marginTop = '-' + h;
                        else if (settings.transition_style == transition_styles.ONLY_NEW_SLIDING)
                            ret.marginTop = 0;

                        break;
                }

                return ret;
            }

        });

    }

}(jQuery));
