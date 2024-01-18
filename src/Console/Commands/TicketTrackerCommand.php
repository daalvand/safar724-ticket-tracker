<?php

namespace Daalvand\Safar724AutoTrack\Console\Commands;

use Carbon\Carbon;
use Daalvand\Safar724AutoTrack\Safar724;
use Daalvand\Safar724AutoTrack\TicketChecker;
use Daalvand\Safar724AutoTrack\ValueObjects\TicketCheckerValueObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

class TicketTrackerCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('ticket-tracker')
            ->setDescription('Track ticket information')
            ->setHelp('This command tracks ticket information for a specific route and date range.')
            ->addOption('source', mode: InputOption::VALUE_REQUIRED, description: 'Source location')
            ->addOption('destination', mode: InputOption::VALUE_REQUIRED, description: 'Destination location')
            ->addOption('from-date', mode: InputOption::VALUE_REQUIRED, description: 'Start date for tracking', default: Carbon::today()->toString())
            ->addOption('to-date', mode: InputOption::VALUE_REQUIRED, description: 'End date for tracking', default: Carbon::tomorrow()->toString())
            ->addOption('from-time', mode: InputOption::VALUE_REQUIRED, description: 'Start time for tracking', default: '00:00')
            ->addOption('to-time', mode: InputOption::VALUE_REQUIRED, description: 'End time for tracking', default: '23:59')
            ->addOption('telegram-chat-id', mode: InputOption::VALUE_OPTIONAL, description: 'Telegram chat ID for notifications', default: (int)$_ENV['TELEGRAM_CHAT_ID'])
            ->addOption('check-duration', mode: InputOption::VALUE_OPTIONAL, description: 'Check duration in seconds (optional)')
            ->addOption('check-times', mode: InputOption::VALUE_OPTIONAL, description: 'Number of times to check (optional)');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $validationErrors = $this->validateInput($input);
            if (!empty($validationErrors)) {
                foreach ($validationErrors as $error) {
                    $output->writeln($error);
                }
                return Command::FAILURE;
            }

            // Continue with the execution logic
            $source = $input->getOption('source');
            $destination = $input->getOption('destination');
            $from = Carbon::parse($input->getOption('from-date'));
            $to = Carbon::parse($input->getOption('to-date'));
            $fromTime = $input->getOption('from-time');
            $toTime = $input->getOption('to-time');
            $chatId = $input->getOption('telegram-chat-id');
            $checkDuration = $input->getOption('check-duration');
            $checkTimes = $input->getOption('check-times');

            $valueObject = new TicketCheckerValueObject();
            $valueObject->setFrom($from);
            $valueObject->setTo($to);
            $valueObject->setFromTime($fromTime);
            $valueObject->setToTime($toTime);
            $valueObject->setSource($source);
            $valueObject->setDestination($destination);
            $valueObject->setChatId($chatId);

            if ($checkDuration) {
                $valueObject->setCheckDuration($checkDuration);
            }

            if ($checkTimes) {
                $valueObject->setCheckTimes($checkTimes);
            }

            $checker = new TicketChecker();
            $checker->track($valueObject);

            return Command::SUCCESS;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));
            $output->writeln(sprintf('<error>%s</error>', $throwable->getTraceAsString()));
            return Command::FAILURE;
        }
    }

    private function validateInput(InputInterface $input): array
    {
        $validator = Validation::createValidator();
        $errors = [];
        $cities = array_column((new Safar724())->getCities(), 'Name');

        // Define validation rules for each input argument
        $validationRules = [
            'source' => [
                new Assert\NotBlank(),
                new Assert\Choice(['choices' => $cities]),
            ],
            'destination' => [
                new Assert\NotBlank(),
                new Assert\Choice(['choices' => $cities]),
            ],
            'from-date' => [
                new Assert\NotBlank(),
                new Assert\Date(),
            ],
            'to-date' => [
                new Assert\NotBlank(),
                new Assert\Date(),
                new Assert\LessThanOrEqual(['propertyPath' => 'from-date', 'message' => 'To date must be less than or equal to from date.']),
            ],
            'telegram-chat-id' => [
                new Assert\Type(['type' => 'integer', 'message' => 'Telegram chat ID must be an integer.']),
                new Assert\GreaterThan(['value' => 0, 'message' => 'Telegram chat ID must be greater than 0.']),
            ],
            'check-duration' => [
                new Assert\Optional(),
                new Assert\Type(['type' => 'integer', 'message' => 'Check duration must be an integer.']),
            ],
            'check-times' => [
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
