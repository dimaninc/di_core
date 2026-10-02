<?php

namespace diCore\Tests\Admin;

use diCore\Admin\Base;
use diCore\Entity\Admin\Level;
use PHPUnit\Framework\TestCase;

/**
 * Admin\Base::canAccessModule(): the same per-module gate checkRights()
 * applies to a normally routed request's $this->module/$this->method (which now
 * delegates to it), exposed so code that builds a page outside normal routing
 * can apply it too – Controller\AdminTableEditLog is the first caller:
 * BasePage::liteCreate() only constructs a page, it runs none of the routing
 * checks Admin\Base::work() normally would, so without this a restricted admin
 * could read any module's edit log just by knowing its slug.
 */
class CanAccessModuleTest extends TestCase
{
    public function testDeniedWhenNotAuthorized(): void
    {
        $base = CanAccessModuleProbeBase::make(false, Level::root);

        $this->assertFalse($base->canAccessModule('allowed_module'));
    }

    public function testGrantedForAModuleThePermissionLevelCovers(): void
    {
        $base = CanAccessModuleProbeBase::make(true, Level::root);

        $this->assertTrue($base->canAccessModule('allowed_module'));
    }

    public function testDeniedForAModuleOutsideEveryGroupsPaths(): void
    {
        $base = CanAccessModuleProbeBase::make(true, Level::root);

        $this->assertFalse($base->canAccessModule('unregistered_module'));
    }

    public function testDeniedWhenAdminLevelDoesNotMatchThePermission(): void
    {
        $base = CanAccessModuleProbeBase::make(true, 'editor');

        $this->assertFalse($base->canAccessModule('allowed_module'));
    }

    /**
     * 'form'/'submit' collapse to the same "{module}_form" path checkRights()
     * checks for a routed form request – a module registered only for 'list'
     * (Admin\Page\Configuration's own menu entry has no "_form" path at all,
     * since the settings page has no separate form route) must not grant the
     * form-shaped check just because the bare module is reachable.
     */
    public function testMethodSuffixMustMatchARegisteredPath(): void
    {
        $base = CanAccessModuleProbeBase::make(true, Level::root);

        $this->assertTrue($base->canAccessModule('allowed_module', 'form'));
        $this->assertTrue($base->canAccessModule('list_only_module'));
        $this->assertFalse($base->canAccessModule('list_only_module', 'form'));
    }
}

class CanAccessModuleProbeBase extends Base
{
    public $tree = [];

    public static function make($authorized, $level): self
    {
        /** @var self $base */
        $base = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();

        $adminUserProp = new \ReflectionProperty(Base::class, 'adminUser');
        $adminUserProp->setAccessible(true);
        $adminUserProp->setValue(
            $base,
            new CanAccessModuleProbeAdminUser($authorized, $level)
        );

        $base->tree = [
            'Group' => [
                'paths' => ['allowed_module', 'allowed_module_form', 'list_only_module'],
                'permissions' => [Level::root],
            ],
        ];

        return $base;
    }

    protected function getAdminMenuFullTree()
    {
        return $this->tree;
    }
}

class CanAccessModuleProbeAdminUser
{
    private $authorized;
    private $model;

    public function __construct($authorized, $level)
    {
        $this->authorized = $authorized;
        $this->model = new CanAccessModuleProbeAdminModel($level);
    }

    public function reallyAuthorized()
    {
        return $this->authorized;
    }

    public function authorizedForSetup()
    {
        return false;
    }

    public function getModel()
    {
        return $this->model;
    }
}

class CanAccessModuleProbeAdminModel
{
    private $level;

    public function __construct($level)
    {
        $this->level = $level;
    }

    public function getLevel()
    {
        return $this->level;
    }

    public function getLogin()
    {
        return 'probe';
    }
}
