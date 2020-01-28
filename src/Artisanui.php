<?php

namespace VladReshet\Artisanui;

use Illuminate\Contracts\Console\Kernel;
use PhpSchool\CliMenu\CliMenu;
use PhpSchool\CliMenu\Builder\CliMenuBuilder;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Input\InputArgument;

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
                     ->addLineBreak('-')
                     ->setBorder(1, 2, 'yellow')
                     ->setPadding(2, 4)
                     ->setMarginAuto()
                     ->setTitle($this->title);

        $menu->build()
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
                        function (CliMenu $menu) use ($input, $arguments, $self) {
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

                            $input = new ArgvInput(array_merge([
                                "_" => "",
                                $options
                            ]));

                            $self->artisan->handle($input, new ConsoleOutput());
                        }
                    );
        };
    }

    private function buildMenu()
    {
        $menu = new CliMenuBuilder();

        $self = $this;

        foreach ($this->commands as $group => $list) {
            $menu->addSubMenu($group, function (CliMenuBuilder $builder) use ($list, $group, $self) {
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
            });
        }

        return $menu;
    }

    private function getAllArtisanCommands()
    {
        $all = $this->artisan->all();

        $groups = [];
        $common = [];

        foreach ($all as $name => $command) {
            $cmd = explode(':', $name);

            $chunksNumber = count($cmd);

            $item = (object) [
                'name' => $name,
                'command' => $command
            ];

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
}
