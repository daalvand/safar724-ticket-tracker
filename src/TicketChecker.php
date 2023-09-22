<?php

namespace Daalvand\Safar724AutoTrack;

use Daalvand\Safar724AutoTrack\ValueObjects\TicketCheckerValueObject;
use Morilog\Jalali\Jalalian;

class TicketChecker
{
    protected Safar724 $client;
    protected Telegram $notificationService;

    public function __construct() {
        $this->client              = new Safar724();
        $this->notificationService = new Telegram();
    }

    public function track(TicketCheckerValueObject $valueObject): void {
        $fromDate = Jalalian::fromFormat('Y-m-d', $valueObject->getFrom());
        $toDate   = Jalalian::fromFormat('Y-m-d', $valueObject->getTo());

        $checked = 0;
        while ($checked < $valueObject->getCheckTimes()) {
            $date = $fromDate;
            while ($date->lessThanOrEqualsTo($toDate)) {
                $data = $this->checkTicket($date, $valueObject);
                foreach ($data['Items'] as $item) {
                    if ($item['AvailableSeatCount'] > 0) {
                        $this->sendMessageToClient($item, $valueObject);
                    }
                }
                $date = $date->addDay();
            }
            $checked++;
            sleep($valueObject->getCheckDuration());
        }
    }

    private function checkTicket(Jalalian $date, TicketCheckerValueObject $valueObject): array {
        return $this->client->checkTicket($date, $valueObject->getSource(), $valueObject->getDestination());
    }

    private function sendMessageToClient(array $item, TicketCheckerValueObject $valueObject): void {

        $source        = $valueObject->getSource();
        $destination   = $valueObject->getDestination();
        $departureDate = $item['DepartureDate'];
        $link          = Safar724::BASE_URL . "/bus/$source-$destination?date=$departureDate/#/service:{$item['ID']}-{$item['DestinationCode']}";

        $sourceTerminal      = $item['OriginTerminalPersianName'];
        $destinationTerminal = $item['DestinationTerminalPersianName'];
        $date                = $this->enToFaNumbers("{$item['DepartureDate']} {$item['DepartureTime']}");
        $price               = $this->enToFaNumbers($item['Price']);
        $discount            = $this->enToFaNumbers($item['DiscountPercentage']);
        $availableSeatCount  = $this->enToFaNumbers($item['AvailableSeatCount']);
        $companyName         = $this->enToFaNumbers($item['CompanyPersianName']);
        $busType             = $item['BusType'];

        $message = "✨ اتوبوس موجود است:
🚌 از: $sourceTerminal
🛑 به: $destinationTerminal
📅 تاریخ و زمان حرکت: `$date`
🎫 تعداد صندلی موجود: $availableSeatCount
🚍 نوع اتوبوس: $busType
🏢 شرکت: $companyName
💰 قیمت: $price
💯 درصد تخفیف: $discount%
🌐 لینک خرید: <a href='$link'>لینک خرید</a>
";

        $this->notificationService->sendMessage($valueObject->getChatId(), $message, ['parse_mode' => 'HTML']);
    }

    private function enToFaNumbers(string|int $input): string {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            $input
        );
    }
}
