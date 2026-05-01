<?php

namespace unit\DAO\TestFiltersConfigTemplateDAO;

use Exception;
use Model\Filters\FiltersConfigTemplateDao;
use Model\Filters\FiltersConfigTemplateStruct;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class FiltersConfigTemplateDaoTest extends AbstractTest
{
    #[Test]
    public function saveThrowsWhenUidIsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->uid = null;
        $struct->name = 'test';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        FiltersConfigTemplateDao::save($struct);
    }

    #[Test]
    public function saveThrowsWhenNameIsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->uid = 1;
        $struct->name = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('name');

        FiltersConfigTemplateDao::save($struct);
    }

    #[Test]
    public function updateThrowsWhenIdIsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->id = null;
        $struct->uid = 1;
        $struct->name = 'test';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('id');

        FiltersConfigTemplateDao::update($struct);
    }

    #[Test]
    public function updateThrowsWhenUidIsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->id = 1;
        $struct->uid = null;
        $struct->name = 'test';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('uid');

        FiltersConfigTemplateDao::update($struct);
    }

    #[Test]
    public function updateThrowsWhenNameIsNull(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->id = 1;
        $struct->uid = 1;
        $struct->name = null;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('name');

        FiltersConfigTemplateDao::update($struct);
    }

    #[Test]
    public function saveDoesNotThrowNullGuardWhenPropertiesAreSet(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->uid = 1;
        $struct->name = 'test-filter';

        // Guard passes, fails on DB connection — not on null property exception
        try {
            FiltersConfigTemplateDao::save($struct);
            $this->fail('Expected an exception (DB or otherwise)');
        } catch (Exception $e) {
            $this->assertStringNotContainsString('must not be null', strtolower($e->getMessage()));
        }
    }

    #[Test]
    public function updateDoesNotThrowNullGuardWhenPropertiesAreSet(): void
    {
        $struct = new FiltersConfigTemplateStruct();
        $struct->id = 999;
        $struct->uid = 1;
        $struct->name = 'test-filter';

        // Guard passes, fails on DB connection — not on null property exception
        try {
            FiltersConfigTemplateDao::update($struct);
            $this->fail('Expected an exception (DB or otherwise)');
        } catch (Exception $e) {
            $this->assertStringNotContainsString('must not be null', strtolower($e->getMessage()));
        }
    }
}
