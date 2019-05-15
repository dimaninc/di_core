var diComments = function(_opts)
{
	var self = this,
		backend = '/api/comment/',

		BAND_MODE_TREE = 1,
		BAND_MODE_FLAT = 2,

		opts = $.extend({
			backend: null,
			diForm: null,
			addEventToForm: true,
			submitOnCtrlEnter: false,
			loadOnInit: false,
			hideFormAfterSubmit: false,
			autoRefresh: false,
			refreshTimeout: 10,
			highlightNewTimeout: 60,
			animationMethod: 'fade', //fade/slide
			afterSubmitMessage: null,
			moderated: null,
			beforeSubmit: null,
			afterSubmit: null,
            afterRenderCommentRow: null,
            showAfterSubmitMessage: function(o) {
                o.e.$form.find('[data-purpose="after-submit-message"]')
                    .html(opts.afterSubmitMessage)
                    .fadeIn();
            },
			afterWork: null,
			$container: null,
			$defaultFormContainer: null,
			scrollToNewCommentTimeout: null,
			bandMode: BAND_MODE_FLAT, // not used yet
			orderField: 'order_num', // 'id' was before
			type: null,
			id: null,
			template: null
		}, _opts || {}),

		firstCommentId = null,
		lastCommentId = null;

	this.e = {
		$container: null,
		$defaultFormContainer: null,
		$progress: null,
		$rows_container: null,
		$block: null,
		$form: null,
		$parent: null,
		$content: null
	};

	function init()
	{
		initElements();
		initOpts();

		if (!loadOnInit()) {
			basicInit();
		}

		//self.tryToHighlight();
	}

	function basicInit()
	{
		initElements();
		initActions();
		initEvents();
		initCommentIds();
		initRefresh();
	}

	function initElements()
	{
        if (opts.$container) {
            self.e.$container = typeof opts.$container == 'object'
                ? opts.$container
                : $(opts.$container);
        }

        if (opts.$defaultFormContainer) {
            self.e.$defaultFormContainer = typeof opts.$defaultFormContainer == 'object'
                ? opts.$defaultFormContainer
                : $(opts.$defaultFormContainer);
        }

        if (self.e.$container && self.e.$container.hasClass('comments-block')) {
            self.e.$block = self.e.$container;
        } else {
            var selector = '.comments-block';

            if (opts.type && opts.id) {
                selector += '[data-type="' + opts.type + '"][data-id="' + opts.id + '"]';
            }

            self.e.$block = $(selector, self.e.$container || null);
        }

        self.e.$rows_container = $('.comment-rows', self.e.$block);
        self.e.$form = $('form[name="comment_form"]', self.e.$block);
        self.e.$parent = $('[name=parent]', self.e.$form);
        self.e.$content = $('[name=content]', self.e.$form);
        self.e.$progress = $('.loading-ico,.loading', self.e.$form);
	}

	function initEvents()
	{
		if (opts.addEventToForm)
		{
			self.e.$form.off('submit.diComments').on('submit.diComments', function() {
				return self.onSubmit();
			});
		}

		if (opts.submitOnCtrlEnter)
		{
			self.e.$content.off('keypress.diComments').on('keypress.diComments', function(event) {
				event = event || window.event;

				if (in_array(event.keyCode, [10, 13]) && event.ctrlKey)
				{
					event.preventDefault();

					if (opts.addEventToForm)
					{
						return self.onSubmit();
					}
					else
					{
						self.e.$form.submit();

						return false;
					}
				}

				return true;
			});
		}
	}

	function initOpts()
	{
		if (opts.backend) {
			backend = opts.backend;
		}

		if (self.e.$container) {
            if (!opts.type) {
                opts.type = self.e.$container.data('type');
            }

            if (!opts.id) {
                opts.id = self.e.$container.data('id');
            }

            if (!opts.template) {
                opts.template = self.e.$container.data('template');
            }
		}

        if (self.e.$form) {
            if (opts.afterSubmitMessage === null) {
                opts.afterSubmitMessage = self.e.$form.data('after-submit-message');
            }

            if (opts.moderated === null) {
                opts.moderated = self.e.$form.data('moderated') || false;
            }
        }
	}

    this.getOpt = function(key) {
        return typeof opts[key] != 'undefined'
            ? opts[key]
            : null;
    };

	function getAnimationFunction(state)
	{
		return opts.animationMethod == 'fade'
			? {false: 'fadeOut', true: 'fadeIn'}[!!state]
			: {false: 'slideUp', true: 'slideDown'}[!!state];
	}

	function toggleElement($e, state)
	{
		$e[getAnimationFunction(state)]();
	}

	function loadOnInit()
	{
		if (opts.loadOnInit)
		{
			work('block');

			return true;
		}

		return false;
	}

	function initRefresh()
	{
		if (opts.autoRefresh)
		{
			setInterval(function() {
				self.refresh();
			}, opts.refreshTimeout * 1000);
		}
	}

	function initCommentIds()
	{
    	self.e.$rows_container.find('.comment-row').each(function() {
		    updateCommentIds($(this).data(opts.orderField.replace('_', '-')));
    	});
	}

    function needToShowNewComment()
    {
        return !opts.moderated;
    }

	function work(action, params)
	{
		$.post(backend + action + '/', $.extend({
			target_type: opts.type,
			target_id: opts.id,
			template: opts.template
		}, params || {}), function(data) {
			response($.extend({
				action: action
			}, data));
		});
	}

	function response(data)
	{
		switch (data.action)
		{
			case 'block':
				self.e.$container.html(data.html);

				basicInit();

				break;

			case 'add':
			case 'edit':
				if (opts.afterSubmit)
				{
					opts.afterSubmit(self);
				}

				showProgress(false);

				if (data.reload)
				{
					window.location.reload();
				}

				if (data.ok)
				{
					if (data.action == 'edit')
					{
						self.getCommentRow(self.e.$form.attr('data-id')).find('.content').text(self.e.$form.find('[name="content"]').val());

						self.e.$form.removeAttr('data-id');
					}
					else if (data.action == 'add')
					{
						var totalCount = self.e.$block.data('total-count') || 0;
						totalCount++;

						self.e.$block
							.attr('data-total-count', totalCount)
							.data('total-count', totalCount);
					}

					self.e.$content.val('').blur();

					if (opts.hideFormAfterSubmit)
					{
						toggleElement(self.e.$form, false);
					}
					else
					{
						self.showForm();
					}

                    if (needToShowNewComment()) {
                        placeCommentRow(data);
                        updateCommentIds(data[opts.orderField]);
                        initActions();
                    } else if (opts.afterSubmitMessage) {
                        opts.showAfterSubmitMessage(self);
                    }

					/*
					 //$('.comments-count[data-type='+data.type+'][data-id='+data.target_id+']').html(e2.innerHTML*1 + 1);
					 */
				}
                else if (opts.diForm) {
                    for (var field in data.errors) {
                        if (data.errors.hasOwnProperty(field)) {
                            opts.diForm.showError(field, data.errors[field].join(''), 'error');
                        }
                    }
                }

				break;
		}

		if (opts.afterWork)
		{
			opts.afterWork(data.action, data);
		}
	}

	function updateCommentIds(id, force)
	{
		setFirstCommentId(id, force);
		setLastCommentId(id, force);
	}

	function setFirstCommentId(id, force)
	{
	    force = force || false;
		id = parseInt(id);

	    if (firstCommentId === null || force || id < firstCommentId)
	    {
		    firstCommentId = id;
	    }
	}

	this.getFirstCommentId = function()
	{
		return firstCommentId;
	};

	function setLastCommentId(id, force)
	{
		force = force || false;
		id = parseInt(id);

		if (lastCommentId === null || force || id > lastCommentId)
		{
			lastCommentId = id;
		}
	}

	this.getLastCommentId = function()
	{
		return lastCommentId;
	};

	function initActions()
	{
		$('.actions [data-action]', self.e.$block).off('click.diComments').on('click.diComments', function() {

		    var $this = $(this),
		    	$cr = $this.closest('.comment-row'),
		    	id = $cr.data('id'),
		    	action = $this.data('action');

			switch (action)
			{
			    case 'reply':
					self.showForm({
						parent: id
					});
					break;

				case 'del':
					self.del(id);
					break;

				case 'edit':
					self.edit(id);
					break;
			}

			return false;

		});
	}

	this.insertSmile = function(smile_id)
	{
		var x = get_cursor_position(self.e.$content.get(0)),
			v = self.e.$content.val();

		self.e.$content.val(v.substr(0, x) + ' ' + smile_id + ' ' + v.substr(x)).focus();

		set_cursor_position(self.e.$content.get(0), x + smile_id.length + 2);

		return false;
	};

	this.tryToHighlight = function()
	{
		var ar = parse_uri_params(window.location.href, '#');

		for (var i in ar)
		{
			if (ar.hasOwnProperty(i) && i.substr(0, 7) == 'comment')
			{
				var comment_id = i.substr(7)*1;

				if (comment_id)
				{
					this.getCommentRow(comment_id).addClass('comment-highlighted');

					break;
				}
			}
		}
	};

	this.showForm = function(options)
	{
	    options = $.extend({
	    	parent: 0,
	    	id: null,
	    	action: 'add' //add/edit
	    }, options || {});

	    switch (options.action)
	    {
	    	case 'add':
			    var $p = options.parent > 0 ? this.getCommentRow(options.parent) : false;

			    if (options.parent > 0)
			    {
				    this.e.$form.insertAfter($p);
			    }
			    else
			    {
				    if (this.e.$defaultFormContainer)
				    {
					    this.e.$form.appendTo(this.e.$defaultFormContainer);
				    }
				    else
				    {
					    this.e.$form.insertAfter($('.comment-rows'));
				    }
			    }

				this.e.$form
					.attr('data-level-num', options.parent > 0 ? $p.data('level-num') + 1 : 0)
					.removeAttr('data-id');

				break;

			case 'edit':
			    var $e = this.getCommentRow(options.id);

				this.e.$form
					.insertAfter($e)
					.attr('data-level-num', $e.data('level-num'))
					.attr('data-id', options.id)
                    .find('[name="content"]')
						.val($e.find('.content').text());

				break;
		}

		this.e.$form.find('[name="action"]').val(options.action);

		toggleElement(this.e.$form, true);
		this.e.$parent.val(options.parent);

		if (options.parent > 0 || options.action == 'edit')
		{
			this.e.$content.focus();
		}

		return false;
	};

	this.toggleSmilesBlock = function()
	{
		$('#comment-form-smiles').toggle();

		return false;
	};

	this.hideLoadPreviousBlock = function()
	{
		this.e.$block.attr('data-previous-exists', 'false');

		return this;
	};

	this.refresh = function(_opts)
	{
		_opts = $.extend({
			where: null
		}, _opts || {});

		$.post(backend, {
	    	action: 'refresh',
			where: _opts.where,
		    first_comment_id: this.getFirstCommentId(),
	    	last_comment_id: this.getLastCommentId(),
	    	target_type: opts.type,
	    	target_id: opts.id,
	    	l: this.e.$form.find('[name="l"]').val()
    	}, function(data) {
			if (_opts.where == 'past')
			{
				if (!data.new_comments.length)
				{
					self.hideLoadPreviousBlock();

					return false;
				}

				data.new_comments = data.new_comments.reverse();
			}

			var nested = [];

			$.each(data.new_comments, function(index, comment) {
				if (self.getCommentRow(comment.id).length)
				{
					//console.log('comment with id=' + comment.id + ' exists');

					return true;
				}

				if (comment.parent > 0)
				{
					nested.push(comment);

					return true;
				}

				placeCommentRow(comment, {
					forcePrepend: _opts.where == 'past',
					scrollTo: _opts.where != 'past'
				});

				updateCommentIds(comment[opts.orderField]);

				if (opts.highlightNewTimeout)
				{
					(function(id) {
						self.getCommentRow(id).addClass('new');

						setTimeout(function() {
							self.getCommentRow(id).removeClass('new');
						}, opts.highlightNewTimeout * 1000);
					})(comment.id);
				}
			});

			$.each(nested.reverse(), function(index, comment) {
				placeCommentRow(comment, {
					forcePrepend: _opts.where == 'past',
					scrollTo: false
				});

				updateCommentIds(comment[opts.orderField]);
			});

			initActions();

			console.log(self.e.$rows_container.find('.comment-row').length, self.e.$block.data('total-count'));

			if (self.e.$rows_container.find('.comment-row').length == self.e.$block.data('total-count'))
			{
				self.hideLoadPreviousBlock();
			}
	    });
	};

	this.getCommentRow = function(id)
	{
		return $('.comment-row[data-id="'+id+'"]');
	};

	this.edit = function(id)
	{
		this.showForm({
			action: 'edit',
			id: id
		});
	};

	this.del = function(id)
	{
	    this.confirm(['Удалить комментарий?', 'Вы уверены?'], {
	    	yes: function() {
			    $.post(backend, {
			        l: self.e.$form.find('[name="l"]').val(),
			    	action: 'del',
			    	comment_id: id
		    	}, function(data) {
					self.getCommentRow(data.id).replaceWith(data.html);

					//$('.comments-count[data-type='+data.type+'][data-id='+data.target_id+']').html(e2.innerHTML*1 - 1);
			    });
	    	}
	    });

		return false;
	};

	this.confirm = function(message, _opts)
	{
	    _opts = $.extend({
	    	yes: null,
	    	no: null
	    }, _opts || {});

		if (typeof message != 'object')
			message  = [message];

		for (var i in message)
		{
			if (message.hasOwnProperty(i) && !confirm(message[i]))
			{
				if (_opts.no)
				{
					_opts.no();
				}

				return false;
			}
		}

		if (_opts.yes)
		{
			_opts.yes();
		}
	};

	function placeCommentRow(data, _opts)
	{
		_opts = $.extend({
			forcePrepend: false,
			scrollTo: true
		}, _opts || {});

	    var $e = $(data.html).hide();

		if (data.parent > 0)
		{
		    var $p = self.getCommentRow(data.parent),
		    	$a = $p,
		    	$n,
		    	levelNum = $p.data('level-num');

			while (true)
			{
			    $n = $a.next();

			    if (!$n.length || $n.data('level-num') <= levelNum)
			    {
				    break;
			    }

				$a = $n;
			}

			$e.insertAfter($a);
		}
		else
		{
			var method = (!data.dir || data.dir.toLowerCase() == 'asc') && !_opts.forcePrepend ? 'append' : 'prepend';

		    self.e.$rows_container[method]($e);
		}

		toggleElement($e, true);

		if (_opts.scrollTo && opts.scrollToNewCommentTimeout !== null && $e.position().top)
		{
			setTimeout(function() {
				$('html, body').animate({
					scrollTop: $e.offset().top //$e.position().top
				}, opts.scrollToNewCommentTimeout);
			}, 50);
		}

        opts.afterRenderCommentRow && opts.afterRenderCommentRow(self, $e);
    }

	function showProgress(state)
	{
		self.e.$progress.css({
			visibility: state ? 'visible' : 'hidden'
		});

		self.e.$form.toggleClass('comment-progress', !!state);

		$('button[type="submit"]', self.e.$form).prop('disabled', !!state);
	}

	this.onSubmit = function()
	{
		if (opts.diForm && !opts.diForm.onSubmit())
		{
			return false;
		}

		showProgress(true);

		if (opts.beforeSubmit)
		{
			opts.beforeSubmit(self);
		}

		var q_ar = this.e.$form.serializeArray().reduce(function(a, x) { a[x.name] = x.value; return a; }, {}),
			action = this.e.$form.find('[name="action"]').val();

		if (action == 'edit')
		{
			for (var i in q_ar)
			{
				if (q_ar.hasOwnProperty(i) && !in_array(i, ['action','content']))
				{
					delete q_ar[i];
				}
			}

			q_ar.comment_id = this.e.$form.attr('data-id');
		}

		work(action, q_ar);

		return false;
	};

	init();
};
