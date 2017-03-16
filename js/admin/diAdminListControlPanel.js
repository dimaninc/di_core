var diAdminListControlPanel = function(_opts)
{
	var self = this,
		e = {
			$panel: $('.list-control-panel'),
			$buttonsContainer: null,
			$toggle: null,
			$btnCopy: null,
			$btnMove: null,
			$btnDelete: null,
			$rows: $('.dinicetable > tbody > tr'),
			$rowToggles: null
		},
		externalActions = {},
		selectedIds = [],
		table = $('.dinicetable').data('table'),
		anchorHtml = '<div class="parent-anchor">&#x21B3;</div>',
		opts = $.extend({
			NiceTable: null
		}, _opts || {});

	function constructor()
	{
		setupElements();
		setupToggleClicks();
		setupButtons();
		setupToggleAll();
		setupExpandCollapse();
	}

	this.addAction = function(name, callback)
	{
		var a;

		if (typeof name == 'object')
		{
			a = name;
		}
		else
		{
			a = {};
			a[name] = {
				callback: callback
			};
		}

		externalActions = $.extend(externalActions, a);

		return this;
	};

	function setupElements()
	{
		e.$toggle = $('input#toggle-all', e.$panel);
		e.$buttonsContainer = $('> ul', e.$panel);
		e.$btnCopy = $('button[data-purpose="copy"]', e.$buttonsContainer);
		e.$btnMove = $('button[data-purpose="move"]', e.$buttonsContainer);
		e.$btnDelete = $('button[data-purpose="delete"]', e.$buttonsContainer);

		e.$rowToggles = $('input[type="checkbox"][data-purpose="toggle"]', e.$rows);
	}

	function setupToggleClicks()
	{
		e.$rowToggles.parent('td').click(function(e) {
			if (!$(e.target).is(':checkbox'))
			{
				$(this).find('input[type="checkbox"][data-purpose="toggle"]').click();
			}
		});
	}

	function isExternalAction(name)
	{
		return name && typeof externalActions[name] != 'undefined';
	}

	function getExternalAction(name)
	{
		if (!isExternalAction(name))
		{
			return {
				_exists: false
			};
		}

		return $.extend({
			_exists: true,
			before: function () {
				return true;
			},
			callback: function () {
			},
			deselectOnFinish: false
		}, externalActions[name]);
	}

	function executeExternalAction(name, options)
	{
		options = $.extend({
			ids: []
		}, options || {});

		var action = getExternalAction(name);

		if (action._exists)
		{
			if (action.before(options))
			{
				action.callback(options);
			}

			if (action.deselectOnFinish)
			{
				toggleAll(false);
			}
		}
	}

	function setupButtons()
	{
		var $btn,
			action,
			title,
			ids,
			parent,
			confirmation;

		function doButtonAction()
		{
			if (isExternalAction($btn.data('action')))
			{
				executeExternalAction($btn.data('action'), {
					ids: ids
				});

				return;
			}

			$('.parent-anchor,.outer-anchor').remove();
			e.$rowToggles.removeClass('hidden').prop('checked', false);

			return call(action, {
				ids: ids.join(','),
				parent: parent
			}, function(res) {
				switch (action)
				{
					case 'delete':
						$.each(res.id, function(k, v) {
							getRow(v).remove();
						});
						log('Deleted id#: ' + res.id.join(', '));
						break;

					case 'copy':
					case 'move':
						//todo: make it dynamically
						window.location.reload();
						break;
				}
			});
		}

		e.$buttonsContainer.find('button').on('click', function() {
			$btn = $(this);
			action = $btn.data('purpose') || '';
			title = $btn.html();
			ids = self.getSelectedIds();
			parent = null;
			confirmation = $btn.data('confirmation') || title + ' выделенные записи';

			if (!confirm(confirmation + ' (' + ids.length + ' шт)?') || !confirm('Вы уверены?'))
			{
				return false;
			}

			if (in_array(action, ['copy', 'move']))
			{
				e.$rowToggles.each(function() {
					var $cb = $(this);

					$cb.addClass('hidden')
						.parent().append(anchorHtml);
				});

				$('.dinicetable_div').prepend('<div class="outer-anchor">{0}</div>'.format(anchorHtml));

				$('.parent-anchor').on('click', function() {
					var $anchor = $(this);

					parent = $anchor.parent('td').parent('tr').data('id') || 0;

					doButtonAction();
				});
			}
			else
			{
				doButtonAction();
			}

			return false;
		});
	}

	function log(message)
	{
		A.console.add(message);
	}

	function call(action, params, callback)
	{
		log('Requesting ' + action + ' record(s)');

		$.post(opts.NiceTable.getWorkerBase() + 'batch_' + action + '/' + table + '/', params, function(res) {
			if (!res.ok)
			{
				log('Error while requesting action "' + action + '" for #' + params.ids);

				return false;
			}

			callback(res);
		});
	}

	function toggleAll(state)
	{
		e.$rowToggles.prop('checked', !!state);

		refreshRowHighlight();
		checkButtonsVisibility();
	}

	function setupToggleAll()
	{
		e.$toggle.on('click', function() {
			toggleAll($(this).prop('checked'));
		});

		e.$rowToggles.on('click change', function() {
			checkButtonsVisibility();
		});
	}

	function getExpandButton(id)
	{
		var $btn;

		if (id instanceof jQuery)
		{
			if (id.hasClass('tree'))
			{
				$btn = id;
			}
			else
			{
				$btn = id.find('.tree');
			}
		}
		else
		{
			$btn = e.$rows.filter('[data-id="' + id + '"]').find('.tree');
		}

		return $btn;
	}

	function hideExpandButton(id)
	{
		getExpandButton(id).addClass('hidden');
	}

	function setExpandStatus(id, state)
	{
		var c = {
			true: 'expand',
			false: 'collapse'
		};

		getExpandButton(id).addClass(c[state]).removeClass(c[!state]);
	}

	function getRowsInfo()
	{
		var rowsInfo = [];

		e.$rows.each(function() {
			var $row = $(this),
				collapsed = $row.hasClass('collapsed'),
				id = $row.data('id'),
				level = $row.data('level');

			rowsInfo.push({
				id: $row.data('id'),
				level: $row.data('level'),
				collapsed: $row.hasClass('collapsed')
			});
		});

		return rowsInfo;
	}

	function setupExpandCollapse()
	{
		var prevId = null,
			prevLevel = null,
			rowsInfo = getRowsInfo();

		for (var i = 0; i < rowsInfo.length; i++)
		{
			if (prevId)
			{
				if (prevLevel >= rowsInfo[i].level)
				{
					hideExpandButton(prevId);
				}
			}
			else
			{
				hideExpandButton(rowsInfo[i].id);
			}

			prevId = rowsInfo[i].id;
			prevLevel = rowsInfo[i].level;

			if (i == rowsInfo.length - 1)
			{
				hideExpandButton(rowsInfo[i].id);
			}
		}

		e.$rows.find('.tree').on('click', function() {
			var $btn = $(this),
				$row = $btn.closest('[data-role="row"]'),
				origLevel = $row.data('level'),
				level,
				state = $btn.hasClass('expand');

			while (($row = $row.next()) && $row.length)
			{
				level = $row.data('level');

				if (level <= origLevel)
				{
					break;
				}

				if (!state || level == origLevel + 1)
				{
					$row.toggleClass('collapsed', !state);

					if (!state)
					{
						setExpandStatus($row, true);
					}
				}
			}

			setExpandStatus($btn, !state);

			storeExpandInfo();
		});
	}

	function storeExpandInfo()
	{
		var rowsInfo = getRowsInfo(),
			$btn,
			hiddenAr = [];

		for (var i = 0; i < rowsInfo.length; i++)
		{
			$btn = getExpandButton(rowsInfo[i].id);

			if ($btn.hasClass('expand') && !$btn.hasClass('hidden'))
			{
				hiddenAr.push(rowsInfo[i].id);
			}
		}

		$.cookie('list_collapsed[' + table + ']', hiddenAr.join(','), {
			expires: 365,
			path: '/_admin/'
		});
	}

	function selected()
	{
		return self.getSelectedIds().length > 0;
	}

	this.getSelectedIds = function()
	{
		return selectedIds;
	};

	function getRow(id)
	{
		return e.$rows.filter('[data-id="{0}"]'.format(id));
	}

	function refreshSelectedIds()
	{
		selectedIds = [];

		e.$rowToggles.filter(':checked').each(function() {
			selectedIds.push($(this).data('id'));
		});

		refreshRowHighlight();

		return selectedIds;
	}

	function refreshRowHighlight()
	{
		e.$rowToggles.each(function() {
			var $toggle = $(this),
				$row = $toggle.closest('tr');

			$row.toggleClass('highlight', !!$toggle.prop('checked'));
		});
	}

	function checkButtonsVisibility()
	{
		refreshSelectedIds();

		e.$buttonsContainer[{false: 'fadeOut', true: 'fadeIn'}[selected()]]();

		if (!selected())
		{
			e.$toggle.prop('checked', false);
		}
		else if (self.getSelectedIds().length == e.$rowToggles.length)
		{
			e.$toggle.prop('checked', true);
		}
	}

	constructor();
};