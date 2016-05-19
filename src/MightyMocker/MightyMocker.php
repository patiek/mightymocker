<?php

namespace MightyMocker;

use Closure;
use Exception;
use SplObjectStorage;

class MightyMocker
{
    private static $classFilePaths = [];
    /** @var SplObjectStorage $pathFinders */
    private static $pathFinders;

    public static function mock($className, $classFilePath = null)
    {
        if (!isset($classFilePath)
            && null === ($classFilePath = self::resolveClassFilePath($className))) {
            throw new Exception('Cannot find className '.$className);
        }

        return Mock::create($className, $className.'_real', $classFilePath);
    }

    public static function registerPathFinder(Closure $pathFinder)
    {
        if (!isset(self::$pathFinders)) {
            self::clearPathFinders();
        }
        self::$pathFinders->attach($pathFinder);
    }

    public static function unregisterPathFinder(Closure $pathFinder)
    {
        if (!isset(self::$pathFinders)) {
            self::clearPathFinders();
        }
        self::$pathFinders->detach($pathFinder);
    }

    public static function clearPathFinders()
    {
        self::$pathFinders = new SplObjectStorage();
    }

    protected static function resolveClassFilePath($className)
    {
        if (isset(self::$classFilePaths[$className])) {
            return self::$classFilePaths[$className];
        } elseif (isset(self::$pathFinders)) {
            self::$pathFinders->rewind();
            while (self::$pathFinders->valid()) {
                /** @var Closure $pathFinder */
                $pathFinder = self::$pathFinders->current();
                if ('' !== ($path = (string)$pathFinder($className))) {
                    self::$classFilePaths[$className] = $path;

                    return $path;
                }
                self::$pathFinders->next();
            }
        }

        return null;
    }
}