<?php

namespace VladReshet\Artisanui;

use Illuminate\Contracts\Console\Kernel;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;

class Artisanui
{
    public function __construct(Kernel $kernel)
    {
        $this->artisan = $kernel;
    }

    public function launch()
    {
        $this->commands = $this->getAllArtisanCommands();

        $this->title = env('APP_NAME') . " CLI UI";

        $menu = $this->buildMenu()
                     ->addLineBreak('-');
                     // ->setBorder(1, 2, 'yellow')
                     // ->setPadding(2, 4)
                     // ->setMarginAuto()
                     // ->setTitle($this->title);

        $menu->build()
             ->open();
    }

    private function getGroupCallback()
    {
        return function () {

        };
    }

    private function buildMenu()
    {
        $menu = new CliMenuBuilder();

        $title = $this->title;

        foreach ($this->commands as $group => $list) {
            $menu->addSubMenu($group, function (CliMenuBuilder $b) use ($list, $title, $group) {
                $b->setTitle("{$title} > {$group}");

                foreach ($list as $cmd) {
                    $b->addItem($cmd, function (CliMenu $menu) {
                        echo "exec " . $menu->getSelectedItem()->getText();
                    });
                }
            });
        }

        return $menu;
    }

    private function getAllArtisanCommands()
    {
        $all = array_keys($this->artisan->all());

        $groups = [];
        $common = [];

        foreach ($all as $name) {
            $cmd = explode(':', $name);

            $partsSize = count($cmd);

            if ($partsSize === 1 || $partsSize > 2) {
                $common[] = $name;

                continue;
            }

            if ($partsSize === 2) {
                if (!array_key_exists($cmd[0], $groups)) {
                    $groups[$cmd[0]] = [];
                }

                $groups[$cmd[0]][] = $cmd[1];
            }
        }

        ksort($groups);

        return array_merge([
            'common' => $common
        ], $groups);
    }
}
