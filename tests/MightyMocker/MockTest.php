<?php

namespace MightyMocker;

use MightyMocker\MightyMocker as mighty;

class MockTest extends \PHPUnit_Framework_TestCase
{
    const FILE_PIZZA = __DIR__.'/Pizza.php';

    /**
     * @expectedException \DomainException
     */
    public function testBadClassName()
    {
        Mock::create('bogus_class', 'bad name', self::FILE_PIZZA);
    }

    /**
     * @expectedException \LogicException
     */
    public function testClassAlreadyLoaded()
    {
        eval('class bogus_class {}');
        Mock::create('bogus_class', 'bogus_class_real', self::FILE_PIZZA);
    }


    public function testPublicMethodMockValue()
    {
        mighty::mock(Pizza::class, self::FILE_PIZZA)
              ->methods([
                  'getDescription' => 'gross',
              ]);
        $pizza = new Pizza();
        $this->assertSame('gross', $pizza->getDescription());
    }

    public function testPublicMethodMockClosure()
    {
        mighty::mock(Pizza::class, self::FILE_PIZZA)
              ->methods([
                  'addCheese' => function() {
                      /** @var Pizza $this */
                      $this->addTopping('stinky cheese');
                  },
              ]);
        $pizza = new Pizza();
        $pizza->addCheese();
        $this->assertTrue($pizza->hasTopping('stinky cheese'));
    }

    public function testProtectedMethodMockValue()
    {
        mighty::mock(Pizza::class, self::FILE_PIZZA)
              ->methods([
                  'addTopping' => null,
              ]);
        $pizza = new Pizza();
        $pizza->addMushrooms();
        $this->assertFalse($pizza->hasTopping('mushrooms'));
    }

    public function testProtectedMethodMockClosure()
    {
        mighty::mock(Pizza::class, self::FILE_PIZZA)
              ->methods([
                  'addTopping' => function($name) {
                      parent::addTopping('small ' . $name);
                  },
              ]);
        $pizza = new Pizza();
        $pizza->addMushrooms();
        $this->assertTrue($pizza->hasTopping('small mushrooms'));
    }

    public function testPatternMethodMockValue()
    {
        mighty::mock(Pizza::class, self::FILE_PIZZA)
              ->methods([
                  [
                      function($methodName){
                        return strpos($methodName, 'get') === 0;
                      },
                      'delicious pizza'
                  ],
                  [
                      function($methodName){
                          return strpos($methodName, 'is') === 0;
                      },
                      true
                  ]
              ]);
        $pizza = new Pizza();
        $this->assertSame('delicious pizza', $pizza->getDescription());
        $this->assertTrue($pizza->isEdible());
    }
    
    public function testClearMethods()
    {
        $hasToppingReplacement = function($methodName) {
            if ($methodName == 'hasTopping') {
                return true;
            }
        };
        $mock = mighty::mock(Pizza::class, self::FILE_PIZZA)
              ->methods([
                  'getDescription' => 'gross',
                  [
                      $hasToppingReplacement,
                      true
                  ]
              ]);
        $pizza = new Pizza();
        $this->assertSame('gross', $pizza->getDescription());
        $mock->clearMethods(['getDescription']);
        $this->assertSame('disgusting pizza', $pizza->getDescription());
        $mock->clearMethods([$hasToppingReplacement]);
        $this->assertSame('pizza', $pizza->getDescription());
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testInvalidMethodValueMock()
    {
        mighty::mock(Pizza::class, self::FILE_PIZZA)
            ->method(null, true);
    }
}
