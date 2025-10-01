<?php

namespace unit\Utils\Tools;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Utils\Tools\CatUtils;

class CatUtilsTest extends TestCase {
    /**
     * Test that a valid project name is returned as is.
     */
    public function testSanitizeOrFallbackProjectNameWithValidName() {
        $validName = "Valid_Project_Name";
        $result    = CatUtils::sanitizeOrFallbackProjectName( $validName, [] );
        $this->assertEquals( $validName, $result );
    }

    /**
     * Test that an invalid project name is sanitized.
     */
    public function testSanitizeOrFallbackProjectNameWithInvalidName() {
        $invalidName = "Invalid@Project#Name!";
        $this->expectException( InvalidArgumentException::class );
        CatUtils::sanitizeOrFallbackProjectName( $invalidName, [] );
    }

    /**
     * Test that a fallback name is generated when input name is empty
     * and more than one file is given.
     */
    public function testFallbackNameGeneratedWhenNameIsEmptyAndMultipleFilesProvided() {
        $files  = [
                [ 'name' => 'file1.txt' ],
                [ 'name' => 'file2.txt' ]
        ];
        $result = CatUtils::sanitizeOrFallbackProjectName( "", $files );
        $this->assertStringStartsWith( 'MATECAT_PROJ-', $result );
    }

    /**
     * Test that the fallback name is based on file name when name is empty
     * and exactly one file is provided.
     */
    public function testFallbackNameBasedOnSingleFile() {
        $files        = [
                [ 'name' => 'example_file_name.txt' ]
        ];
        $expectedName = "example_file_name";
        $result       = CatUtils::sanitizeOrFallbackProjectName( "", $files );
        $this->assertEquals( $expectedName, $result );
    }

    /**
     * Test that an empty input name with no files provided results in
     * a generated fallback project name.
     */
    public function testFallbackNameGeneratedWhenNameIsEmptyAndNoFilesProvided() {
        $result = CatUtils::sanitizeOrFallbackProjectName( "", [] );
        $this->assertStringStartsWith( 'MATECAT_PROJ-', $result );
    }
}