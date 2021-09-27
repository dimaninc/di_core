var diAdminForm = function(table, id, auto_save_timeout) {
	var extensions = {
	    pic: ['jpeg', 'jpg', 'png', 'gif', 'svg']
    };
    var self = this,
        initiating = true,
		Tabs;

	this.table = table;
	this.id = ~~id;
	this.auto_save_timeout = ~~auto_save_timeout;
	this.timer_id = false;
	this.able_to_leave_page = true; // false
    this.language = $('body').data('language');

    var local = {
        ru: {
            field_href: 'Ссылка',
            field_slug_source: 'Название для URL'
        },

        en: {
            field_href: 'Href',
            field_slug_source: 'Slug source'
        }
    };

	this.e = {
	    $window: $(window),
		form: document.forms[table + '_form']
	};

	function init() {
		$(self.e.form).find('button#btn-clone').click(function() {
			$(self.e.form).find('input[name="id"]').val(0);
			$(self.e.form).submit();
		});

        $(self.e.form).find('button#btn-create-and-add-another').click(function() {
            $(self.e.form).find('input[name="__redirect_to"]').val('form');
            $(self.e.form).submit();
        });

        if (self.auto_save_timeout && self.id) {
			self.timer_id = setInterval(function() {
				self.auto_save();
			}, self.auto_save_timeout * 1000);
		}

        if (!self.id) {
            setTimeout(function() {
                $(self.e.form).find('input')
                    .filter('[type="text"],[type="email"],[type="tel"],[type="number"]')
                    .add('textarea').first().focus();
            }, 100);
        }

		self.clear_busy();

		initTabs();
		initColorPickers();
        initTypeChange();
		initDelLinks();
        self.initPicHolders()
        .initRotateAndWaterMarkLinks()
        .initFileInputs()
        .initCheckboxesToggles()
        .initFieldMaxLength();

        initiating = false;
	}

    this.L = function(name) {
        return typeof local[this.getLanguage()][name] !== 'undefined'
            ? local[this.getLanguage()][name]
            : name;
    };

	this.getLanguage = function() {
	    return this.language;
    };

	this.isMobile = function() {
	    return this.e.$window.width() < 768;
    };

	this.initFieldMaxLength = function($inputs) {
	    $inputs = $inputs || $('[data-max-length]');

	    $inputs.each(function() {
	        var $i = $(this);
	        var max = $i.data('max-length');
	        var $row = $i.closest('.diadminform-row');
	        var $title = $row.find('label');

	        var $counter = $('<span class="max-len-counter" />');
	        $counter.appendTo($title);

	        $i.on('input', function() {
	            var left = max - ($i.val() || '').length;
	            $counter.html(left).toggleClass('over', left <= 2);
            }).trigger('input');
        });

	    return this;
    };

	this.initCheckboxesToggles = function() {
	    var $rows = $('.diadminform-row'); //[data-type="checkboxes"]

	    $rows.each(function() {
	        var $row = $(this);
	        var $on = $row.find('.tags-toggle > span[data-purpose="toggle-on"]');
            var $off = $row.find('.tags-toggle > span[data-purpose="toggle-off"]');
            var $checkboxes = $row.find('.tags-grid input[type="checkbox"]');

            $on.on('click', function() {
                $checkboxes.prop('checked', true);
            });

            $off.on('click', function() {
                $checkboxes.prop('checked', false);
            });
        });

	    return this;
    };

    this.initPicHolders = function() {
                                        //:not(.no-zoom-feature)
        $('.existing-pic-holder .container').on('click', function() {
            if (self.isMobile() || !$(this).hasClass('no-zoom-feature')) {
                $(this).toggleClass('img-full-size');
            }
        });

        return this;
    };

    this.initFileInputs = function() {
        $('.diadminform-row .value').on('change', 'input[type="file"]', function() {
            var xhttp = new XMLHttpRequest();

            function sendFileChunk(file, tmpFilename, chunkSize, chunkIdx, chunksCount, resetInput) {
                if (chunkIdx < chunksCount) {
                    var offset = chunkIdx * chunkSize;
                    xhttp.onreadystatechange = function () {
                        if (this.readyState == 4 && this.status == 200) {
                            chunkIdx++;
                            sendFileChunk(file, tmpFilename, chunkSize, chunkIdx, chunksCount, resetInput);
                        }
                    };
                    xhttp.open('POST', di.getWorkerPath('files', 'chunk_upload') +
                        '?table=' + self.table +
                        '&field=' + field +
                        '&tmp_filename=' + di.urlencode(tmpFilename), true);
                    xhttp.setRequestHeader('Content-Type', 'application/octet-stream');
                    xhttp.send(file.slice(offset, offset + chunkSize));
                } else {
                    resetInput();
                }
            }

            var $inp = $(this);
            var field = $inp.attr('name');
            var chunk = $inp.data('chunk');
            var $wrapper = $inp.closest('.file-input-wrapper');
            var $existingPreviewArea = $wrapper.siblings('.existing-pic-holder');
            if (!$existingPreviewArea.length) {
                $existingPreviewArea = $wrapper.parent().siblings('.existing-pic-holder');
            }
            var $previewArea;
            var ext;
            var isPic;
            var isSvg;

            var getPreviewArea = function() {
                if (!$previewArea) {
                    $previewArea = $('<div class="existing-pic-holder"/>').insertBefore($wrapper);
                }

                return $previewArea;
            };

            if (this.files.length) {
                if (chunk) {
                    var file = this.files[0];
                    var chunkSize = chunk;
                    var chunksCount = Math.ceil(file.size / chunkSize, chunkSize);
                    var chunkIdx = 0;
                    var tmpFilename = (new Date().getTime()) + '-' + get_unique_id(16) + '.tmp';
                    var $inpUploaded = $('input[name="__uploaded__{0}"]'.format(field));
                    var $inpOrigFilename = $('input[name="__orig_filename__{0}"]'.format(field));

                    $inpUploaded.val(tmpFilename);
                    $inpOrigFilename.val(basename(this.value));

                    var resetInput = function() {
                        $wrapper.removeAttr('data-progress');

                        try {
                            $inp[0].value = '';
                            if ($inp[0].value) {
                                $inp[0].type = 'text';
                                $inp[0].type = 'file';
                            }
                        } catch (e) {
                            console.log('Unable to reset input after upload: ', e);
                        }
                    };

                    sendFileChunk(file, tmpFilename, chunkSize, chunkIdx, chunksCount, resetInput);

                    $wrapper.attr('data-progress', '+');
                }

                $wrapper
                    .addClass('selected')
                    .attr('data-caption', basename(this.value));

                ext = get_file_ext(this.value || '').toLowerCase();
                isPic = in_array(ext, extensions.pic);
                isSvg = ext === 'svg';

                if (isPic) {
                    for (var i in this.files) {
                        if (this.files.hasOwnProperty(i)) {
                            var $row = $('<div class="container embed no-bottom-margin"><img src=""></div>');

                            $existingPreviewArea.remove();

                            getPreviewArea()
                                .append($row);

                            (function($row, file, isSvg) {
                                var reader = new FileReader();

                                reader.onload = function(e) {
                                    var $img = $row.find('img');

                                    $img.attr('src', e.target.result);
                                    if (isSvg) {
                                        $img.css({
                                            width: '200px'
                                        });
                                    }
                                };
                                reader.readAsDataURL(file);
                            })($row, this.files[i], isSvg);
                        }
                    }
                }
            }
        });

        return this;
    };

    this.initRotateAndWaterMarkLinks = function() {
        $('.existing-pic-holder a.rotate-pic, .existing-pic-holder a.watermark-pic').on('click', function() {
            var $this = $(this);
            var $h = $this.closest('.existing-pic-holder');
            var $c = $h.find('.container');
            var $img = $c.find('img');

            $c.addClass('freeze');

            if (confirm($this.data('confirm'))) {
                $.get($this.attr('href'), function(res) {
                    if (res.ok) {
                        $img.attr('src', $img.attr('src').replace(/\?.+$/, '') + '?' + new Date().getTime());
                        setTimeout(function() {
                            $c.removeClass('freeze');
                        }, 500);
                    } else {
                        $c.removeClass('freeze');
                        alert(res.message || 'Error: no such record');
                    }
                });
            }

            return false;
        });

        return this;
    };

	function initTabs() {
		Tabs = new diTabs({
			$tabsContainer: $('.diadminform_tabs ul'),
			$pagesContainer: $('form [data-purpose="tab-pages"]')
		})
	}

	this.getTabs = function() {
		return Tabs;
	};

	function initDelLinks() {
		$('a.del-file').click(function() {
			var $this = $(this);
			var $row = $this.closest('.dynamic-row');
			var mainField = $row.data('main-field');

			if (confirm($this.data('confirm'))) {
				$.get($this.attr('href'), {redirect: 0}, function(res) {
					if (res.ok) {
						var $e;

						if (res.field && res.type === 'dynamic') {
						    // dynamic rows
                            $e = $('.diadminform-row[data-field="{0}"] .dynamic-row[data-id="{1}"] .existing-pic-holder[data-field="{2}"]'.format(mainField, res.subId, res.field));
                        } else if (res.subId) {
						    // pic with sub-id
                            $e = $('.diadminform-row[data-field="{0}"] .existing-pic-holder[data-sub-id="{1}"]'.format($this.data('field'), res.subId));
                        } else {
                            $e = $('.diadminform-row[data-field="{0}"] .existing-pic-holder'.format($this.data('field')));
                        }

						$e.remove();
					} else {
						alert(res.message || 'Error: no such record');
					}
				});
			}

			return false;
		});
	}

	this.setupColorPicker = function($colorPicker) {
		var $hidden = $('input:hidden[name="' + $colorPicker.data('field') + '"]'),
			$view = $('[data-purpose="color-view"][data-field="' + $colorPicker.data('field') + '"]'),
			saveColor = function(hex, close) {
				$hidden.val('#' + hex);
				$view.css('background', '#' + hex);

				if (close)
				{
					$colorPicker.slideUp();
				}
			};

		$colorPicker.ColorPicker({
			color: $hidden.val(),
			flat: true,
			onSubmit: function(hsb, hex, rgb, el) {
				saveColor(hex, true);
			},
			onChange: function(hsb, hex, rgb) {
				saveColor(hex, false);
			}
		});
	};

	function initColorPickers() {
	    $(self.e.form).on('click', '[data-purpose="color-view"]', function() {
		    $('[data-purpose="color-picker"][data-field="{0}"]'.format($(this).data('field'))).slideToggle();
	    });

		$('[data-purpose="color-picker"]:not([data-field$="\%NEWID\%\]"])').each(function() {
			self.setupColorPicker($(this));
		});
	}

	this.getFieldTitle = function(field, options) {
	    options = options || {};

	    switch (field) {
            case 'menu_title':
                return options.href
                    ? this.L('field_href')
                    : this.L('field_slug_source');

            default:
                return null;
        }
    };

    function initTypeChange() {
	    var $type = $('select[name="type"],input:hidden[name="type"]');

        function typeOnChange()
        {
            var type = $type.val(),
	            $title = $('[name="title"]'),
	            $menuTitle = $('[name="menu_title"]'),
                $hideForTypeFields = $('[data-hide-for-type]'),
                is_href = in_array(type, ['href', 'nohref']);

            // toggling fields for different content types
            $hideForTypeFields.each(function() {
                var $e = $(this),
                    $wrapper = $e.closest('.diadminform-row'),
                    types = $e.data('hide-for-type');

                $wrapper.toggleClass('display-none', in_array(type, types));
            });

            $('.diadminform-row').filter('[data-field="html_title"],[data-field="html_description"],[data-field="html_keywords"]').toggle(!is_href);

	        $('.diadminform-row[data-field="menu_title"]')
		        .toggle(!in_array(type, ['nohref']))
		        .find('.title')
                    .text(self.getFieldTitle('menu_title', {href: is_href}));

            if (!is_href && ~~$('[name="id"]').val() === 0 && !initiating)
            {
                if (!$title.val())
                {
                    $title.val($(this).find('option:selected').text().replace(/\s*\/\/\/([A-Za-z\-_0-9])+$/i, ''));
                }

                if (!$menuTitle.val())
                {
	                $menuTitle.val(type.replace(/_/g, ' '));
                }
            }
        }

        if (in_array(self.table, ['content']))
        {
            $type.change(typeOnChange);

            typeOnChange();
        }
    }

	// old shit. todo: refactor this
  this.is_able_to_leave_page = function() {
    return this.able_to_leave_page;
  };

  this.set_able_to_leave_page = function(state) {
    this.able_to_leave_page = state;
  };

  this.set_status = function(message) {
    window.A.console.add(message);
  };

  this.cancel = function(href_ending) {
    if (confirm(this.get_cancel_message())) {
      this.set_able_to_leave_page(true);
      location.href = 'index.php?path='+this.table+(typeof href_ending !== 'undefined' ? href_ending : '');
    }
  };

  this.get_cancel_message = function() {
    return 'All unsaved data will be lost. Are you sure?';
  };

  this.instant_submit = function()
  {
    this.e.form.target = 'save_frame_'+this.table+'_'+this.id;
    this.e.form.redirect_after_submit.value = '';

    this.e.form.submit();

    this.e.form.target = '';
    this.e.form.redirect_after_submit.value = '1';
  };

  this.set_busy = function(status_message1, status_message2, error_message)
  {
    if (this.busy.state)
    {
      alert(this.busy.error_message);

      return false;
    }

    this.busy = {
      state: true,
      error_message: error_message,
      status_message1: status_message1,
      status_message2: status_message2
    };

    this.set_status(this.busy.status_message1);

    return true;
  };

  this.clear_busy = function()
  {
    this.busy = {
      state: false,
      error_message: '',
      status_message1: '',
      status_message2: ''
    };
  };

  this.quick_save = function()
  {
    if (!this.id)
    {
      alert('Apply feature is working only on existing records yet');

      return false;
    }

    if (!this.set_busy('Quick saving...', 'Saved.', 'Quick saving is in progress'))
      return false;

    this.instant_submit();
  };

  this.auto_save = function()
  {
    if (!this.id)
      return false;

    if (!this.set_busy('Auto saving...', 'Saved (auto).', 'Auto-saving is in progress'))
      return false;

    this.instant_submit();
  };

  this.loaded = function()
  {
    this.set_status(this.busy.status_message2);

    this.clear_busy();
  };

  this.switch_to_edit_mode = function()
  {
    var ar = parse_uri_params(window.location.href, '?');
    ar.edit = 1;
    window.location.href = 'index.php?'+serialize_uri(ar);
  };

  this.cancel_click = function()
  {
    window.location.href = 'index.php?path='+this.table;
  };

  this.check_password = function(field)
  {
    var console = _ge(field+'_console');
    var password_needed = this.id ? false : true;

    if (this.e.form[field].value.length == 0 && password_needed)
    {
      console.innerHTML = 'Please enter password';
      this.e.form[field].style.backgroundColor = '#ffc';
      this.e.form[field+'2'].style.backgroundColor = '#ffc';
      password_ok = false;
    }
    else if (this.e.form[field].value != this.e.form[field+'2'].value)
    {
      console.innerHTML = 'Passwords don\'t match';
      this.e.form[field].style.backgroundColor = '#fcc';
      this.e.form[field+'2'].style.backgroundColor = '#fcc';
      password_ok = false;
    }
    else
    {
      console.innerHTML = '';
      this.e.form[field].style.backgroundColor = '#fff';
      this.e.form[field+'2'].style.backgroundColor = '#fff';
      password_ok = true;
    }

    if (typeof manage_submit_btn === 'function')
      manage_submit_btn();
  };

	this.color_select_onchange = function(field, freeze_input)
	{
		var sel = _ge(field+'_select__');
		var img = _ge(field+'_img__');
		var inp = _ge(field);

		if (img && inp)
		{
			if (sel && !freeze_input) inp.value = sel.value;

			if (inp.value && /^\#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(inp.value))
			{
				var s = inp.value;
				if (s[0] !== '#') s = '#'+s;

				img.style.backgroundColor = s;
				img.style.visibility = 'visible';
			}
			else
			{
				img.style.visibility = 'hidden';
			}
		}
	};

	init();
};
