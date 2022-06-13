var diNiceTable = function (opts) {
    var self = this,
        CP,
        language,
        subfolder,
        workerBase = '/api/list/',
        settings = $.extend(
            {
                $table: null,
                table: ''
            },
            opts
        );

    var localization = {
        ru: {
            delete: 'Удалить?',
            are_you_sure: 'Вы уверены?',
            deleted: 'Удалено'
        },
        en: {
            delete: 'Delete?',
            are_you_sure: 'Are you sure?',
            deleted: 'Deleted'
        }
    };

    if ((subfolder = $(document.body).data('subfolder'))) {
        workerBase = '/' + subfolder + workerBase;
    }

    if (!settings.$table) {
        settings.$table = $(
            '.dinicetable_div > table[data-table="{0}"],ul.admin-grid[data-table="{0}"]'.format(
                settings.table
            )
        );
    }

    this.getLanguage = function () {
        if (!language) {
            language = $(document.body).data('language');
        }

        return language;
    };

    this.L = function (token) {
        return localization[this.getLanguage()][token];
    };

    this.getWorkerBase = function () {
        return workerBase;
    };

    this.blinkRow = function (opts) {
        opts = $.extend(
            {
                ids: [],
                cssClass: 'highlight',
                duration: 400
            },
            opts
        );

        var $rows = getRow(opts.ids);

        $rows.addClass(opts.cssClass);

        setTimeout(function () {
            $rows.removeClass(opts.cssClass);
        }, opts.duration);
    };

    this.getTable = function () {
        return settings.table;
    };

    this.getControlPanel = function () {
        return CP;
    };

    function constructor() {
        attachButtonEvents();
        initControlPanel();
    }

    function initControlPanel() {
        CP = new diAdminListControlPanel({
            NiceTable: self
        });
    }

    function log(message) {
        A.console.add(message);
    }

    function attachButtonEvents() {
        settings.$table.on('click', '.nicetable-button', function () {
            var $b = $(this),
                action = $b.data('action'),
                id = $b.closest('[data-id]').data('id');

            if ($b.is('a')) {
                return true;
            }

            switch (action) {
                case 'del':
                    clickDel(id);
                    break;

                case 'up':
                case 'down':
                    clickMove(action, id);
                    break;

                // default is toggle
                default:
                    clickToggle(action, id);
                    break;
            }
        });
    }

    function clickDel(id) {
        var $rows = getRow(id);
        $rows.addClass('delete');

        setTimeout(function () {
            if (confirm(self.L('delete')) && confirm(self.L('are_you_sure'))) {
                call('delete', id, function (res, $row) {
                    $row.remove();

                    log(self.L('deleted'));
                });
            } else {
                $rows.removeClass('delete');
            }
        }, 10);
    }

    function clickMove(direction, id) {
        call(
            'move',
            {
                id: id,
                direction: direction
            },
            function (res) {
                var $upRows = getRow(res.up),
                    $downRows = getRow(res.down);

                $upRows.insertBefore(getRow(res.downFirst));

                self.blinkRow({
                    ids: res.up.concat(res.down),
                    cssClass: 'highlight'
                });

                log(res[direction].length + ' row(s) moved ' + direction);
            }
        );
    }

    function clickToggle(field, id) {
        call(
            'toggle',
            {
                id: id,
                field: field
            },
            function (res, $row) {
                $row.find('.nicetable-button[data-action="' + field + '"]')
                    .attr('data-state', res.state)
                    .data('state', res.state);

                log('Switched "' + field + '" to ' + boolToOnOff(res.state));
            }
        );
    }

    function boolToOnOff(x) {
        return x ? 'On' : 'Off';
    }

    function getRow(ids) {
        if (!di.isArray(ids)) {
            ids = [ids];
        }

        var selectorAr = $.map(ids, function (id) {
            return '[data-id="' + id + '"]';
        });

        return selectorAr.length
            ? settings.$table.find('[data-role="row"]').filter(selectorAr.join(','))
            : null;
    }

    function call(action, id, callback) {
        var params = {};

        if (typeof id === 'object') {
            params = id;
            id = params.id || '';
        }

        $.post(
            workerBase + action + '/' + settings.table + '/' + id,
            params,
            function (res) {
                if (!res.ok) {
                    log('Error while requesting action "' + action + '" for #' + id);

                    if (typeof res.message !== 'undefined') {
                        log(res.message);
                    }

                    return false;
                }

                callback(res, getRow(res.id));
            }
        );
    }

    constructor();
};

diNiceTable.typeSelectors = {
    list: '.dinicetable_div',
    grid: '.admin-grid[data-table]'
};

diNiceTable.getInstance = function (type) {
    var s = type
        ? diNiceTable.typeSelectors[type]
        : di.values(diNiceTable.typeSelectors).join(',');

    return $(s).data('obj');
};

diNiceTable.initLists = function () {
    $(diNiceTable.typeSelectors.list).each(function () {
        var $this = $(this);

        $this.data(
            'obj',
            new diNiceTable({
                table: $this.find('> table[data-table]').data('table'),
                $table: $this
            })
        );
    });
};

diNiceTable.initGrids = function () {
    $(diNiceTable.typeSelectors.grid).each(function () {
        var $this = $(this);

        $this.data(
            'obj',
            new diNiceTable({
                table: $this.data('table'),
                $table: $this
            })
        );
    });
};

diNiceTable.create = function () {
    $(function () {
        diNiceTable.initLists();
        diNiceTable.initGrids();
    });
};

diNiceTable.create();
