<?php

namespace Daalvand\Safar724AutoTrack\Console\Commands;

use Daalvand\Safar724AutoTrack\Safar724;
use Daalvand\Safar724AutoTrack\TicketChecker;
use Daalvand\Safar724AutoTrack\ValueObjects\TicketCheckerValueObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;

class TicketTrackerCommand extends Command
{
    protected function configure(): void {
        $this->setName('ticket-tracker')
             ->setDescription('Track ticket information')
             ->setHelp('This command tracks ticket information for a specific route and date range.')
             ->addArgument('source', InputArgument::REQUIRED, 'Source location')
             ->addArgument('destination', InputArgument::REQUIRED, 'Destination location')
             ->addArgument('from-date', InputArgument::REQUIRED, 'Start date for tracking')
             ->addArgument('to-date', InputArgument::REQUIRED, 'End date for tracking')
             ->addOption('telegram-chat-id', null, InputOption::VALUE_OPTIONAL, 'Telegram chat ID for notifications', (int)$_ENV['TELEGRAM_CHAT_ID'])
             ->addOption('check-duration', null, InputOption::VALUE_OPTIONAL, 'Check duration in seconds (optional)')
             ->addOption('check-times', null, InputOption::VALUE_OPTIONAL, 'Number of times to check (optional)');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int {
        $validationErrors = $this->validateInput($input);

        if (!empty($validationErrors)) {
            foreach ($validationErrors as $error) {
                $output->writeln(sprintf('<error>%s</error>', $error));
            }
            return Command::FAILURE;
        }

        // Continue with the execution logic
        $source        = $input->getArgument('source');
        $destination   = $input->getArgument('destination');
        $from          = $input->getArgument('from-date');
        $to            = $input->getArgument('to-date');
        $chatId        = $input->getOption('telegram-chat-id');
        $checkDuration = $input->getOption('check-duration');
        $checkTimes    = $input->getOption('check-times');

        $valueObject = new TicketCheckerValueObject($from, $to, $source, $destination, $chatId);

        if ($checkDuration) {
            $valueObject->setCheckDuration($checkDuration);
        }

        if ($checkTimes) {
            $valueObject->setCheckTimes($checkTimes);
        }

        $checker = new TicketChecker();
        $checker->track($valueObject);

        return Command::SUCCESS;
    }

    private function validateInput(InputInterface $input): array {
        $validator = Validation::createValidator();
        $errors    = [];
        $cities    = array_column((new Safar724())->getCities(), 'Name');

        // Define validation rules for each input argument
        $validationRules = [
            'source'           => [
                new Assert\NotBlank(),
                new Assert\Choice(['choices' => $cities]),
            ],
            'destination'      => [
                new Assert\NotBlank(),
                new Assert\Choice(['choices' => $cities]),
            ],
            'from-date'        => [
                new Assert\NotBlank(),
                new Assert\Date(),
            ],
            'to-date'          => [
                new Assert\NotBlank(),
                new Assert\Date(),
                new Assert\LessThanOrEqual(['propertyPath' => 'from-date', 'message' => 'To date must be less than or equal to from date.']),
            ],
            'telegram-chat-id' => [
                new Assert\Type(['type' => 'integer', 'message' => 'Telegram chat ID must be an integer.']),
                new Assert\GreaterThan(['value' => 0, 'message' => 'Telegram chat ID must be greater than 0.']),
            ],
            'check-duration'   => [
                new Assert\Optional(),
                new Assert\Type(['type' => 'integer', 'message' => 'Check duration must be an integer.']),
            ],
            'check-times'      => [
                new Assert\Optional(),
                new Assert\Type(['type' => 'integer', 'message' => 'Check times must be an integer.']),
            ],
        ];

        foreach ($validationRules as $argument => $rules) {
            if ($input->hasArgument($argument)) {
                $value = $input->getArgument($argument);
            } else {
                $value = $input->getOption($argument);
            }

            $violations = $validator->validate($value, $rules);

            if (count($violations) > 0) {
                foreach ($violations as $violation) {
                    $errors[] = sprintf('<error>%s: %s</error>', ucfirst($argument), $violation->getMessage());
                }
            }
        }

        return $errors;
    }

}
