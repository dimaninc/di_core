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
		$('ul.left-menu > li > b').click(function()	{
			var $this = $(this),
				$parent = $this.parent('li'),
				$subMenu = $this.next('ul'),
				state = parseInt($parent.attr('state')) ? 0 : 1;

			$subMenu.slideToggle(function() {
				$parent.attr('state', state);

				$.cookie('admin_visible_left_menu_ids',
					$('ul.left-menu > li').map(function() {
						return parseInt($(this).attr('state')) ? $(this).data('id') : '';
					}).get().join(','), {
						expires: 365,
						path: '/_admin/'
					}
				);
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
