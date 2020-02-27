function diDbManager(_opts)
{
	var self = this,

		opts = $.extend({
			workerUri: '/api/db/'
		}, _opts || {}),

		workerScript = opts.workerUri,

		$e = {
			tables: $('select#tables'),
			dumpFileName: $('#dump_fn'),
			console: $('.db-errors-console')
		};

	function constructor()
	{
		bindEvents();
		self.refreshTableSizes();
	}

	function bindEvents()
	{
	    $('.db-list form.create').submit(function() {
	    	self.createDump();

	    	return false;
	    });

	    $('[data-purpose="select-all"]').click(function() {
	    	self.selectAllTables(true);
	    });

	    $('[data-purpose="deselect-all"]').click(function() {
	    	self.selectAllTables(false);
	    });

	    $('input#system').on('click change', function() {
			$('.dump-settings').css('visibility', this.checked ? 'hidden' : 'visible');
		});

		$('button[data-action="execute"]').click(function() {
			self.executeQuery('here the query');
		});

		$('iframe[name="upload_container"]').load(function() {
			self.status('Dump has been uploaded');

			addDumpRow($.parseJSON($(this).contents().text()));
		});

		$(document.body)
			.on('click', '[data-type="database"] button[data-action="restore"]', function() {
				self.restoreDump($(this).closest('[data-filename]').data('filename'), $(this).closest('[data-folder-id]').data('folder-id'));
			})
			.on('click', '[data-type="database"] button[data-action="download"]', function() {
				self.downloadDump($(this).closest('[data-filename]').data('filename'), $(this).closest('[data-folder-id]').data('folder-id'));
			})
			.on('click', '[data-type="database"] button[data-action="delete"]', function() {
				self.deleteDump($(this).closest('[data-filename]').data('filename'));
			})
			.on('click', '[data-type="database"] button[data-action="view"]', function() {
				self.viewDump($(this).closest('[data-filename]').data('filename'), $(this).closest('[data-folder-id]').data('folder-id'));
			});

		$e.tables.on('click change', function() {
			self.refreshTableSizes();

			var $this = $(this),
				$options = $this.find('option'),
				selectedCount = 0,
				lastTable = null;

			if ($options.length < 2) {
				return false;
			}

			$options.each(function() {
				if (this.selected) {
					selectedCount++;
					lastTable = this.value;
				}
			});

			if (selectedCount == 1) {
				$e.dumpFileName.val(lastTable);
			}
		});
	}

	function worker(action, options)
	{
		if (typeof options != 'object') {
			options = {
				file: options
			}
		}

		options = $.extend({
			file: '',
			folderId: 0
		}, options);

		var urlBase = workerScript + action + '/';

	    if (action == 'download') {
	        options.headers = 1;

	    	window.location.href = urlBase + '?' + $.param(options);

	    	return false;
	    }

		$.get(urlBase, options, function(res) {

			if (!res.ok)
			{
				self.status('Unable to complete action: `'+action+'` with file `'+options.file+'`');

				//return false;
			}

			switch (action)
			{
				case 'create':
					if (res.ok)
					{
						self.status('Dump has been created');

						addDumpRow(res);
					}

					break;

				case 'execute':
					break;

				case 'restore':
					if (res.ok)
					{
						self.status('Dump `'+options.file+'` has been successfully restored into Database');

						self.loadTablesIntoList(res.tablesForSelectAr);
					}
					else
					{
						$e.console.html(res.errors.join('<br><br>').replace(/\n/g, '<br>\n')).slideDown();

						/*
						$('html, body').animate({
							scrollTop: $e.console.position().top
						}, 1000);
						*/
					}

					break;

				case 'delete':
					if (res.ok)
					{
						self.status(res.file+' has been deleted');
					}

					$('.db-dump-rows[data-type="database"] tbody tr[data-filename="'+res.file+'"]').fadeOut();

					break;

				default:
					self.status('Unknown action "'+action+'"');
					break;
			}

		});
	}

	function addDumpRow(res)
	{
		var sizeStr = res.size
			? ', ' + size_in_bytes(res.size)
			: '';

		$('.db-dump-rows[data-type="database"] tbody[data-folder-id="1"] tr:first-child').after(
			'<tr data-format="' + res.format + '" data-filename="' + res.file + '">' +
			'<td><b>' + (res.name || res.file) + '</b> ' +
			'<small>(' + (new Date().toLocaleString()) + sizeStr + ') [' + res.format + ']</small></td>' +
			'<td><button type="button" data-action="restore">Restore...</button> ' +
			'<button type="button" data-action="download">Download</button> ' +
			'<button type="button" data-action="delete">Delete...</button> ' +
			'<button type="button" data-action="view">View</button></td></tr>'
		);
	}

	function getCheckboxParam($e)
	{
	    if (typeof $e != 'object')
	    	$e = $($e);

		return $e.prop('checked') ? 1 : 0;
	}

	this.status = function(message)
	{
		A.console.add(message);
	};

	this.error = function(message)
	{
		alert(message);
	};

	this.executeQuery = function(query)
	{
		if (!confirm('Are you sure you want to execute query "'+query+'"?'))
		{
			return false;
		}

		worker('execute', {
			query: query
		});
	};

	this.createDump = function()
	{
		var $options = $e.tables.find('option'),
			tablesAr = [],
			queryAr;

		$options.each(function() {
		    if (this.selected)
		    {
				tablesAr.push(this.value);
			}
		});

		queryAr = {
			compress: getCheckboxParam('#compress_dump'),
			drops: getCheckboxParam('#dump_drops'),
			creates: getCheckboxParam('#dump_creates'),
			fields: getCheckboxParam('#dump_fields'),
			data: getCheckboxParam('#dump_data'),
			multiple: getCheckboxParam('#dump_multiple'),
			system: getCheckboxParam('#system'),
			tables: tablesAr.join(','),
			file: $e.dumpFileName.val(),
			folderId: 1
		};

		this.status('Creating database dump...');

		worker('create', queryAr);
	};

	this.restoreDump = function(file, folderId)
	{
		if (!confirm('Are your sure you want to restore this database dump?\nWarning! Current database might be deleted or damaged!'))
		{
			return false;
		}

		this.status('Restoring database dump '+file+'...');

		worker('restore', {
			file: file,
			folderId: folderId
		});
	};

	this.downloadDump = function(file, folderId)
	{
		this.status('Downloading database dump...');

		worker('download', {
			file: file,
			folderId: folderId
		});
	};

	this.deleteDump = function(file)
	{
		if (!confirm('Are you sure you want to delete the database dump '+file+'?'))
		{
			return false;
		}

		this.status('Deleting database dump '+file+'...');

		worker('delete', {
			file: file,
			folderId: 1
		});
	};

	this.viewDump = function(file, folderId)
	{
		$('[data-name="view-sql"] code')
			.text('Loading...')
			.load(workerScript+'download/'+'?file='+file+'&folderId='+folderId+'&headers=0', function() {
				dip.show('view-sql');
			});
	};

	this.refreshTableSizes = function()
	{
		var s1 = s2 = 0,
			$options = $e.tables.find('option');

		$options.each(function() {
			if (this.selected)
			{
				var ar = (this.innerHTML || this.text).match(/^[a-z0-9_]+,\s([^\s]+)\s\([^\s]+\s([^\s]+)\)/i);

				if (ar && ar.length === 3)
				{
					s1 += ~~from_size_in_bytes(ar[1]);
					s2 += ~~from_size_in_bytes(ar[2]);
				}
			}
		});

		$('#total_size_selected').html(size_in_bytes(s1));
		$('#total_idx_size_selected').html(size_in_bytes(s2));
	};

	this.selectAllTables = function(state)
	{
		var $options = $e.tables.find('option');

		$options.prop('selected', !!state);

		$e.dumpFileName.val('');

		this.refreshTableSizes();

		//$e.tables.focus();
	};

	this.loadTablesIntoList = function(ar)
	{
		$e.tables.find('option').remove();
		$e.dumpFileName.val('');

		$.each(ar, function(table, title) {
			$e.tables.append($('<option value="'+table+'">'+title+'</option>'));
		});

		this.selectAllTables(true);
	};

	constructor();
}
