<?php

namespace MightyMocker;

use MightyMocker\MightyMocker as mighty;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class MightyMockerTest extends \PHPUnit_Framework_TestCase
{
    const FILE_PIZZA = __DIR__.'/Pizza.php';

    public function testRegisterPathFinder()
    {
        $c = function($className){
            return __DIR__ . '/' . $className . '.php';
        };
        mighty::registerPathFinder($c);

        $ref = new \ReflectionClass(mighty::class);
        $prop = $ref->getProperty('pathFinders');
        $prop->setAccessible(true);
        /** @var \SplObjectStorage $paths */
        $paths = $prop->getValue();
        $this->assertTrue($paths->contains($c));
    }

    public function testUnregisterPathFinder()
    {
        $c = function($className){
            return __DIR__ . '/' . $className . '.php';
        };

        $ref = new \ReflectionClass(mighty::class);
        $prop = $ref->getProperty('pathFinders');
        $prop->setAccessible(true);

        mighty::registerPathFinder($c);
        
        /** @var \SplObjectStorage $paths */
        $paths = $prop->getValue();
        $this->assertTrue($paths->contains($c));

        mighty::unregisterPathFinder($c);
        $this->assertFalse($paths->contains($c));
    }

    public function testClearPathFinders()
    {
        $c = function($className){
            return __DIR__ . '/' . $className . '.php';
        };
        mighty::registerPathFinder($c);

        $ref = new \ReflectionClass(mighty::class);
        $prop = $ref->getProperty('pathFinders');
        $prop->setAccessible(true);
        /** @var \SplObjectStorage $paths */
        $paths = $prop->getValue();

        $paths->attach($c);
        $this->assertTrue($paths->contains($c));

        mighty::clearPathFinders();
        $paths = $prop->getValue();
        $this->assertFalse($paths->contains($c));
    }

    public function testPathFinderResolution()
    {
        $pathPrefix = __DIR__ . '/';
        $pathSuffix = '.php';

        $c = function($className) use ($pathPrefix, $pathSuffix) {
            return $pathPrefix . $className . $pathSuffix;
        };

        mighty::clearPathFinders();
        mighty::registerPathFinder($c);
        $ref = new \ReflectionClass(mighty::class);
        $method = $ref->getMethod('resolveClassFilePath');
        $method->setAccessible(true);
        $filePath = $method->invoke(null, 'Pizza');
        $this->assertSame($pathPrefix . 'Pizza' . $pathSuffix, $filePath);
    }

    public function testPathFinderRepeatResolution()
    {
        $pathPrefix = 'bogus';
        $pathSuffix = '.php';

        $c = function($className) use ($pathPrefix, $pathSuffix) {
            return $pathPrefix . $className . $pathSuffix;
        };

        mighty::clearPathFinders();
        mighty::registerPathFinder($c);
        $ref = new \ReflectionClass(mighty::class);
        $method = $ref->getMethod('resolveClassFilePath');
        $method->setAccessible(true);

        $filePath = $method->invoke(null, 'Pizza');
        $this->assertSame($pathPrefix . 'Pizza' . $pathSuffix, $filePath);

        mighty::unregisterPathFinder($c);
        $filePath = $method->invoke(null, 'Pizza');
        $this->assertSame($pathPrefix . 'Pizza' . $pathSuffix, $filePath);
    }

    public function testPathFinderMultipleResolution()
    {
        $pathPrefix = __DIR__ . '/';
        $pathSuffix = '.php';

        $a = function($className) {
            return null;
        };

        $c = function($className) use ($pathPrefix, $pathSuffix) {
            return $pathPrefix . $className . $pathSuffix;
        };

        mighty::clearPathFinders();
        mighty::registerPathFinder($a);
        mighty::registerPathFinder($c);
        $ref = new \ReflectionClass(mighty::class);
        $method = $ref->getMethod('resolveClassFilePath');
        $method->setAccessible(true);

        $filePath = $method->invoke(null, 'Pizza');
        $this->assertSame($pathPrefix . 'Pizza' . $pathSuffix, $filePath);
    }

    public function testPathFinderMissing()
    {
        $a = function($className) {
            return null;
        };

        mighty::clearPathFinders();
        $ref = new \ReflectionClass(mighty::class);
        $method = $ref->getMethod('resolveClassFilePath');
        $method->setAccessible(true);

        $filePath = $method->invoke(null, 'Pizza');
        $this->assertNull($filePath);

        mighty::registerPathFinder($a);
        $filePath = $method->invoke(null, 'Pizza');
        $this->assertNull($filePath);
    }

    public function testPathFinderAlreadyUnregistered()
    {
        $a = function($className) {
            return null;
        };

        mighty::unregisterPathFinder($a);
        $ref = new \ReflectionClass(mighty::class);
        $prop = $ref->getProperty('pathFinders');
        $prop->setAccessible(true);
        /** @var \SplObjectStorage $paths */
        $paths = $prop->getValue();
        $this->assertEquals(0, count($paths));
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Cannot find className mightymocker_bogus_class
     */
    public function testMockFailedPath()
    {
        mighty::mock('mightymocker_bogus_class');
    }
}
