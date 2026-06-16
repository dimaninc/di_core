<?php

use diCore\Helper\StringHelper;

class diListController extends \diBaseAdminController
{
    const BATCH_COPY_ACTION = 1;
    const BATCH_MOVE_ACTION = 2;

    private $possibleDirections = ['up', 'down'];
    private $signs = ['up' => '<', 'down' => '>'];
    private $orderDirections = ['up' => 'desc', 'down' => 'asc'];
    private $orderNumField = 'order_num';

    // table => bool, whether its admin page enables useEditLog() (per-request memo)
    private static $editLogEnabledCache = [];

    public function batchDeleteAction()
    {
        $c = $this->getTargetCollection();

        $ar = [
            'ok' => !!count($c),
            'id' => [],
        ];

        /** @var \diModel $m */
        foreach ($c as $m) {
            if ($m->exists('parent') && $m->exists('level_num')) {
                $collection = $this->getFamilyCollection($m, [$m]);
            } else {
                $collection = [$m];
            }

            /** @var \diModel $model */
            foreach ($collection as $model) {
                $ar['id'][] = $model->getId();

                $this->deleteRecord($model);
            }
        }

        return $ar;
    }

    public function batchMoveAction()
    {
        return $this->batch(self::BATCH_MOVE_ACTION);
    }

    public function batchCopyAction()
    {
        return $this->batch(self::BATCH_COPY_ACTION);
    }

    protected function batch($action)
    {
        $all = $this->getAllModels($this->getTargetCollection());

        $ar = [
            'ok' => !!count($all),
            'id' => array_keys($all),
        ];

        if ($ar['ok']) {
            $table = $this->param(0);
            $delta = count($all);
            $map = [];

            $parentModel = $this->getParentModel(
                $table,
                \diRequest::post('parent', 0)
            );
            $order = $parentModel->getRelated('order');

            // checking if there is a loop, we can't move model inside itself or inside children
            if ($action == self::BATCH_MOVE_ACTION) {
                /** @var \diModel $m */
                foreach ($all as $m) {
                    if ($parentModel->getId() == $m->getId()) {
                        return [
                            'ok' => false,
                            'message' => 'Parent cycling detected',
                        ];
                    }
                }
            }

            $this->moveRecordsDown($table, $order, $delta);

            /** @var diModel $m */
            foreach ($all as $m) {
                /** @var diModel $p */
                $p = $map[$m->get('parent')] ?? $parentModel;

                if ($action == self::BATCH_COPY_ACTION) {
                    $m->killId()->generateSlug();
                }

                $m->set('parent', $p->getId())
                    ->set('level_num', $p->get('level_num') + 1)
                    ->set($this->orderNumField, $order++)
                    ->save();

                $map[$m->getOrigId()] = $m;
            }

            reset($all);
            $this->postProcess(current($all));
        }

        return $ar;
    }

    private function moveRecordsDown($table, $orderNum, $delta = 1)
    {
        $this->getDb()->update(
            $table,
            [
                "*$this->orderNumField" => "$this->orderNumField + $delta",
            ],
            "WHERE $this->orderNumField >= '$orderNum'"
        );

        return $this;
    }

    private function getParentModel($table, $parentId)
    {
        if ($parentId > 0) {
            $parentModel = \diModel::createForTableNoStrict($table, $parentId, 'id');
            $order = $parentModel->get($this->orderNumField) + 1;
        } else {
            $minRec = $this->getDb()->r(
                $table,
                '',
                "MIN($this->orderNumField) as min_order_num"
            );
            $order = $minRec ? $minRec->min_order_num - 1 : 0;

            $parentModel = \diModel::createForTableNoStrict($table);
            $parentModel
                ->setId(-1)
                ->set('parent', -1)
                ->set('level_num', -1)
                ->set($this->orderNumField, $order - 1);
        }

        $parentModel->setRelated('order', $order);

        return $parentModel;
    }

    private function getAllModels(\diCollection $c)
    {
        $all = [];

        /** @var \diModel $m */
        foreach ($c as $m) {
            if ($m->exists('parent') && $m->exists('level_num')) {
                $collection = $this->getFamilyCollection($m, [$m]);
            } else {
                $collection = [$m];
            }

            /** @var \diModel $model */
            foreach ($collection as $model) {
                $all[$model->getId()] = $model;
            }
        }

        uasort($all, function (\diModel $a, \diModel $b) {
            if ($a->get('order_num') == $b->get('order_num')) {
                return 0;
            }

            return $a->get('order_num') < $b->get('order_num') ? -1 : 1;
        });

        return $all;
    }

    public function deleteAction()
    {
        $m = $this->getTargetModel();

        if ($m->exists('parent') && $m->exists('level_num')) {
            $collection = $this->getFamilyCollection($m, [$m]);
        } else {
            $collection = [$m];
        }

        $ar = [
            'ok' => $m->exists(),
            'id' => array_map(function (\diModel $m) {
                return $m->getId();
            }, $collection),
        ];

        foreach ($collection as $model) {
            $this->deleteRecord($model);
        }

        return $ar;
    }

    public function toggleAction()
    {
        $field = \diRequest::post('field');
        $m = $this->getTargetModel();

        $ar = [
            'ok' => false,
            'id' => $m->getId(),
        ];

        if (!$field) {
            $ar['message'] = 'No field specified';
        } elseif (!$m->exists($field) && !$m::getFieldType($field)) {
            $ar['message'] = "Record #{$m->getId()} doesn't have field '$field'";
        } else {
            try {
                $oldValue = $m->get($field);

                $m->set($field, $m->get($field) ? 0 : 1)->save();

                $this->afterToggle($m, $field)->postProcess($m);
                $this->logAdminToggle($m, $field, $oldValue);

                $ar['ok'] = true;
                $ar['state'] = $m->get($field);
            } catch (\Exception $e) {
                $ar['message'] = $e->getMessage();
                $ar['state'] = $m->getOrigData($field);
            }
        }

        return $ar;
    }

    protected function afterToggle(diModel $m, $field)
    {
        $methodName = camelize('after_toggle_' . $field);

        if (method_exists($m, $methodName)) {
            $m->$methodName();
        }

        return $this;
    }

    public function moveAction()
    {
        $direction = \diRequest::post('direction');
        $m = $this->getTargetModel();

        $ar = [
            'ok' => false,
            'up' => [],
            'down' => [],
            'downFirst' => null,
        ];

        if (!in_array($direction, $this->possibleDirections)) {
            $ar['message'] = "Invalid direction: $direction";

            return $ar;
        }

        if (!$m->exists($this->orderNumField)) {
            $ar['message'] = "Field $this->orderNumField not exists in model";

            return $ar;
        }

        $col = $m::getCollectionClass()::create();
        $m->filterCollectionForMove($col);

        if ($col->hasQueryWhere()) {
            $col->filterBy(
                $this->orderNumField,
                $this->signs[$direction],
                $m->get($this->orderNumField)
            )->orderBy($this->orderNumField, $this->orderDirections[$direction]);

            $neighbor = $col->getFirstItem();
        } else {
            $queryAr = $this->getQueryArForMove($m, [
                "$this->orderNumField{$this->signs[$direction]}'{$m->get(
                    $this->orderNumField
                )}'",
            ]);

            $neighbor = new \diModel(
                $this->getDb()->r(
                    $m->getTable(),
                    'WHERE ' .
                        join(' AND ', $queryAr) .
                        " ORDER BY $this->orderNumField {$this->orderDirections[$direction]}"
                ),
                $m->getTable()
            );
        }

        if (!$neighbor->exists()) {
            $ar['message'] = 'Record is on the edge already';

            return $ar;
        }

        if ($neighbor->get($this->orderNumField) < $m->get($this->orderNumField)) {
            $m1 = $neighbor;
            $m2 = $m;
        } else {
            $m1 = $m;
            $m2 = $neighbor;
        }

        $col1 = $this->getFamilyCollection($m1, [$m1]);
        $col2 = $this->getFamilyCollection($m2, [$m2]);

        $num = $m1->get($this->orderNumField);
        $counter = 0;
        $limit = count($col2);
        $field = $this->orderNumField;
        $dir = 'up';

        try {
            array_map(function (\diModel $model) use (
                $num,
                $field,
                &$ar,
                $dir,
                &$counter,
                $limit
            ) {
                $model->set($field, $num + $counter++)->save();

                if ($dir == 'up' && $counter > $limit) {
                    $dir = 'down';
                }

                $ar[$dir][] = $model->getId();
            }, array_merge($col2, $col1));

            $this->postProcess($m);

            $ar['downFirst'] = $m1->getId();
            $ar['ok'] = true;
        } catch (\Exception $e) {
            $ar['message'] = $e->getMessage();
        }

        return $ar;
    }

    /** @deprecated */
    protected function getQueryArForMove(\diModel $m, $ar = [])
    {
        return array_merge($ar, $m->getQueryArForMove());
    }

    public function orderAction()
    {
        $m = $this->getTargetModel();
        $value = \diRequest::post('value');

        if (!isInteger($value)) {
            return $this->badRequest('Integer value required');
        }

        $value = (int) $value;

        $this->moveRecordsDown($m->getTable(), $value);

        $m->set('order_num', $value)->save();

        return $this->okay([
            'id' => $m->getId(),
            'order' => $value,
        ]);
    }

    /**
     * @return diModel
     */
    protected function getTargetModel()
    {
        $table = $this->param(0);
        $id = $this->param(1);

        return \diModel::createForTableNoStrict($table, $id, 'id');
    }

    /**
     * @return \diCollection
     */
    protected function getTargetCollection()
    {
        $table = $this->param(0);
        $ids = explode(',', \diRequest::post('ids', ''));
        $ids = array_filter(array_map([StringHelper::class, 'in'], $ids));

        return \diCollection::createForTableNoStrict($table)->filterBy('id', $ids);
    }

    /**
     * @param \diModel $m
     * @return $this
     */
    protected function deleteRecord(\diModel $m)
    {
        $copy = clone $m;

        $this->logAdminDeletion($m);

        $m->hardDestroy();

        $this->postProcess($copy);

        return $this;
    }

    /**
     * Records a list toggle (visible/active/top/…) as a regular edit-log entry.
     * The single flipped field reads like any other field change, so operation
     * stays 'update'.
     */
    protected function logAdminToggle(\diModel $m, $field, $oldValue)
    {
        if (!$this->shouldLogAdminEdit($m)) {
            return $this;
        }

        try {
            $old = $m->processFieldsOnSave([$field => $oldValue]);
            $new = $m->processFieldsOnSave([$field => $m->get($field)]);

            $log = \diCore\Entity\AdminTableEditLog\Model::create()
                ->setTargetTable($m->getTable())
                ->setTargetId($m->getId())
                ->setAdminId($this->getEditLogAdminId())
                ->setOldData(serialize($old))
                ->setNewData(serialize($new));

            if ($log->hasOldData() && $log->hasNewData()) {
                $log->save();
            }
        } catch (\Throwable $e) {
            // logging must never break the admin action
        }

        return $this;
    }

    /**
     * Snapshots a record being deleted from a list (full row + related data) into
     * the edit log before it is destroyed.
     */
    protected function logAdminDeletion(\diModel $m)
    {
        if (!$m->exists() || !$this->shouldLogAdminEdit($m)) {
            return $this;
        }

        try {
            \diCore\Entity\AdminTableEditLog\Model::createForDeletion(
                $m,
                $this->getEditLogAdminId()
            )->save();
        } catch (\Throwable $e) {
            // logging must never break the admin action
        }

        return $this;
    }

    protected function getEditLogAdminId()
    {
        $admin = $this->getAdminModel();

        return $admin ? $admin->getId() : 0;
    }

    /**
     * Edit-log on a list toggle/delete is gated by the same useEditLog() flag the
     * admin page already exposes — resolved from the table name (no table→page
     * registry exists, so we map via the module naming convention).
     *
     * These actions are served over /api/ where the admin Base ($X) is NOT built,
     * so we can't construct the page (its constructor needs Base and may have side
     * effects). We instantiate it WITHOUT the constructor and call useEditLog() —
     * the overrides are plain flag returns. Anything that needs page state throws
     * and is treated as "no logging".
     */
    protected function shouldLogAdminEdit(\diModel $m)
    {
        $table = $m->getTable();

        if (array_key_exists($table, self::$editLogEnabledCache)) {
            return self::$editLogEnabledCache[$table];
        }

        $enabled = false;

        try {
            $pageClass = \diCore\Admin\Base::getModuleClassName($table);

            if (
                $pageClass &&
                class_exists($pageClass) &&
                is_subclass_of($pageClass, \diCore\Admin\BasePage::class)
            ) {
                /** @var \diCore\Admin\BasePage $page */
                $page = (new \ReflectionClass($pageClass))->newInstanceWithoutConstructor();
                $enabled = (bool) $page->useEditLog();
            }
        } catch (\Throwable $e) {
            $enabled = false;
        }

        return self::$editLogEnabledCache[$table] = $enabled;
    }

    /**
     * @param diModel $m
     * @return $this
     */
    protected function postProcess(\diModel $m)
    {
        switch ($m->getTable()) {
            case 'content':
                $Z = new \diCurrentCMS();
                $Z->build_content_table_cache();
                break;

            case 'orders':
                $this->getDb()->delete(
                    'order_items',
                    "WHERE order_id='{$m->getId()}'"
                );
                $this->getDb()->delete(
                    'actions_log',
                    "WHERE type='{$m->getTable()}' and target_id='{$m->getId()}'"
                );
                break;
        }

        return $this;
    }

    /**
     * @param diModel $m
     * @param array $collection
     * @return array
     */
    private function getFamilyCollection(\diModel $m, $collection = [])
    {
        if ($m->has('parent')) {
            $col = \diCollection::createForTable($m->getTable())->filterBy(
                'parent',
                $m->getId()
            );

            /** @var \diModel $model */
            foreach ($col as $model) {
                /** @var \diModel $_m */
                foreach ($collection as $_m) {
                    if ($model->getId() == $_m->getId()) {
                        //\diCore\Tool\Logger::getInstance()->log('Parent cycling detected');
                        throw new \Exception('Parent cycling detected');
                    }
                }

                $collection[] = $model;

                $collection = $this->getFamilyCollection($model, $collection);
            }
        }

        return $collection;
    }
}
