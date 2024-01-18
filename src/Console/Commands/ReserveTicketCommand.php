<?php

namespace Daalvand\Safar724AutoTrack\Console\Commands;

use Carbon\Carbon;
use Daalvand\Safar724AutoTrack\Safar724;
use Morilog\Jalali\Jalalian;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

class ReserveTicketCommand extends BaseCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this->setName('reserve-ticket')
            ->setDescription('Reserve a ticket for a specific route and date')
            ->setHelp('This command reserves a ticket and retries if necessary.')
            ->addOption('max_try', mode: InputOption::VALUE_REQUIRED, description: 'Maximum number of reservation attempts', default: 1000)
            ->addOption('interval', mode: InputOption::VALUE_REQUIRED, description: 'Interval between reservation attempts in seconds', default: 600)
            ->addOption('passenger-name', mode: InputOption::VALUE_REQUIRED, description: 'Passenger name', default: 'John')
            ->addOption('passenger-lastname', mode: InputOption::VALUE_REQUIRED, description: 'Passenger last name', default: 'Doe')
            ->addOption('passenger-mobile', mode: InputOption::VALUE_REQUIRED, description: 'Passenger mobile', default: '09123456789')
            ->addOption('passenger-gender', mode: InputOption::VALUE_REQUIRED, description: 'Passenger gender', default: 0)
            ->addOption('passenger-code', mode: InputOption::VALUE_REQUIRED, description: 'Passenger code', default: '4653119597')
            ->addOption('seat-number', mode: InputOption::VALUE_REQUIRED, description: 'Seat number')
            ->addOption('service-id', mode: InputOption::VALUE_REQUIRED, description: 'Service ID')
            ->addOption('destination', mode: InputOption::VALUE_REQUIRED, description: 'Destination location');

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $destination       = $input->getOption('destination');
        $passengerName     = $input->getOption('passenger-name');
        $passengerLastname = $input->getOption('passenger-lastname');
        $passengerMobile   = $input->getOption('passenger-mobile');
        $passengerGender   = $input->getOption('passenger-gender');
        $passengerCode     = $input->getOption('passenger-code');
        $seatNumber        = $input->getOption('seat-number');
        $serviceId         = $input->getOption('service-id');


        $destinationId = (new Safar724())->getId($destination);
        $serviceDetail = (new Safar724())->setServiceDetail($serviceId, $destinationId);
        $sourceId      = $serviceDetail['OriginCode'];
        $source        = $serviceDetail['OriginName'];
        $date          = Jalalian::fromFormat('Y/m/d', $serviceDetail['Date'])->format('Y-m-d');

        $payload = [
            'paymentDetails' => [
                'AccountId'       => random_int(1000, 10000),
                'ServiceID'       => $serviceId,
                'SelectedSeats'   => $seatNumber,
                'OriginName'      => $source,
                'DestinationName' => $destination,
                'DestinationCode' => $destinationId,
                'Discount'        => 0,
                'ReturnUrl'       => "/checkout/$sourceId/$source/$destinationId/$destination/$date/$serviceId",
            ],
            'passenger'      => [
                'name'     => $passengerName,
                'lastname' => $passengerLastname,
                'mobile'   => $passengerMobile,
                'gender'   => $passengerGender,
                'code'     => $passengerCode,
            ],
            'headers'        => [
                'RequestOrigin' => 'WebSite',
                'Token'         => '',
                'Version'       => [
                    'Name' => '',
                    'Code' => '',
                ],
            ],
        ];

        try {
            $retried  = 0;
            $maxTry   = (int)$input->getOption('max_try');
            $interval = (int)$input->getOption('interval');
            $client   = new Safar724();

            while ($retried <= $maxTry) {
                $response = $client->request('checkout/payment', 'POST', [
                    'headers'     => [
                        'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
                    ],
                    'form_params' => $payload,
                ]);

                $body   = json_decode($response->getBody()->getContents(), true);
                $status = $body['Status'] ?? null;
                $output->writeln("http status code:: " . $response->getStatusCode());
                $output->writeln("content:: " . json_encode($body));
                $output->writeln("status code:: " . $status);
                $output->writeln("reserve time:: " . Carbon::now('Asia/Tehran')->toDateTimeString());
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

    protected function rules(): array
    {
        $cities = array_column((new Safar724())->getCities(), 'Name');

        return [
            'destination'        => [
                new Assert\NotBlank(),
                new Assert\Choice(choices: $cities),
            ],
            'passenger-name'     => [
                new Assert\NotBlank(),
                new Assert\Type(type: 'string', message: 'Passenger name must be a string.'),
            ],
            'passenger-lastname' => [
                new Assert\NotBlank(),
                new Assert\Type(type: 'string', message: 'Passenger last name must be a string.'),
            ],
            'passenger-mobile'   => [
                new Assert\NotBlank(),
                new Assert\Type(type: 'string', message: 'Passenger mobile must be a string.'),
                new Assert\Regex(pattern: '/^09\d{9}$/'),
            ],
            'passenger-gender'   => [
                new Assert\Type(type: 'numeric', message: 'Passenger gender must be a number.'),
                new Assert\Range(notInRangeMessage: 'Passenger gender must be either 0 or 1.', min: 0, max: 1),
            ],
            'passenger-code'     => [
                new Assert\NotBlank(),
                new Assert\Type(type: 'string', message: 'Passenger code must be a string.'),
            ],
            'seat-number'        => [
                new Assert\NotBlank(),
                new Assert\Type(type: 'numeric', message: 'Seat number must be a number.'),
            ],
            'service-id'         => [
                new Assert\NotBlank(),
                new Assert\Type(type: 'numeric', message: 'Service ID must be a number.'),
            ],
        ];
    }
}
