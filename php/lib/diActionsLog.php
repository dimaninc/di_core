<?php
/**
 * Created by PhpStorm.
 * User: dimaninc
 * Date: 15.06.2015
 * Time: 23:54
 */

class diActionsLog
{
    const logTable = 'di_actions_log';
    const childClassName = 'diCustomActionsLog';

    // user types
    const utAdmin = 1;
    const utUser = 2;

    // base admin actions
    const aAdded = 1;
    const aEdited = 2;
    const aDeleted = 3;
    const aOwned = 4;
    const aAccepted = 5;
    const aDeclined = 6;
    const aMadeVisible = 7;
    const aMadeInvisible = 8;
    const aCommented = 9;
    const aUploaded = 10;
    const aStatusChanged = 11;
    const aTagAdded = 12;
    const aTagRemoved = 13;
    const aUploadDeleted = 14;

    // admin actions: orders
    const aShipmentAdded = 21;
    const aItemAdded = 22;
    const aItemEdited = 23;
    const aItemDeleted = 24;
    const aOrderStatusChanged = 25;

    // admin actions: tasks
    const aPriorityChanged = 31;

    // base user actions
    const uAdded = 101;
    const uEdited = 102;
    const uDeleted = 103;

    public static $actionStrAr = [
        self::aAdded => 'Админ добавил',
        self::aEdited => 'Админ отредактировал',
        self::aDeleted => 'Админ удалил',
        self::aOwned => 'Админ назначил',
        self::aAccepted => 'Админ принял',
        self::aDeclined => 'Админ отклонил',
        self::aMadeVisible => 'Админ включил видимость',
        self::aMadeInvisible => 'Админ отключил видимость',
        self::aCommented => 'Админ прокомментировал',
        self::aUploaded => 'Админ загрузил файл',
        self::aStatusChanged => 'Админ изменил статус',
        self::aTagAdded => 'Админ добавил тег(и)',
        self::aTagRemoved => 'Админ удалил тег(и)',
        self::aUploadDeleted => 'Админ удалил файл',

        self::aShipmentAdded => 'Админ добавил доставку',
        self::aItemAdded => 'Админ добавил товар',
        self::aItemEdited => 'Админ отредактировал товар',
        self::aItemDeleted => 'Админ удалил товар',

        self::aPriorityChanged => 'Админ изменил приоритет',

        self::uAdded => 'Пользователь добавил',
        self::uEdited => 'Пользователь отредактировал',
        self::uDeleted => 'Пользователь удалил',
    ];

    /** @var diDB */
    private $db;

    protected $targetType;
    protected $targetId;

    public function __construct($targetType, $targetId)
    {
        global $db;

        $this->db = $db;

        $this->targetType = $targetType;
        $this->targetId = $targetId;

        $this->initTable();
    }

    /**
     * @param $targetType
     * @param $targetId
     * @return diActionsLog
     */
    public static function create($targetType, $targetId)
    {
        if (diLib::exists(static::childClassName)) {
            $className = static::childClassName;
        } else {
            $className = get_called_class();
        }

        return new $className($targetType, $targetId);
    }

    public static function act($targetType, $targetId, $action, $options = [])
    {
        $al = new static($targetType, $targetId);
        $al->saveLog($action, $options);
    }

    public static function get($targetType, $targetId, $filtersAr = [])
    {
        $al = new static($targetType, $targetId);

        return $al->getLogAr($filtersAr);
    }

    public static function lastLogRecord($targetType, $targetId)
    {
        $al = new static($targetType, $targetId);

        return $al->getLastLogRecord();
    }

    public function getLogAr($filtersAr = [])
    {
        $filtersAr = extend(
            [
                '_sortby' => 'id',
                '_dir' => 'ASC',
            ],
            $filtersAr
        );

        $queryAr = [
            "target_type = '{$this->targetType}'",
            "target_id = '{$this->targetId}'",
        ];

        foreach ($filtersAr as $field => $value) {
            if (substr($field, 0, 1) == '_') {
                continue;
            }

            $queryAr[] =
                "$field = '" . $this->getDb()->escape_string($value) . "'";
        }

        $orderBy =
            ' ORDER BY ' . $filtersAr['_sortby'] . ' ' . $filtersAr['_dir'];

        if (isset($filtersAr['_start']) && isset($filtersAr['_per_page'])) {
            $limit =
                ' LIMIT ' .
                $filtersAr['_start'] .
                ',' .
                $filtersAr['_per_page'];
        } elseif (isset($filtersAr['_per_page'])) {
            $limit = ' LIMIT ' . $filtersAr['_per_page'];
        } else {
            $limit = '';
        }

        $ar = [];

        $rs = $this->getDb()->rs(
            self::logTable,
            'WHERE ' . join(' and ', $queryAr) . $orderBy . $limit
        );
        while ($r = $this->getDb()->fetch($rs)) {
            $ar[] = $r;

            if (!isset($firstR)) {
                $firstR = $r;
            }
        }

        if (
            (!isset($filtersAr['action']) ||
                $filtersAr['action'] == self::uAdded) &&
            (!isset($firstR) ||
                !in_array($firstR->action, [self::uAdded, self::aAdded]))
        ) {
            $position =
                strtoupper($filtersAr['_dir']) == 'DESC' ? count($ar) : 0;
            $userType = self::getFirstActionUserType($this->targetType);

            array_splice($ar, $position, 0, [
                $this->getVirtualLogRow([
                    'action' =>
                        $userType == self::utAdmin
                            ? self::aAdded
                            : self::uAdded,
                    'user_type' => $userType,
                ]),
            ]);
        }

        return $ar;
    }

    public function getLastLogRecord()
    {
        $ar = $this->getLogAr();

        return $ar[count($ar) - 1];
    }

    public static function getFirstActionUserType($targetType)
    {
        if (in_array($targetType, [diTypes::admin_task, diTypes::admin_wiki])) {
            return self::utAdmin;
        }

        return self::utUser;
    }

    public static function getTargetTypeFromStr($s)
    {
        if (is_numeric($s)) {
            return $s;
        }

        return diTypes::getId($s);
    }

    private function getVirtualLogRow($options = [])
    {
        $r = $this->getDb()->r(
            self::getTableByType($this->targetType),
            $this->targetId
        );

        $row = [
            'id' => -1,
            'target_type' => $this->targetType,
            'target_id' => $this->targetId,
            'user_type' => self::utUser,
            'user_id' => 0,
            'action' => null,
            'info' => '',
        ];

        if (isset($r->date)) {
            $row['date'] = is_numeric($r->date)
                ? date('Y-m-d H:i:s', $r->date)
                : $r->date;
        }

        return (object) extend($row, $options);
    }

    public function preProcess($action, $options)
    {
        $c = self::childClassName;

        if (class_exists($c) && method_exists($c, 'preProcess')) {
            $c::preProcess($this, $action, $options);
        }
    }

    public function postProcess($action, diDiActionsLogModel $log)
    {
        $c = self::childClassName;

        if (class_exists($c) && method_exists($c, 'postProcess')) {
            $c::postProcess($this, $action, $log);
        }
    }

    public function saveLog($action, $options = [])
    {
        $this->preProcess($action, $options);

        if (!is_array($options)) {
            $options = [
                'info' => $options,
            ];
        }

        $options = extend(
            [
                'info' => '',
            ],
            $options
        );

        $userType = self::getUserType($action);

        /** @var diDiActionsLogModel $log */
        $log = \diModel::create(self::logTable);
        $log->setTargetType($this->targetType)
            ->setTargetId($this->targetId)
            ->setUserType($userType)
            ->setUserId(self::getUserId($userType))
            ->setAction($action)
            ->setInfo($options['info'])
            ->save();

        $this->postProcess($action, $log);

        return $this;
    }

    public static function getTableByType($type)
    {
        return diTypes::getTable($type);
    }

    public static function getActionStr($action)
    {
        return isset(static::$actionStrAr[$action])
            ? static::$actionStrAr[$action]
            : "action#$action";
    }

    public static function parseSimpleCommaSeparatedInfo($info, $count = 2)
    {
        return array_merge(explode(',', $info), array_fill(0, $count, null));
    }

    public static function getSimpleInfoFromDataSource($dataSource, $old, $new)
    {
        $ar = [];

        if ($old) {
            $ar[] = call_user_func($dataSource, $old);
        }

        if ($new) {
            $ar[] = call_user_func($dataSource, $new);
        }

        return join(' &raquo; ', $ar);
    }

    public static function getSimpleListFromDataSource($dataSource, $ar)
    {
        $ar = call_user_func($dataSource, $ar);

        return join(
            ', ',
            array_map(function ($r) {
                return $r->title;
            }, $ar)
        );
    }

    public static function getActionInfoStr($r)
    {
        switch ($r->action) {
            case static::aStatusChanged:
            case static::aPriorityChanged:
                list($old, $new) = self::parseSimpleCommaSeparatedInfo(
                    $r->info
                );

                if ($r->action == self::aStatusChanged) {
                    $entity = 'status';
                } elseif ($r->action == self::aPriorityChanged) {
                    $entity = 'priority';
                } else {
                    $entity = '';
                }

                switch ($r->target_type) {
                    case diTypes::admin_task:
                        $dataSource =
                            \diCore\Entity\AdminTask\Model::class .
                            "::{$entity}Str";
                        break;

                    case diTypes::order:
                        $dataSource = 'diOrderModel::getStatusStr';
                        break;

                    default:
                        throw new Exception(
                            "No data source for target type #{$r->target_type} defined"
                        );
                }

                return self::getSimpleInfoFromDataSource(
                    $dataSource,
                    $old,
                    $new
                );

            case static::aOwned:
                list($old, $new) = self::parseSimpleCommaSeparatedInfo(
                    $r->info
                );

                $dataSource = 'self::getUserAppearance';

                return self::getSimpleInfoFromDataSource(
                    $dataSource,
                    $old,
                    $new
                );

            case static::aTagAdded:
            case static::aTagRemoved:
                list($class, $tagsStr) = explode(':', $r->info);

                if ($class && diLib::exists($class)) {
                    $tagIds = self::parseSimpleCommaSeparatedInfo($tagsStr);

                    $dataSource = "$class::tagsByIds";

                    return self::getSimpleListFromDataSource(
                        $dataSource,
                        $tagIds
                    );
                } else {
                    return $tagsStr;
                }
        }

        return $r->info;
    }

    public static function getUserType($action)
    {
        return $action < 100 ? self::utAdmin : self::utUser;
    }

    public static function getUserId($type)
    {
        switch ($type) {
            case self::utAdmin:
                /** @var diAdminUser $adminUser */
                $adminUser = diAdminUser::create();

                return $adminUser->getModel()->exists()
                    ? $adminUser->getModel()->getId()
                    : null;

            case self::utUser:
                return diAuth::i()->getUserId();
        }

        return null;
    }

    public static function getUserAppearance($rowOrUserId, $userType = null)
    {
        if (!is_object($rowOrUserId)) {
            $rowOrUserId = (object) [
                'user_type' => $userType ?: self::utAdmin,
                'user_id' => $rowOrUserId,
            ];
        }

        switch ($rowOrUserId->user_type) {
            case self::utAdmin:
                /** @var \diCore\Entity\Admin\Model $admin */
                $admin = diModel::create(diTypes::admin, $rowOrUserId->user_id);

                return $admin->exists()
                    ? $admin->getLogin()
                    : "admin#$rowOrUserId->user_id";

            case self::utUser:
                /** @var \diCore\Entity\User\Model $user */
                $user = diModel::create(diTypes::user, $rowOrUserId->user_id);

                return $user->exists()
                    ? $user->getEmail()
                    : "user#$rowOrUserId->user_id";
        }

        return null;
    }

    protected function getDb()
    {
        return $this->db;
    }

    private function initTable()
    {
        $res = $this->getDb()->q(
            'CREATE TABLE IF NOT EXISTS `' .
                static::logTable .
                "`(
			id BIGINT NOT NULL AUTO_INCREMENT,
			target_type INT UNSIGNED,
			target_id BIGINT,
			user_type TINYINT,
			user_id BIGINT,
			action TINYINT UNSIGNED,
			info VARCHAR(250) DEFAULT '',
			date TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			INDEX target_idx(target_type,target_id),
			INDEX user_idx(user_type,user_id),
			PRIMARY KEY(id)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci"
        );

        if (!$res) {
            throw new Exception(
                'Unable to init Table: ' . $this->getDb()->getLogStr()
            );
        }

        return $this;
    }
}
