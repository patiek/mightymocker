<?php

namespace MightyMocker;

abstract class AbstractFood
{
    const PROGRESS_PREP = 'prep';
    const PROGRESS_COOK = 'cook';
    const PROGRESS_DONE = 'done';

    public function __construct()
    {
        $this->progress = self::PROGRESS_PREP;
    }

    abstract public function getDescription();

    public function finish()
    {
        if ($this->progress !== self::PROGRESS_COOK) {
            throw new \Exception('We can only finish pizzas that are in the cooking step!');
        }
        $this->setProgress(self::PROGRESS_DONE);
    }

    protected function setProgress($progress)
    {
        $this->progress = $progress;
    }

    public function isEdible()
    {
        return $this->getProgress() === self::PROGRESS_DONE;
    }

    protected function getProgress()
    {
        return $this->progress;
    }
}