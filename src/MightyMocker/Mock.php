<?php

namespace MightyMocker;

use Closure;
use DomainException;
use InvalidArgumentException;
use LogicException;
use ReflectionClass;
use ReflectionMethod;
use SplObjectStorage;

class Mock
{
    /** @var Mock[] */
    private static $_classNamesToMocks = [];
    private $_className;
    private $_mockName;
    private $_methods = [];
    private $_methodClosures;
    private $_propertyClosures;

    protected function __construct($className, $mockName)
    {
        $this->_className        = $className;
        $this->_mockName         = $mockName;
        $this->_methodClosures   = new SplObjectStorage();
        $this->_propertyClosures = new SplObjectStorage();
    }

    public static function create($className, $mockName, $filePath)
    {
        $mock = new self($className, $mockName);

        if (!array_key_exists($className, self::$_classNamesToMocks)) {
            $realClassCode = $mock->getClassRewriteCode($className, $mockName, $filePath);
            eval($realClassCode);
            $mockClassCode = $mock->getMockClassCode($className, $mockName);
            eval($mockClassCode);
        }

        self::$_classNamesToMocks[$className] = $mock;

        return $mock;
    }

    public static function getMethodClosure($methodName, $className, $target, $args)
    {
        $mock = self::$_classNamesToMocks[$className];
        return $mock->getBoundMethodClosure($methodName, $className, $target, $args);
    }

    public function methods(array $methods)
    {
        foreach ($methods as $k => $v) {
            if (is_int($k)) {
                // $method, $value = $v
                $this->method($v[0], $v[1]);
            } else {
                $this->method($k, $v);
            }
        }
        return $this;
    }

    public function method($method, $value)
    {
        if ($method instanceof Closure) {
            $this->_methodClosures->attach($method, $value);
        } elseif (is_string($method)) {
            $this->_methods[$method] = $value;
        } else {
            throw new InvalidArgumentException('Expected $method to be string or instance of Closure.');
        }
        return $this;
    }

    public function clearMethods(array $methods)
    {
        foreach ($methods as $method) {
            if (is_string($method)) {
                unset($this->_methods[$method]);
            } else {
                $this->_methodClosures->detach($method);
            }
        }
        return $this;
    }

    protected function getClassRewriteCode($className, $mockName, $filePath)
    {
        if (class_exists($className, false)) {
            throw new LogicException("Cannot mock class {$className} because it has already been loaded.");
        }

        $mockNameParts = explode('\\', $mockName);
        $mockName = end($mockNameParts);

        if (!preg_match('#^[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*$#', $mockName)) {
            throw new DomainException('Invalid $mockName specified, must be valid class name, given ' . $mockName);
        }

        $mockName = preg_quote($mockName);

        // get contents of real class
        $content = file_get_contents($filePath);

        // strip final keyword and rename class to {classname}_real
        $content = preg_replace(
            '#\n\s*(?:final\s+)?class\s+([^\s{]+)(\s?[^{]+{)#is',
            "\nclass $mockName\\2",
            $content,
            1
        );

        // strip beginning and ending php tags
        $content = preg_replace(['#^<\?php\s+#', '#\?>$#'], ['', ''], $content);

        // replace any __FILE__ and __DIR__ magic references since they will not work in eval code
        $content = str_replace(
            [
                '__FILE__',
                '__DIR__',
            ],
            [
                var_export(realpath($filePath), true),
                var_export(dirname(realpath($filePath)), true),
            ],
            $content
        );

        return $content;
    }

    protected function getMockClassCode($className, $mockName)
    {
        $classNameParts = explode('\\', $className);
        $className = end($classNameParts);

        $ref             = new ReflectionClass($mockName);
        $mockableMethods = $ref->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_PROTECTED);
        $methodContents  = [];
        foreach ($mockableMethods as $method) {
            $methodName = $method->getName();

            $body = sprintf(
                "\$c = \\%s::getMethodClosure('%s', self::class, %s, func_get_args()); return \$c();",
                self::class,
                $methodName,
                $method->isStatic() ? 'null' : '$this',
                $methodName
            );

            // build list of parameters
            $parameters = [];
            foreach ($method->getParameters() as $param) {
                // Example: 'Parameter #1 [ <required> array $update ]'
                $parameters[] = preg_replace('#^.*?\[ <(?:required|optional)> (.+)\]$#', '\\1', (string)$param);
            }

            $parts = [];
            if ($method->isPublic()) {
                $parts[] = 'public';
            }
            if ($method->isProtected()) {
                $parts[] = 'protected';
            }
            if ($method->isStatic()) {
                $parts[] = 'static';
            }

            $parts[]          = 'function';
            $parts[]          = $methodName.'('.implode(', ', $parameters).')';
            $methodContents[] = implode(' ', $parts).'{ '.$body.' }';
        }

        $content = strtr(
            'namespace {namespace} { class {classname} extends \{mockname} { {methods} }}',
            [
                '{namespace}' => $ref->getNamespaceName(),
                '{classname}' => $className,
                '{mockname}'  => $mockName,
                '{methods}'   => implode("\n", $methodContents),
            ]
        );

        return $content;
    }

    private function getBoundMethodClosure($methodName, $className, $target, $args) {
        $mockValue = null;
        $found     = false;

        if (array_key_exists($methodName, $this->_methods)) {
            $mockValue = $this->_methods[$methodName];
            $found     = true;
        } elseif (count($this->_methodClosures) > 0) {
            // search mock closures to see if we match any of them
            $this->_methodClosures->rewind();
            while ($this->_methodClosures->valid()) {
                /** @var Closure $closure */
                $closure = $this->_methodClosures->current();
                if ($closure($methodName)) {
                    // use corresponding value of this mock closure since we match
                    $mockValue = $this->_methodClosures->getInfo();
                    $found     = true;
                    break;
                }
                $this->_methodClosures->next();
            }
        }

        if ($found) {
            if ($mockValue instanceof Closure) {
                // call the mockValue via closure and return its value
                $mockValue = $mockValue->bindTo($target, $className);
                $closure = function () use ($mockValue, $args) {
                    return $mockValue(...$args);
                };
            } else {
                // return mock value via closure
                $closure = function () use ($mockValue) {
                    return $mockValue;
                };
            }
        } else {
            // we will call the parent because we are not mocking this method
            $closure = function() use ($methodName, $args) {
                return parent::$methodName(...$args);
            };
        }

        // bind the closure to the calling class
        $closure = $closure->bindTo($target, $className);

        return $closure;
    }

}
