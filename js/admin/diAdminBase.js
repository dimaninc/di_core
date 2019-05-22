var diAdminBase = function() {
	this.console = new diAdminConsole();

	function constructor() {
		initMainMenu();
		initControls();
		initExpandCollapse();
	}

	function isSideMenuMode() {
		return $('.admin-layout .logo .menu-toggle').is(':visible');
	}

	function initMainMenu() {
		var $menuRows = $('ul.left-menu > li');

		var toggleMenuRow = function($row, state) {
			var $subMenu = $row.find('> ul'),
                method;

			if (typeof state === 'undefined') {
                state = $row.data('state') ? 0 : 1;
			}

			method = state ? 'slideDown' : 'slideUp';

			$row
                .data('state', state)
                .attr('data-state', state);

            $subMenu[method](function() {
                $.cookie('admin_visible_left_menu_ids',
                    $menuRows.map(function() {
                        return $(this).data('state') ? $(this).data('id') : '';
                    }).get().join(','), {
                        expires: 365,
                        path: '/_admin/'
                    }
                );
            });
		};

		$menuRows.find('> b').click(function() {
			toggleMenuRow($(this).parent('li'));
		});

		$('.menu-panel > [data-purpose="collapse"]').click(function() {
			$menuRows.each(function() {
				toggleMenuRow($(this), 0);
            });
		});

        $('.menu-panel > [data-purpose="expand"]').click(function() {
            $menuRows.each(function() {
                toggleMenuRow($(this), 1);
            });
        });

        $('.admin-layout .logo,.admin-layout .site-title').on('click', function() {
			if (isSideMenuMode()) {
				$('.admin-layout').toggleClass('nav-shown');
			}
		});
	}

	function initControls() {
		$(':radio,:checkbox').diControls();
	}

	function initExpandCollapse() {
		$('.expand-collapse-block u').click(function() {

			var action = $(this).data('action'),
				table = $(this).parent().data('table');

			if (table == 'orders')
				expand_collapse_all_orders(action == 'expand');
			else
				dint_expand_collapse_all(table, di_last_id, 'section', action == 'collapse');

			return false;

		});
	}

	constructor();
};

$(function() {
	window.A = new diAdminBase();

	// orders
	$('[data-table="orders"] .dinicetable td[data-role="invoice"]').click(function() {
		$('[data-table="orders"] .dinicetable tr[id="order-items-'+$(this).parent().data('id')+'"]').toggle();
	});

	// unload
	$(document.body).on('beforeunload', function() {
		return admin_onbeforeunload();
	});
});
