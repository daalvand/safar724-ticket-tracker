<?php

namespace Daalvand\Safar724AutoTrack\ValueObjects;

use Carbon\Carbon;

class TicketCheckerValueObject
{
    private Carbon $from;
    private Carbon $to;
    private string|int $source;
    private string|int $destination;
    private int $checkDuration = 3600;
    private int $checkTimes = 10;
    private int $chatId;

    public function __construct(Carbon $from, Carbon $to, string|int $source, string|int $destination, int $chatId)
    {
        $this->from        = $from;
        $this->to          = $to;
        $this->source      = $source;
        $this->destination = $destination;
        $this->chatId      = $chatId;
    }

    public function getFrom(): Carbon
    {
        return $this->from;
    }

    public function setFrom(Carbon $from): static
    {
        $this->from = $from;
        return $this;
    }

    public function getTo(): Carbon
    {
        return $this->to;
    }

    public function setTo(Carbon $to): static
    {
        $this->to = $to;
        return $this;
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
        return $this;
    }

    public function getDestination(): string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): static
    {
        $this->destination = $destination;
        return $this;
    }

    public function getCheckDuration(): int
    {
        return $this->checkDuration;
    }

    public function setCheckDuration(int $checkDuration): static
    {
        $this->checkDuration = $checkDuration;
        return $this;
    }

    public function getCheckTimes(): int
    {
        return $this->checkTimes;
    }

    public function setCheckTimes(int $checkTimes): static
    {
        $this->checkTimes = $checkTimes;
        return $this;
    }

    public function getChatId(): int
    {
        return $this->chatId;
    }

    public function setChatId(int $chatId): static
    {
        $this->chatId = $chatId;
        return $this;
    }
}
