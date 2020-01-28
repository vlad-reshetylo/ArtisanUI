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

class ArtisanUI
{
    private $artisan;
    private $commands;
    private $favourite;
    private $title;
    private $all;

    public function __construct(Kernel $kernel)
    {
        $this->artisan = $kernel;
    }

    public static function getItem(string $name, $command) 
    {
        return (object) [
            'name' => $name,
            'command' => $command
        ];
    }

    public function launch()
    {
        $this->commands = $this->getAllArtisanCommands();
        $this->favourite = $this->loadFavouriteCommands();

        $this->title = config('artisanui.title');

        $this->buildMenu()
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
             ->setTitle($this->title)
             ->build()
             ->open();
    }

    // callback for commands without options
    private function getExecutionCallback()
    {
        $args = func_get_args();

        return function (CliMenu $menu) use ($args) {
            [$cmd, $self] = $args;

            $input = new ArrayInput([
                'command' => $cmd->name
            ]);

            $self->artisan->handle($input, new ConsoleOutput());
        };
    }

    // callback for commands with options
    private function getCommandCallback()
    {
        $args = func_get_args();

        $self = $this;

        return function (CliMenuBuilder $builder) use ($args, $self) {
            [$cmd, $self] = $args;

            $arguments = $cmd->command
                             ->getDefinition()
                             ->getArguments();

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

            $builder->setTitle("artisan {$cmd->name}");

            if ($required) {
                $builder->addStaticItem($required);
            }

            if ($optional) {
                $builder->addStaticItem('Optional arguments (press Enter to select)')
                        ->addLineBreak();
            }

            $input = collect([]);

            $delimiter = "          |";

            // callback for optional checkboxes
            $checkboxCallback = function (CliMenu $menu) use (&$input, $delimiter) {
                [$name] = explode($delimiter, $menu->getSelectedItem()->getText());

                $input->has($name) ? $input->pull($name) : $input->put($name, true);
            };

            foreach ($arguments as $argument) {
                if ($argument->isRequired()) {
                    continue;
                }

                $builder->addCheckboxItem(
                    $argument->getName() . $delimiter . $argument->getDescription(),
                    $checkboxCallback
                );
            }

            $builder->addLineBreak('-')
                    ->addItem(
                        "Execute command. " . ($required ? $required : "") , 
                        function (CliMenu $menu) use ($input, $arguments, $self, $cmd) {
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

                            $input = new ArgvInput(array_merge(
                                [
                                    "" => "",
                                    "command" => $cmd->name
                                ],
                                $options
                            ));

                            $self->artisan->handle($input, new ConsoleOutput());
                        }
                    );
        };
    }

    private function buildMenu()
    {
        $menu = new CliMenuBuilder();

        // yeah, right like in JavaScript! :)
        $self = $this;

        $getItemCallback = function ($list, $group) use ($self) {
            return function (CliMenuBuilder $builder) use ($list, $group, $self) {
                $builder->setTitle("{$self->title} > {$group}");

                foreach ($list as $cmd) {
                    $argsNumber = count($cmd->command->getDefinition()->getArguments());

                    if ($argsNumber === 0) {
                        $builder->addItem($cmd->name, $self->getExecutionCallback($cmd, $self));
                    } else {
                        $builder->addSubMenu($cmd->name, $self->getCommandCallback($cmd, $self));
                    }
                }

                $builder = $builder->addLineBreak('-');
            };
        };

        if (empty($this->favourite)) {
            $menu->addStaticItem(
                'You can add your favourite commands in config/artisanui.php to get fast access to them!'
            );
        } else {
            $menu->addStaticItem('Favourite:')
                 ->addLineBreak(" ");

            foreach ($this->all as $name => $command) {
                if (!in_array($name, $this->favourite)) {
                    continue;
                }   

                $argsNumber = count($command->getDefinition()->getArguments());

                $item = self::getItem($name, $command);

                if ($argsNumber === 0) {
                    $menu->addItem($name, $this->getExecutionCallback($item, $this));
                } else {
                    $menu->addSubMenu($name, $this->getCommandCallback($item, $this));
                }
            }
        }

        $menu->addLineBreak(" ")
             ->addLineBreak(config('artisanui.ui.line_break', '='));

        foreach ($this->commands as $group => $list) {
            $menu->addSubMenu($group, $getItemCallback($list, $group));
        }

        return $menu;
    }

    private function loadFavouriteCommands()
    {
        return config('artisanui.favourite', []);
    }

    private function getAllArtisanCommands()
    {
        $this->all = $this->artisan->all();

        $groups = [];
        $common = [];

        foreach ($this->all as $name => $command) {
            $cmd = explode(':', $name);

            $chunksNumber = count($cmd);

            $item = self::getItem($name, $command);

            if ($chunksNumber === 1 || $chunksNumber > 2) {
                $common[] = $item;

                continue;
            }

            if ($chunksNumber === 2) {
                if (!array_key_exists($cmd[0], $groups)) {
                    $groups[$cmd[0]] = [];
                }

                $groups[$cmd[0]][] = $item;
            }
        }

        ksort($groups);

        foreach ($groups as &$list) {
            usort($list, function ($first, $second) {
                return strcmp($first->name, $second->name);
            });
        }

        return array_merge([
            'common' => $common
        ], $groups);
    }

    private function getCheckboxStyle()
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

    private function getSelectableStyle()
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
