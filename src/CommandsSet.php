<?php

namespace VladReshet\ArtisanUi;

// DTO for menu settings and commands
class CommandsSet
{
    private $commands = [
        'all' => [],
        'favourite' => [],
        'grouped' => []
    ];

    public function setAll(array $commands) :self
    {
        $this->commands['all'] = $commands;

        return $this;
    }

    public function setFavourite(array $commands) :self
    {
        $this->commands['favourite'] = $commands;

        return $this;
    }

    public function setGrouped(array $commands) :self
    {
        $this->commands['grouped'] = $commands;

        return $this;
    }

    public function getAll() :array
    {
        return $this->commands['all'];
    }

    public function getFavourite() :array
    {
        return $this->commands['favourite'];
    }

    public function getGrouped() :array
    {
        return $this->commands['grouped'];
    }

    public function hasFavourite() :bool
    {
        return !empty($this->commands['favourite']);
    }

    public function isFavourite(string $name) :bool
    {
        return in_array($name, $this->commands['favourite']);
    }
}
