<?php

namespace Daalvand\Safar724AutoTrack\Console\Commands;

use Carbon\Carbon;
use Daalvand\Safar724AutoTrack\Exceptions\RequestException;
use Daalvand\Safar724AutoTrack\Safar724;
use Daalvand\Safar724AutoTrack\TicketChecker;
use Daalvand\Safar724AutoTrack\ValueObjects\TicketCheckerValueObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints as Assert;

class TicketTrackerCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

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
        $source        = $input->getOption('source');
        $destination   = $input->getOption('destination');
        $from          = Carbon::parse($input->getOption('from-date'));
        $to            = Carbon::parse($input->getOption('to-date'));
        $fromTime      = $input->getOption('from-time');
        $toTime        = $input->getOption('to-time');
        $chatId        = $input->getOption('telegram-chat-id');
        $checkDuration = $input->getOption('check-duration');
        $checkTimes    = $input->getOption('check-times');

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

        try {
            $checker = new TicketChecker();
            $checker->track($valueObject);
        } catch (RequestException) {
            return $this->execute($input, $output);
        }

        return Command::SUCCESS;
    }

    protected function rules(): array
    {
        $cities = array_column((new Safar724())->getCities(), 'Name');
        return [
            'source'           => [
                new Assert\NotBlank(),
                new Assert\Choice(choices: $cities),
            ],
            'destination'      => [
                new Assert\NotBlank(),
                new Assert\Choice(choices: $cities),
            ],
            'from-date'        => [
                new Assert\NotBlank(),
                new Assert\Date(),
            ],
            'to-date'          => [
                new Assert\NotBlank(),
                new Assert\Date(),
                new Assert\LessThanOrEqual(propertyPath: 'from-date', message: 'To date must be less than or equal to from date.'),
            ],
            'telegram-chat-id' => [
                new Assert\Type(type: 'numeric', message: 'Telegram chat ID must be an integer.'),
                new Assert\GreaterThan(value: 0, message: 'Telegram chat ID must be greater than 0.'),
            ],
            'check-duration'   => [
                new Assert\Optional(),
                new Assert\Type(type: 'numeric', message: 'Check duration must be an integer.'),
            ],
            'check-times'      => [
                new Assert\Optional(),
                new Assert\Type(type: 'numeric', message: 'Check times must be an integer.'),
            ],
        ];
    }
}