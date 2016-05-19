<?php

namespace MightyMocker;

require_once(__DIR__.'/AbstractFood.php');
require_once(__DIR__.'/CookableInterface.php');

final class Pizza extends AbstractFood implements CookableInterface
{

    protected $progress;
    protected $toppings = [];

    public function addMushrooms()
    {
        $this->addTopping('mushrooms');
    }

    public function addCheese()
    {
        $this->addTopping('cheese');
    }

    public function addAnchovies()
    {
        $this->addTopping('anchovies');
    }

    public function cook()
    {
        if ($this->progress !== self::PROGRESS_PREP) {
            throw new \Exception('We can only cook pizzas that are in the preparation step!');
        }
        $this->setProgress(self::PROGRESS_COOK);
    }

    public function getDescription()
    {
        $description = '';

        if ($this->isDisgusting()) {
            $description .= 'disgusting ';
        }

        $description .= 'pizza';

        if (!empty($this->toppings)) {
            $description .= ' with '.implode(', ', $this->toppings);
        }

        return $description;
    }

    protected function addTopping($name)
    {
        $this->toppings[] = $name;
    }

    private function isDisgusting()
    {
        return $this->hasTopping('anchovies');
    }

    public function hasTopping($name)
    {
        return in_array($name, $this->toppings);
    }

    public static function createFavorite() {
        $p = new Pizza();
        $p->addCheese();
        return $p;
    }

}
