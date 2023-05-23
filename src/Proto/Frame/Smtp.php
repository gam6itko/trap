<?php

declare(strict_types=1);

namespace Buggregator\Client\Proto\Frame;

use Buggregator\Client\Proto\Frame;
use Buggregator\Client\ProtoType;
use Buggregator\Client\Traffic\Smtp\Message;
use DateTimeImmutable;

final class Smtp extends Frame
{
    public function __construct(
        public readonly Message $message,
        DateTimeImmutable $time = new DateTimeImmutable(),
    ) {
        parent::__construct(ProtoType::SMTP, $time);
    }

    /**
     * @throws \JsonException
     */
    public function __toString(): string
    {
        return (string)$this->message->getBody();
    }

    public static function fromString(string $payload, DateTimeImmutable $time): self
    {
        return new self($payload, $time);
    }
}
