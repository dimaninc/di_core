var diAdminBase = function () {
  var self = this;
  this.console = new diAdminConsole();

  function constructor() {
    self.initMainMenu().initMainMenuSearch().initControls().initExpandCollapse();
  }

  this.isSideMenuMode = function () {
    return $('.admin-layout .logo .menu-toggle').is(':visible');
  };

  this.initMainMenuSearch = function () {
    var $container = $('.nav .menu-panel');
    var $wrapper = $('.search', $container);
    var $searchInput = $('input', $wrapper);
    var $reset = $('.reset', $wrapper);
    var $menuRows = $('.nav > ul > li');

    var filterRows = function () {
      var query = di.trim($searchInput.val());
      var showAll = !query;
      var words = query ? query.split(/\s+/) : [];

      $menuRows.each(function () {
        var $menuRow = $(this);
        var $menuTitle = $('.group-title', $menuRow);
        var $menuItems = $('> ul > li > a', $menuRow);

        if (showAll) {
          $menuRow.show();

          return true;
        }

        var foundTitle = false;
        var foundItems = false;
        var foundHref = false;

        for (var i = 0; i < words.length; i++) {
          var menuTitle = $menuTitle.text() || '';

          if (menuTitle.toLowerCase().indexOf(words[i]) > -1) {
            foundTitle = true;
          }

          $menuItems.each(function () {
            var $item = $(this);
            var item = $item.text() || '';
            var href = $item.attr('href').replace(/^\/?_admin\//, '');

            if (item.toLowerCase().indexOf(words[i]) > -1) {
              foundItems = true;

              return false;
            } else if (href.toLowerCase().indexOf(words[i]) > -1) {
              foundHref = true;

              return false;
            }
          });
        }

        $menuRow.toggle(foundTitle || foundItems || foundHref);
      });
    };

    var toggleSearchingMode = function (state) {
      $container.toggleClass('searching', !!state);
    };

    $searchInput
      .focus(function () {
        toggleSearchingMode(true);
      })
      .blur(function () {
        toggleSearchingMode(false);
      })
      .on('blur focus input', function () {
        filterRows();
      })
      .keyup(function (e) {
        e = e || window.event;

        if (e.keyCode === 27) {
          $searchInput.val('').blur();
        }
      });

    $reset.click(function () {
      $searchInput.val('');
      toggleSearchingMode(false);
    });

    return this;
  };

  this.initMainMenu = function () {
    var $menuRows = $('ul.left-menu > li');

    var toggleMenuRow = function ($row, state) {
      var $subMenu = $row.find('> ul'),
        method;

      if (typeof state === 'undefined') {
        state = $row.data('state') ? 0 : 1;
      }

      method = state ? 'slideDown' : 'slideUp';

      $row.data('state', state).attr('data-state', state);

      $subMenu[method](function () {
        $.cookie(
          'admin_visible_left_menu_ids',
          $menuRows
            .map(function () {
              return $(this).data('state') ? $(this).data('id') : '';
            })
            .get()
            .join(','),
          {
            expires: 365,
            path: '/_admin/'
          }
        );
      });
    };

    $menuRows.find('.group-title').click(function () {
      toggleMenuRow($(this).parent('li'));
    });

    $('.menu-panel > [data-purpose="collapse"]').click(function () {
      $menuRows.each(function () {
        toggleMenuRow($(this), 0);
      });
    });

    $('.menu-panel > [data-purpose="expand"]').click(function () {
      $menuRows.each(function () {
        toggleMenuRow($(this), 1);
      });
    });

    $('.admin-layout .logo,.admin-layout .site-title').on('click', function () {
      if (self.isSideMenuMode()) {
        $('.admin-layout').toggleClass('nav-shown');
      }
    });

    return this;
  };

  this.initControls = function () {
    $(':radio,:checkbox').diControls();

    return this;
  };

  this.initExpandCollapse = function () {
    $('.expand-collapse-block u').click(function () {
      var action = $(this).data('action'),
        table = $(this).parent().data('table');

      if (table === 'orders') expand_collapse_all_orders(action === 'expand');
      else
        dint_expand_collapse_all(
          table,
          di_last_id,
          'section',
          action === 'collapse'
        );

      return false;
    });

    return this;
  };

  constructor();
};

$(function () {
  window.A = new diAdminBase();

  // orders
  $('[data-table="orders"] .dinicetable td[data-role="invoice"]').click(function () {
    $(
      '[data-table="orders"] .dinicetable tr[id="order-items-' +
        $(this).parent().data('id') +
        '"]'
    ).toggle();
  });

  // unload
  $(document.body).on('beforeunload', function () {
    return admin_onbeforeunload();
  });
});
