var diActionsLog = function(_opts) {
	var self = this,
		worker = '/_core/php/admin/workers/actions_log/get/',
		opts = $.extend({
			targetType: null,
			targetId: null,
			$container: null
		}, _opts || {}),
		template = '<table class="actions-log"><thead><tr>'+
			'<th width="15%">Когда</th><th width="20%">Кто</th><th width="20%">Действие</th><th width="45%">Информация</th>'+
			'</tr></thead><tbody></tbody></table>';

	function constructor() {
		if (typeof opts.$container == 'string') {
			opts.$container = $(opts.$container);
		}

		if (opts.$container.length) {
			getAndPrintLog();
		}
	}

	this.printLogTable = function(rows) {
		opts.$container.html(template);

		var $body = opts.$container.find('tbody');

		$.each(rows, function(k, v) {
			var tr = sprintf('<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>', v.date, v.user, v.actionStr, v.info || '');

			$body.append(tr);
		})
	};

	function getWorkerUri() {
		return worker + [opts.targetType, opts.targetId].join('/') + '/';
	}

	function getAndPrintLog() {
		$.get(getWorkerUri(), {}, function(res) {
			if (res.ok) {
				self.printLogTable(res.data);
			}
		});
	}

	constructor();
};