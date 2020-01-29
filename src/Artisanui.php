<?php

namespace VladReshet\ArtisanUI;

use Illuminate\Contracts\Console\Kernel;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use PhpSchool\CliMenu\Style\CheckboxStyle;
use PhpSchool\CliMenu\Style\SelectableStyle;
use Closure;

class ArtisanUI
{
    const DELIMITER = "         |";

    private $artisan;
    private $commands;
    private $favourite;
    private $title;
    private $all;
    private $menu;

    public function __construct(Kernel $kernel)
    {
        $this->artisan = $kernel;
    }

    public function launch() :void
    {
        $this->getAllArtisanCommands()
             ->loadFavouriteCommands()
             ->loadTitle()
             ->buildMenu()
             ->stylishMenu()
             ->showMenu();
    }

    private function showMenu() :void
    {
        $this->menu
             ->build()
             ->open();
    }

    private function stylishMenu() :self
    {
        $this->menu
             ->addLineBreak(config('artisanui.ui.line_break', '='))
             ->setCheckboxStyle($this->getCheckboxStyle())
             ->setSelectableStyle($this->getSelectableStyle())
             ->setBorder(
                config('artisanui.ui.border_horizontal', 2), 
                config('artisanui.ui.border_vertical', 4),
                config('artisanui.ui.border_color', 'yellow'),
             )
             ->setPadding(
                config('artisanui.ui.padding_horizontal', 2), 
                config('artisanui.ui.padding_vertical', 4),
             )
             ->setForegroundColour(
                config('artisanui.ui.text_color', 'white')
             )
             ->setBackgroundColour(
                config('artisanui.ui.background_color', 'blue')
             )
             ->setMarginAuto()
             ->setTitle($this->title);

        return $this;
    }

    // callback for commands without options
    private function getExecutionCallback($command) :Closure
    {
        $input = [
            'command' => $command->getName()
        ];

        $closure = function (CliMenu $menu) use ($input) {
            $this->artisan->handle(
                new ArrayInput($input), 
                new ConsoleOutput()
            );
        };

        return $closure->bindTo($this);
    }

    // callback for commands with options
    private function getCommandCallback($command) :Closure
    {
        [$name, $command] = [$command->getName(), $command];

        $closure = function (CliMenuBuilder $builder) use ($name, $command) {
            $arguments = $command->getDefinition()->getArguments();

            $required = [];
            $optional = false;

            foreach ($arguments as $argument) {
                if ($argument->isRequired()) {
                    $required[] = $argument->getName();
                } else {
                    $optional = true;
                }
            }

            $required = !empty($required) ? 
                            "The next options are required: " . implode(', ', $required) : 
                            false;

            $builder->setTitle("artisan {$name}");

            if ($required) {
                $builder->addStaticItem($required);
            }

            if ($optional) {
                $builder->addStaticItem('Optional arguments (press Enter to select)')
                        ->addLineBreak();
            }

            // object for collecting selected options
            $input = collect([]);

            // callback for optional checkboxes
            $checkboxCallback = function (CliMenu $menu) use (&$input) {
                [$name] = explode(self::DELIMITER, $menu->getSelectedItem()->getText());

                $input->has($name) ? $input->pull($name) : $input->put($name, true);
            };

            foreach ($arguments as $argument) {
                if ($argument->isRequired()) {
                    continue;
                }

                $builder->addCheckboxItem(
                    $argument->getName() . self::DELIMITER . $argument->getDescription(),
                    $checkboxCallback
                );
            }

            $executeClosure = function (CliMenu $menu) use ($input, $arguments, $name) {
                $options = [];

                foreach ($arguments as $argument) {
                    if (!$argument->isRequired() && !$input->has($argument->getName())) {
                        continue;
                    }

                    $description = $argument->getDescription() . " (--" . $argument->getName() . ")";

                    $answer = $menu->askText()
                                   ->setPromptText("Enter $description")
                                   ->ask();

                    $options[$argument->getName()] = $answer->fetch();
                }

                $input = [
                    "" => "",
                    "command" => $name
                ];

                $this->artisan->handle(
                    new ArgvInput($input + $options), 
                    new ConsoleOutput()
                );
            };

            $builder->addLineBreak('-')
                    ->addItem(
                        "Execute command. " . $required ?: "", 
                        $executeClosure->bindTo($this)
                    );
        };

        return $closure->bindTo($this);
    }

    private function buildFavouriteSection()
    {
        $this->menu
             ->addStaticItem('Favourite:')
             ->addLineBreak(" ");

        foreach ($this->all as $name => $command) {
            if (!in_array($name, $this->favourite)) {
                continue;
            }   

            if (!$command->withArguments) {
                $this->menu->addItem($name, $this->getExecutionCallback($command));
            } else {
                $this->menu->addSubMenu($name, $this->getCommandCallback($command));
            }
        }
    }

    private function getItemsGroupClosure(array $list, string $group) :Closure
    {
        $closure = function (CliMenuBuilder $builder) use ($list, $group) {
            $builder->setTitle("{$this->title} > {$group}");

            foreach ($list as $cmd) {
                if (!$cmd->withArguments) {
                    $builder->addItem(
                        $cmd->getName(), 
                        $this->getExecutionCallback($cmd)
                    );
                } else {
                    $builder->addSubMenu(
                        $cmd->getName(), 
                        $this->getCommandCallback($cmd)
                    );
                }
            }

            $builder = $builder->addLineBreak('-');
        };

        return $closure->bindTo($this);
    }

    private function buildMenu() :self
    {
        $this->menu = new CliMenuBuilder();

        $this->menu
             ->addStaticItem(
                 'You can add your favourite commands in config/artisanui.php to get fast access to them!'
             );

        if (!empty($this->favourite)) {
            $this->buildFavouriteSection();
        }

        $this->menu
             ->addLineBreak(" ")
             ->addLineBreak(config('artisanui.ui.line_break', '='));

        foreach ($this->commands as $group => $list) {
            $this->menu->addSubMenu(
                $group, 
                $this->getItemsGroupClosure($list, $group)->bindTo($this)
            );
        }

        return $this;
    }

    private function loadTitle() :self
    {
        $this->title = config('artisanui.title', "");

        return $this;
    }

    private function loadFavouriteCommands() :self
    {
        $this->favourite = config('artisanui.favourite', []);

        return $this;
    }

    private function getAllArtisanCommands() :self
    {
        $all = $this->artisan->all();

        $this->all = [];

        foreach ($all as $command) {
            $command->withArguments = count($command->getDefinition()->getArguments()) !== 0;

            $this->all[$command->getName()] = $command;
        }

        $groups = [];
        $common = [];

        foreach ($this->all as $name => $command) {
            $cmd = explode(':', $name);

            $chunksNumber = count($cmd);

            if ($chunksNumber === 1 || $chunksNumber > 2) {
                $common[] = $command;

                continue;
            }

            if ($chunksNumber === 2) {
                if (!array_key_exists($cmd[0], $groups)) {
                    $groups[$cmd[0]] = [];
                }

                $groups[$cmd[0]][] = $command;
            }
        }

        ksort($groups);

        foreach ($groups as &$list) {
            usort($list, function ($first, $second) {
                return strcmp($first->getName(), $second->getName());
            });
        }

        $this->commands = ['common' => $common] + $groups;

        return $this;
    }

    private function getCheckboxStyle() :CheckboxStyle
    {
        $checkboxStyle = new CheckboxStyle();

        $checkboxStyle->setCheckedMarker(
            config('artisanui.ui.checked_marker', '[X]')
        );

        $checkboxStyle->setUncheckedMarker(
            config('artisanui.ui.unchecked_marker', '[ ]')
        );

        return $checkboxStyle;
    }

    private function getSelectableStyle() :SelectableStyle
    {
        $selectableStyle = new SelectableStyle();

        $selectableStyle->setSelectedMarker(
            config('artisanui.ui.selected_marker', ' > ')
        );
        
        $selectableStyle->setUnselectedMarker(
            config('artisanui.ui.unselected_marker', ' o ')
        );

        return $selectableStyle;
    }
}
