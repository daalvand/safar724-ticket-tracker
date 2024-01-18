<?php

namespace Daalvand\Safar724AutoTrack\Console\Commands;

use Carbon\Carbon;
use Daalvand\Safar724AutoTrack\Safar724;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class ReserveTicketCommand extends Command
{
    protected function configure(): void
    {
        $this->setName('reserve-ticket')
            ->setDescription('Reserve a ticket for a specific route and date')
            ->setHelp('This command reserves a ticket and retries if necessary.')
            ->addOption('max_try', mode: InputOption::VALUE_OPTIONAL, description: 'Maximum number of reservation attempts', default: 1000)
            ->addOption('interval', mode: InputOption::VALUE_OPTIONAL, description: 'Interval between reservation attempts in seconds', default: 600);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $payload = [
            'paymentDetails' => [
                'AccountId' => 1378,
                'ServiceID' => 28743786,
                'SelectedSeats' => 14,
                'OriginName' => 'borujerd',
                'DestinationName' => 'tehran',
                'DestinationCode' => 11320000,
                'Discount' => 0,
                'ReturnUrl' => '/checkout/81360000/borujerd/11320000/tehran/1402-10-29/28743786',
            ],
            'passenger' => [
                'name' => 'علی',
                'lastname' => 'حجتی',
                'mobile' => '09123456789',
                'gender' => 0,
                'code' => '0592231151',
            ],
            'headers' => [
                'RequestOrigin' => 'WebSite',
                'Token' => '',
                'Version' => [
                    'Name' => '',
                    'Code' => '',
                ],
            ],
        ];

        try {
            $retried = 0;
            $maxTry = (int)$input->getOption('max_try');
            $interval = (int)$input->getOption('interval');
            $client = new Safar724();

            while ($retried <= $maxTry) {
                $response = $client->request('checkout/payment', 'POST', [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                    ],
                    'form_params' => $payload,
                ]);

                $body = json_decode($response->getBody()->getContents(), true);
                $status = $body['Status'] ?? null;
                $output->writeln("http status code:: " . $response->getStatusCode());
                $output->writeln("content:: " . json_encode($body));
                $output->writeln("status code:: " . $status);
                $output->writeln("reserve time:: " .Carbon::now('Asia/Tehran')->toDateTimeString());
                $output->writeln("====================================================");
                sleep($interval);
                $retried++;
            }

            return Command::SUCCESS;
        } catch (Throwable $throwable) {
            $output->writeln(sprintf('<error>%s</error>', $throwable->getMessage()));
            $output->writeln(sprintf('<error>%s</error>', $throwable->getTraceAsString()));
            return Command::FAILURE;
        }
    }
}
