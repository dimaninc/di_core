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
      '.dinicetable_div > table[data-table="{0}"],ul[data-role="admin-grid"][data-table="{0}"]'.format(
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

  this.initSaveOrder = function () {
    function handler(event) {
      var $row = $(this).closest('[data-role="row"]');
      var $wrapper = $(this).closest('.nicetable-order');
      var $input = $row.find('input[name="order"]');
      var newValue = parseInt($input.val());
      var oldValue = parseInt($wrapper.data('prev-value'));
      var type = $row.closest('table[data-table]').data('table');
      var id = $row.closest('tr[data-id]').data('id');
      var shouldSubmit = event.type === 'keyup' ? event.keyCode == 13 : true;

      if (shouldSubmit && id && newValue != oldValue) {
        call(
          'order',
          {
            id: id,
            value: newValue
          },
          function (res) {
            if (!res.ok) {
              log('Error changing order: for #' + id);
              return;
            }

            log('Changed order: ' + oldValue + ' -> ' + newValue + ' for #' + id);
            $wrapper.attr('data-prev-value', newValue).data('prev-value', newValue);
          }
        );
      }
    }

    settings.$table
      .find('[data-role="row"] .nicetable-order button')
      .on('click', handler);

    settings.$table
      .find('[data-role="row"] .nicetable-order input[name="order"]')
      .on('keyup', handler);

    return this;
  };

  function constructor() {
    attachButtonEvents();
    initControlPanel();
    self.attachRowEvents().initSaveOrder();
  }

  function initControlPanel() {
    CP = new diAdminListControlPanel({
      NiceTable: self
    });
  }

  function log(message) {
    A.console.add(message);
  }

  this.attachRowEvents = function () {
    settings.$table.on('click', '[data-href]', function () {
      var $c = $(this);
      var href = $c.data('href');

      if (href) {
        location.href = href;
      }
    });

    return this;
  };

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
        call(
          'delete',
          id,
          function (res, $row) {
            $row.remove();

            log(self.L('deleted'));
          },
          function ($row) {
            $row
              .find('.nicetable-button[data-action="del"]')
              .attr('data-loading', 1);
          }
        );
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
        var $upRows = getRow(res.up);
        var $downRows = getRow(res.down);

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
        $row
          .find('.nicetable-button[data-action="' + field + '"]')
          .attr('data-loading', 0)
          .attr('data-state', res.state)
          .data('state', res.state);

        log('Switched "' + field + '" to ' + boolToOnOff(res.state));
      },
      function ($row) {
        $row
          .find('.nicetable-button[data-action="' + field + '"]')
          .attr('data-loading', 1);
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
      : $();
  }

  function call(action, id, callback, precall) {
    var params = {};

    if (typeof id === 'object') {
      params = id;
      id = params.id || '';
    }

    id && precall && precall(getRow(id));

    $.post(
      workerBase + action + '/' + settings.table + '/' + id,
      params,
      function (res) {
        if (!res.ok) {
          log('Error while requesting action "' + action + '" for #' + id);
          typeof res.message !== 'undefined' && log(res.message);
          return;
        }

        callback(res, getRow(res.id));
      }
    );
  }

  constructor();
};

diNiceTable.typeSelectors = {
  list: '.dinicetable_div',
  grid: '[data-role="admin-grid"][data-table]'
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
