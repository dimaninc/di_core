<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 27.09.2017
 * Time: 9:28
 */

namespace diCore\Traits\Admin;

use diCore\Data\Types;
use diCore\Admin\BasePage;

trait TargetInside
{
	protected $defaultTypes = [
		Types::content,
	];

	public function tiAddToForm(BasePage $Page, $modelTypes = [], $options = [])
	{
		$defaultSetupCollectionCallback = function (\diCollection $col) {
			$col->orderBy('order_num');

			return $col;
		};

		$defaultMapCollectionCallback = function (\diModel $m, $id) {
			if ($m instanceof \diCore\Entity\Content\Model)
			{
				return [
					'id' => $m->getId(),
					'title' => sprintf('%s (%s)', $m->getTitle(), $m->getType()),
				];
			}

			return [
				'id' => $m->getId(),
				'title' => $m->get('title'),
			];
		};

		$options = extend([
			'setupCollectionCallback' => $defaultSetupCollectionCallback,
			'mapCollectionCallback' => $defaultMapCollectionCallback,
		], $options);

		$_types = $modelTypes ?: $this->defaultTypes;
		$types = [];
		$targets = [];

		foreach ($_types as $type)
		{
			$col = \diCollection::create($type);

			if (is_callable($options['setupCollectionCallback']))
			{
				// use default callback if null returned
				$col = $options['setupCollectionCallback']($col) ?: $defaultSetupCollectionCallback($col);
			}

			$targets[$type] = $col->map(function (\diModel $m, $id) use ($options, $defaultMapCollectionCallback) {
				// use default callback if null returned
				return $options['mapCollectionCallback']($m, $id) ?: $defaultMapCollectionCallback($m, $id);
			});

			$types[$type] = \diTypes::getTitle($type);
		}

		$Page->setBeforeFormTemplate('admin/_parts/target_inside/before_form', [
			'types' => $types,
			'targets' => $targets,
			'selected' => [
				'type' => $Page->getForm()->getModel()->get('target_type'),
				'id' => $Page->getForm()->getModel()->get('target_id'),
			],
		]);
	}
}