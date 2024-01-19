<?php

namespace Daalvand\Safar724AutoTrack;

use Carbon\Carbon;
use Daalvand\Safar724AutoTrack\ValueObjects\TicketCheckerValueObject;
use Morilog\Jalali\Jalalian;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\CacheItem;
use Symfony\Contracts\Cache\ItemInterface;

class TicketChecker
{
    const TRACKER_MESSAGE_EXPIRE_TIME = 3600;
    protected FilesystemAdapter $cache;
    protected Safar724          $client;
    protected Telegram          $notificationService;

    public function __construct()
    {
        $this->client              = new Safar724();
        $this->notificationService = new Telegram();
        $this->cache               = new FilesystemAdapter();
    }

    public function track(TicketCheckerValueObject $valueObject): void
    {
        $fromDate = Jalalian::fromCarbon($valueObject->getFrom());
        $toDate   = Jalalian::fromCarbon($valueObject->getTo());
        $checked  = 0;
        while ($checked < $valueObject->getCheckTimes()) {
            $date = $fromDate;
            while ($date->lessThanOrEqualsTo($toDate)) {
                $data = $this->checkTicket($date, $valueObject);
                foreach ($data['Items'] as $item) {
                    if ($item['AvailableSeatCount'] > 0 && $this->ticketTimeIsValid($item, $valueObject)) {
                        $this->sendMessageToClient($item, $valueObject);
                    }
                }
                $date = $date->addDay();
            }
            $checked++;
            sleep($valueObject->getCheckDuration());
        }
    }

    private function checkTicket(Jalalian $date, TicketCheckerValueObject $valueObject): array
    {
        return $this->client->checkTicket($date, $valueObject->getSource(), $valueObject->getDestination());
    }

    private function sendMessageToClient(array $item, TicketCheckerValueObject $valueObject): void
    {
        $link    = $this->generateCheckoutLink($item, $valueObject);
        $message = $this->getTrackMessage($item);
        $button  = [
            'inline_keyboard' => [
                [
                    [
                        'text' => "✅ خرید",
                        'url'  => $link
                    ]
                ]
            ]
        ];
        $key     = "track_checker_message_" . md5(json_encode([$button, $message]));
        $item    = $this->cache->getItem($key);
        if ($item->isHit()) {
            return;
        }
        $this->notificationService->sendMessage($valueObject->getChatId(), $message, ['reply_markup' => $button]);
        $item->expiresAfter(self::TRACKER_MESSAGE_EXPIRE_TIME)->set(true);

    }

    private function enToFaNumbers(string|int $input): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'],
            $input
        );
    }

    protected function generateCheckoutLink(array $item, TicketCheckerValueObject $valueObject): string
    {
        $id                 = $item['ID'];
        $source             = $valueObject->getSource();
        $destination        = $valueObject->getDestination();
        $departureDate      = str_replace('/', '-', $item['DepartureDate']);
        $originTerminalCode = $item['OriginTerminalCode'];
        $destinationCode    = $item['DestinationCode'];

        return Safar724::BASE_URL .
            "/checkout/$originTerminalCode/$source/$destinationCode/$destination/" .
            "$departureDate/$id-$destinationCode#step-reserve";
    }

    private function ticketTimeIsValid(array $item, TicketCheckerValueObject $valueObject): bool
    {
        $commonDate     = "2000-01-01 ";
        $fromTime       = $valueObject->getFromTime();
        $toTime         = $valueObject->getToTime();
        $startDateTime  = Jalalian::fromFormat('Y-m-d H:i', $commonDate . $fromTime);
        $endDateTime    = Jalalian::fromFormat('Y-m-d H:i', $commonDate . $toTime);
        $recordDateTime = Jalalian::fromFormat('Y-m-d H:i', $commonDate . $item['DepartureTime']);
        if ($endDateTime->lessThanOrEqualsTo($startDateTime)) {
            $endDateTime = $endDateTime->addDay();
        }

        return $recordDateTime->greaterThanOrEqualsTo($startDateTime) &&
            $recordDateTime->lessThanOrEqualsTo($endDateTime);
    }

    private function getTrackMessage(array $item): string
    {
        $sourceTerminal      = $item['OriginTerminalPersianName'];
        $destinationTerminal = $item['DestinationTerminalPersianName'];
        $date                = $this->enToFaNumbers("{$item['DepartureDate']} {$item['DepartureTime']}");
        $price               = $this->enToFaNumbers($item['Price']);
        $discount            = $this->enToFaNumbers($item['DiscountPercentage']);
        $availableSeatCount  = $this->enToFaNumbers($item['AvailableSeatCount']);
        $companyName         = $this->enToFaNumbers($item['CompanyPersianName']);
        $busType             = $item['BusType'];
        return "✨ اتوبوس موجود است:
🚌 از: $sourceTerminal
🛑 به: $destinationTerminal
📅 تاریخ و زمان حرکت: `$date`
🎫 تعداد صندلی موجود: $availableSeatCount
🚍 نوع اتوبوس: $busType
🏢 شرکت: $companyName
💰 قیمت: $price
💯 درصد تخفیف: $discount%";
    }
}
