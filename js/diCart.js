function diCart(_opts)
{
	var self = this,

		opts = $.extend({
			workerUri: '/api/cart/'
		}, _opts || {}),

		workerScript = opts.workerUri,

		$e = {
		};

	function constructor()
	{
		initEvents();
	}

	function initEvents()
	{
		$plusMinus = $('[data-cart-id] .plus,[data-cart-id] .minus');
		$remove = $('[data-cart-id] .remove');
		$itemRemove = $('[data-item-id] .remove');

		if ($plusMinus.length && $._data($plusMinus.get(0), 'events') == null)
		{
			$plusMinus.click(function () {
				self.addMore($(this).closest('[data-cart-id]').data('cart-id'), $(this).hasClass('plus') ? 1 : -1);
			});
		}

		if ($remove.length && $._data($remove.get(0), 'events') == null)
		{
			$remove.click(function () {
				self.remove($(this).closest('[data-cart-id]').data('cart-id'));
			});
		}

		if ($itemRemove.length && $._data($itemRemove.get(0), 'events') == null)
		{
			$itemRemove.click(function () {
				self.removeItem($(this).closest('[data-item-id]').data('item-id'));
			});
		}
	}

	function work(action, params)
	{
		$.get(workerScript + action + '/', params || {}, response);
	}

	this.add = function(id, count, rnd)
	{
		holdButton(id, rnd);

		work('add', {
			id: id,
			type: 'items',
			count: count,
			rnd: rnd
		});

		return false;
	};

	this.update = function(id, count, rnd)
	{
		holdButton(id, rnd);

		work('update', {
			id: id,
			type: 'items',
			count: count,
			rnd: rnd
		});

		return false;
	};

	this.addMore = function(id, count, rnd)
	{
		holdButton(id, rnd);

		work('add_more', {
			id: id,
			type: 'items',
			count: count,
			rnd: rnd
		});

		return false;
	};

	this.remove = function(id)
	{
		if (!confirm('Удалить позицию из корзины?'))
		{
			return false;
		}

		work('remove', {
			id: id,
			type: 'items'
		});

		return false;
	};

	this.removeItem = function(id)
	{
		if (!confirm('Удалить позиции этого артикула из корзины?'))
		{
			return false;
		}

		work('removeItem', {
			id: id,
			type: 'items'
		});

		return false;
	};

	function response(res)
	{
		releaseButton(res.id, res.rnd);

		switch (res.action)
		{
			case 'add':
			case 'add_more':
				$('[data-cart-id="'+res.id+'"] [data-purpose="cart-count"]').html(res.count);
				$('[data-cart-id="'+res.id+'"] [data-purpose="cart-cost"]').html(res.cost);
				break;

			case 'remove':
				$('[data-cart-id="'+res.id+'"]').fadeOut();
				break;

			case 'remove_item':
				$('[data-item-id="'+res.id+'"]').fadeOut();
				break;
		}

		self.refresh(res.totals);
	}

	this.refresh = function(totals)
	{
		$('[data-purpose="cart-total-count"]').html(totals.count);
		$('[data-purpose="cart-total-cost"]').html(totals.cost);
	};

	function getButton(id, rnd)
	{
		return $('[data-id="id"]' + (rnd ? '[data-rnd="'+rnd+'"]' : ''));
	}

	function holdButton(id, rnd)
	{
		getButton(id, rnd)
			.prop('disabled', true)
			.addClass('-btn-wait');
	}

	function releaseButton(id, rnd)
	{
		getButton(id, rnd)
			.prop('disabled', false)
			.removeClass('-btn-wait');
	}

	constructor();
}