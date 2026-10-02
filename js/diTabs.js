var diTabs = function(_opts)
{
	var self = this,
		opts = $.extend({
			$tabsContainer: null,
			$pagesContainer: null,
			selectedTab: null,
			useHashOnClick: true,
            useHashOnInit: true
		}, _opts || {}),
		e = {
			$allTabs: null,
			$allPages: null,
			$tabs: {},
			$pages: {}
		};

	function constructor() {
		e.$allTabs = opts.$tabsContainer.find('[data-tab]').each(function() {
			var $this = $(this),
				tab = $this.data('tab');

			e.$tabs[tab] = $this;
            e.$pages[tab] = opts.$pagesContainer.find('[data-tab="' + tab + '"]');

			if (!opts.selectedTab) {
				opts.selectedTab = tab;
			}
		}).click(function() {
			self.select($(this).data('tab'));

			return false;
		});

		e.$allPages = opts.$pagesContainer.find('[data-tab]');

		self
            .populateSelectedTab()
            .select(opts.selectedTab, true);
	}

    this.populateSelectedTab = function() {
        if (opts.useHashOnInit) {
            var name = window.location.hash.substr(1);

            if (name && this.tabExists(name)) {
                opts.selectedTab = name;
            }
        }

        return this;
    };

	this.select = function(tab, force) {
        if (opts.selectedTab == tab && !force) {
            return this;
        }

        var $tab, $page;

        if (!tab || !($tab = this.getTab(tab)) || !($page = this.getPage(tab))) {
            return this;
        }

		e.$allTabs.removeClass('selected');
		e.$allPages.removeClass('selected');

		setTimeout(function() {
			$tab.addClass('selected');
			$page.addClass('selected');
		}, 10);

		opts.selectedTab = tab;

        if (opts.useHashOnClick && tab) {
            window.location.hash = tab;
        }

		return this;
	};

    this.tabExists = function(name) {
        return typeof e.$tabs[name] != 'undefined';
    };

    this.getTab = function(name) {
        return this.tabExists(name) ? e.$tabs[name] : $();
    };

	this.getPage = function(name) {
		return this.tabExists(name) ? e.$pages[name] : $();
	};

	this.getSelectedTab = function() {
		return opts.selectedTab;
	};

	this.isTabSelected = function(tabName) {
		return this.getSelectedTab() === tabName;
	};

	constructor();
};