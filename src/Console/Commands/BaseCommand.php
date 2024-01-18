<?php

namespace Daalvand\Safar724AutoTrack\Console\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Validator\Validation;

abstract class BaseCommand extends Command
{

    abstract protected function rules(): array;

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        parent::initialize($input, $output);
        $validator = Validation::createValidator();
        $errors    = [];
        $io        = new SymfonyStyle($input, $output);

        foreach ($this->rules() as $argument => $rules) {
            $value      = $input->getOption($argument);
            $violations = $validator->validate($value, $rules);

            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $errors[] = ucfirst($argument) . ': ' . $violation->getMessage();
                }
            }
        }

        if (!empty($errors)) {
            $io->title('Validation Errors:');
            foreach ($errors as $error) {
                $io->error($error);
            }
            exit(Command::FAILURE);
        }
    }
}
