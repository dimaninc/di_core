var diDynamicRows = function(opts) {
	var self = this,
		$anc,
		$src,
		$jsSrc,
		$wrapper,
		$formRow,
		$dropAreas,
		droppedFiles,
		$lastCreatedRow,
		lastCreatedRowId;

    var local = {
        ru: {
            passwords_not_match: 'Введенные пароли не совпадают',
            submit_multiple_upload: 'Загрузить выбранные фотографии на сервер'
        },

        en: {
            passwords_not_match: 'Entered passwords not match',
            submit_multiple_upload: 'Upload selected pics to server'
        }
    };

	opts = opts || {};

	this.counters = {};
	this.signs = {};
	this.field_titles = {};

	function constructor() {
		opts = $.extend({
			field: null,
			fieldTitle: null,
			sign: 1,
			counter: 0,
			sortable: false,
            language: 'ru',
			afterInit: function(DynamicRows) {},
			afterAddRow: function(DynamicRows, $row, id) {},
			afterDelRow: function(DynamicRows, id) {}
		}, opts);

		if (opts.field) {
			self.init(opts.field, opts.fieldTitle, opts.sign, opts.counter);
		}
	}

    this.L = function(name) {
        return typeof local[opts.language][name] !== 'undefined'
            ? local[opts.language][name]
            : name;
    };

	this.setupEvents = function() {
		var validatePassword = function() {
			var $password = $(this).parent().find('input[type="password"]:not(.password-confirm)'),
				$password2 = $(this).parent().find('input[type="password"].password-confirm');

			$password2.get(0).setCustomValidity($password.val() !== $password2.val()
				? self.L('passwords_not_match')
				: ''
			);
		};

		$anc.parent().on('change', 'input[type="password"]:not(.password-confirm)', validatePassword);
		$anc.parent().on('keyup', 'input[type="password"].password-confirm', validatePassword);

		return this;
	};

	function isDragAndDropSupported() {
		return $formRow.data('drag-and-drop-uploading') && di.supported.advancedUploading;
	}

	function setupMultipleUploads() {
		$dropAreas.each(function() {
			var $this = $(this);
			var $previewArea = $('<ul/>')
				.hide()
				.addClass('admin-form-uploading-area-preview')
				.insertAfter($this);
			var $submitArea = $('<div/>')
				.hide()
				.html('<button type="submit">{0}</button>'.format(self.L('submit_multiple_upload')))
				.addClass('admin-form-uploading-area-submit')
				.insertAfter($previewArea);
			var $inp = $('<input/>').attr({
				type: 'file',
				multiple: 'multiple',
				name: '__new_files[]'
			});

			$inp
				.appendTo($this)
				.on('change', function() {
					$previewArea.html('');

					if (this.files.length) {
						for (var i in this.files) {
							if (this.files.hasOwnProperty(i)) {
								var $row = $('<li><img src=""></li>');

								$previewArea
									.append($row);

								(function($row, file) {
									var reader = new FileReader();

									reader.onload = function(e) {
										$row.find('img').attr('src', e.target.result);
									};
									reader.readAsDataURL(file);
								})($row, this.files[i]);
							}
						}
					}

					$previewArea.fadeIn();
					$submitArea.fadeIn();
				});
		});
	}

	this.setupDragAndDropUploads = function() {
		if (isDragAndDropSupported()) {
			// todo: https://css-tricks.com/drag-and-drop-file-uploading/
			/*
			$formRow.addClass('has-advanced-uploading');

			$formRow.find('form')
				.on('drag dragstart dragend dragover dragenter dragleave drop', function (e) {
					e.preventDefault();
					e.stopPropagation();
				})
				.on('dragover dragenter', function () {
					$formRow.addClass('is-drag-over');
				})
				.on('dragleave dragend drop', function () {
					$formRow.removeClass('is-drag-over');
				})
				.on('drop', function (e) {
					droppedFiles = e.originalEvent.dataTransfer.files;
				});
			*/
		}

		return this;
	};

	this.createExistingPicHolder = function(imgUrl, $row) {
		$row = $row || this.getLastCreatedRow();
        var $inp = $row.find('input[type="file"]').first();
        // adding pic holder
        $inp.closest('div').before('<div class="existing-pic-holder"><div class="container embed"><img src="{0}" alt="pics"></div></div>'.format(imgUrl));
        // saving base64 encoded binary data to input
		var $base64Inp = $(
			'<input type="hidden" name="{0}" value="{1}">'.format('base64_' + $inp.attr('name'), imgUrl)
		).insertBefore($inp);

        return this;
	};

	this.addWithPicBase64 = function(base64) {
		this.add(opts.field);

		this.createExistingPicHolder(base64);

		return this;
	};

	this.setupPasteImage = function() {
		$(document).on('paste.didynamicrows', function(event) {
            var items = (event.clipboardData  || event.originalEvent.clipboardData).items;

            for (var i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') === -1) continue;

                var format = items[i].type;
                var blob = items[i].getAsFile();

                var mycanvas = document.createElement('canvas');
                var ctx = mycanvas.getContext('2d');

                var img = new Image();
                img.onload = function() {
                    mycanvas.width = this.width;
                    mycanvas.height = this.height;

                    ctx.drawImage(this, 0, 0);

					self.addWithPicBase64(mycanvas.toDataURL(format || 'image/png'));
                };

                var URLObj = window.URL || window.webkitURL;
                img.src = URLObj.createObjectURL(blob);
            }

            event.stopPropagation();
		});

		return this;
	};

	this.init = function(field, field_title, sign, counter) {
		this.counters[field] = typeof counter === 'undefined' ? 0 : ~~counter;
		this.field_titles[field] = field_title;
		this.signs[field] = typeof sign === 'undefined' || sign > 0 ? 1 : -1;

		// back compatibility
		opts.field = opts.field || field;
		opts.fieldTitle = opts.fieldTitle || field_title;
		opts.sign = opts.sign || sign;
		opts.counter = opts.counter || counter;

		$jsSrc = $('#js_' + opts.field + '_js_resource');
		$src = $('#js_' + opts.field + '_resource');
		$anc = $('#' + opts.field + '_anchor_div');
		$wrapper = $anc.parent();
		$formRow = $wrapper.closest('.diadminform-row');

		setTimeout(function() {
			$dropAreas = $formRow.find('.admin-form-uploading-area');

			setupMultipleUploads();
		}, 0);

		this
			.setupEvents()
			.setupDragAndDropUploads()
			.setupPasteImage();

		opts.afterInit && opts.afterInit(this);
		opts.sortable && this.setupSortable();

		return this;
	};

	this.recountOrderNumbers = function() {
		var num = 0;

		$wrapper.find('.dynamic-row').each(function() {
			var row = $(this);

			row.find('input[name*="order_num"]').val(++num);
		});

		return this;
	};

	this.setupSortable = function() {
		$wrapper.sortable({
			items: '.dynamic-row',
			placeholder: 'ui-state-highlight',
			/*
			helper: function(e, item) {
				var helper = item.clone();
				helper.addClass('di-sortable-helper').height('auto');
				return helper;
			},
			*/
			start: function(e, ui) {
				//console.log('start', ui);
				ui.item.height('auto');
				//ui.item.parent().addClass('sortable-active-container');
			},
			stop: function(e, ui) {
				//ui.item.parent().removeClass('sortable-active-container');
				self.recountOrderNumbers();
			}
		});

		return this;
	};

	this.refreshSortable = function() {
		$wrapper.sortable('refresh');

		return this;
	};

	this.getRows = function() {
		return $anc.parent().find('.dynamic-row');
	};

	this.is_field_inited = function(field) {
		return typeof this.counters[field] !== 'undefined';
	};

	this.getLastCreatedRow = function() {
		return $lastCreatedRow;
	};

    this.getLastCreatedRowId = function() {
        return lastCreatedRowId;
    };

	this.add = function(field) {
		if (!this.is_field_inited(field)) {
			console.log('diDynamicRows: field {0} not initialized'.format(field));

			return false;
		}

		if (!$src.length || !$anc.length) {
			console.log('diDynamicRows: no $src or $anc');

			return false;
		}

		this.counters[field] += this.signs[field];

		var id = - this.counters[field];
		var html = $src.html() || '';
		html = html.substr(html.indexOf('>') + 1);
		html = html.substr(0, html.length - 6);
		html = html.replace(/%NEWID%/g, id);

		var js = $jsSrc.html() || '';
		js = js.replace(/%NEWID%/g, id);

		var $e = $('<div />');
		var $eJs = $('<script type="text/javascript">' + js + '</script>');

		$e.attr('id', field + '_div[' + id + ']').attr('data-id', id).data('id', id).addClass('dynamic-row')
			.html(html)
			.insertBefore($anc);

		setTimeout(function() {
			$eJs.insertBefore($anc);
		}, 10);

		$e.find(':checkbox,:radio').diControls();

		$('#' + field + '_order_num\\[' + id + '\\]').val(-id);

		if (Math.abs(id) === 1) {
			$('#' + field + '_by_default\\[' + id + '\\]').prop('checked', true);
			$('input[type="radio"][name="{0}_default"][value="{1}"]'.format(field, id)).prop('checked', true);
		}

		if (admin_form) {
			$e.find('[data-purpose="color-picker"]').each(function() {
				admin_form.setupColorPicker($(this));
			});
		}

		$('html, body').animate({
			scrollTop: $e.offset().top - 5
		});

		$lastCreatedRow = $e;
        lastCreatedRowId = id;

        opts.afterAddRow && opts.afterAddRow(this, $e, id);
        opts.sortable && this.refreshSortable();

		return false;
	};

	this.remove = function(field, id) {
		if (!this.is_field_inited(field)) {
			return false;
		}

		if (!confirm('Удалить ' + this.field_titles[field] + '?')) {
			return false;
		}

		$('#' + field + '_div\\[' + id + '\\]').remove();

        opts.afterDelRow && opts.afterDelRow(this, id);

		return false;
	};

	constructor();
};
