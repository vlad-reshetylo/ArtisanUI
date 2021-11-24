<?php

namespace VladReshet\ArtisanUi;

use Illuminate\Contracts\Console\Kernel;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;
use PhpSchool\CliMenu\Style\CheckboxStyle;
use PhpSchool\CliMenu\Style\SelectableStyle;
use VladReshet\ArtisanUi\CommandsSet;
use Closure;

class ArtisanUi
{
    const DELIMITER = "   | ";

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
        $commands = $this->getAllArtisanCommands();

        $set = (new CommandsSet())
                ->setAll($commands['all'])
                ->setGrouped($commands['grouped'])
                ->setFavourite(config('artisanui.favourite', []));

        $menu = $this->describeMenu($set);
        $menu = $this->stylishMenu($menu);

        $menu->build()
             ->open();
    }

    private function stylishMenu(CliMenuBuilder $menu) :CliMenuBuilder
    {
        $menu->addLineBreak(config('artisanui.ui.line_break', '='))
             ->setCheckboxStyle($this->getCheckboxStyle())
             ->setSelectableStyle($this->getSelectableStyle())
             ->setBorder(
                config('artisanui.ui.border_horizontal', 1), 
                config('artisanui.ui.border_vertical', 2),
                config('artisanui.ui.border_color', 'yellow'),
             )
             ->setPadding(
                config('artisanui.ui.padding_horizontal', 1), 
                config('artisanui.ui.padding_vertical', 2),
             )
             ->setForegroundColour(
                config('artisanui.ui.text_color', 'white')
             )
             ->setBackgroundColour(
                config('artisanui.ui.background_color', 'blue')
             )
             ->setMarginAuto()
             ->setTitle(config('artisanui.title', ""));

        return $menu;
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
    private function getOptionalCallback($command) :Closure
    {
        [$name, $command] = [$command->getName(), $command->getDefinition()];

        $closure = function (CliMenuBuilder $builder) use ($name, $command) {
            $arguments = $command->getArguments();
            $options = $command->getOptions();

            $required = [];
            $optional = count($options) !== 0;

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
                $builder->addLineBreak()
                        ->addStaticItem('Optional arguments (press Enter to select):')
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

            foreach ($options as $option) {
                $builder->addCheckboxItem(
                    $option->getName() . self::DELIMITER . $option->getDescription(),
                    $checkboxCallback
                );
            }

            $executeClosure = function (CliMenu $menu) use ($input, $arguments, $options, $name) {
                $parameters = [];

                foreach ($arguments as $argument) {
                    if (!$argument->isRequired() && !$input->has($argument->getName())) {
                        continue;
                    }

                    $description = $argument->getDescription() . " (--" . $argument->getName() . ")";

                    $answer = $menu->askText()
                                   ->setPromptText("Enter $description")
                                   ->ask();

                    $parameters[$argument->getName()] = $answer->fetch();
                }

                foreach ($options as $option) {
                    if (!$input->has($option->getName())) {
                        continue;
                    }

                    $description = $option->getDescription() . " (--" . $option->getName() . ")";

                    if ($option->acceptValue()) {
                        $answer = $menu->askText()
                                       ->setPromptText("Enter $description")
                                       ->ask();

                        $value = "--" . $option->getName() . "=" . $answer->fetch();
                    } else {
                        $value = "--" . $option->getName();
                    }

                    $parameters[$option->getName()] = $value;
                }

                $input = [
                    "" => "",
                    "command" => $name
                ];

                $this->artisan->handle(
                    new ArgvInput($input + $parameters), 
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

    private function buildFavouriteSection(
        CliMenuBuilder $menu, 
        CommandsSet $set
    ) :CliMenuBuilder 
    {
        $menu->addStaticItem('Favourite:')
             ->addLineBreak(" ");

        $all = $set->getAll();

        foreach ($all as $name => $command) {
            if (!$set->isFavourite($name)) {
                continue;
            }   

            if (!$command->withArguments) {
                $menu->addItem($name, $this->getExecutionCallback($command));
            } else {
                $menu->addSubMenu($name, $this->getOptionalCallback($command));
            }
        }

        return $menu;
    }

    private function getItemsGroupClosure(array $list, string $group) :Closure
    {
        $title = config('artisanui.title', "");

        $closure = function (CliMenuBuilder $builder) use ($list, $group, $title) {
            $builder->setTitle("{$title} > {$group}");

            foreach ($list as $cmd) {
                if (!$cmd->withArguments) {
                    $builder->addItem(
                        $cmd->getName(), 
                        $this->getExecutionCallback($cmd)
                    );
                } else {
                    $builder->addSubMenu(
                        $cmd->getName(), 
                        $this->getOptionalCallback($cmd)
                    );
                }
            }

            $builder = $builder->addLineBreak('-');
        };

        return $closure->bindTo($this);
    }

    private function describeMenu(CommandsSet $set) :CliMenuBuilder
    {
        $menu = new CliMenuBuilder();

        $menu->addStaticItem(
            'You can add your favourite commands in config/artisanui.php to get fast access to them!'
        );

        if ($set->hasFavourite()) {
            $menu = $this->buildFavouriteSection($menu, $set);
        }

        $menu->addLineBreak(" ")
             ->addLineBreak(config('artisanui.ui.line_break', '='));

        $groups = $set->getGrouped();

        foreach ($groups as $group => $list) {
            $menu->addSubMenu(
                $group, 
                $this->getItemsGroupClosure($list, $group)->bindTo($this)
            );
        }

        return $menu;
    }

    private function getAllArtisanCommands() :array
    {
        $all = $this->artisan->all();

        $associated = [];

        foreach ($all as $command) {
            $options = $command->getDefinition()->getOptions();
            $arguments = $command->getDefinition()->getArguments();

            $command->withArguments = count($options) !== 0 || count($arguments) !== 0;

            $associated[$command->getName()] = $command;
        }

        $groups = [];
        $common = [];

        foreach ($associated as $name => $command) {
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

        return [
            'all' => $associated,
            'grouped' => ['common' => $common] + $groups
        ];
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
