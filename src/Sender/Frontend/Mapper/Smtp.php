<?php

declare(strict_types=1);

namespace Buggregator\Trap\Sender\Frontend\Mapper;

use Buggregator\Trap\Proto\Frame\Smtp as SmtpFrame;
use Buggregator\Trap\Sender\Frontend\Event;
use Buggregator\Trap\Support\Uuid;
use Buggregator\Trap\Traffic\Message\Multipart\File;
use Buggregator\Trap\Traffic\Message\Smtp as SmtpMessage;
use Buggregator\Trap\Traffic\Message\Smtp\MessageFormat;

/**
 * @internal
 */
final class Smtp
{
    public function map(SmtpFrame $frame): Event
    {
        $message = $frame->message;

        $uuid = Uuid::generate();
        $assets = self::fetchAssets($message);

        return new Event(
            uuid: $uuid,
            type: 'smtp',
            payload: [
                'from' => $message->getSender(),
                'reply_to' => $message->getReplyTo(),
                'subject' => $message->getSubject(),
                'to' => $message->getTo(),
                'cc' => $message->getCc(),
                'bcc' => $message->getBcc(),
                'text' => $message->getMessage(MessageFormat::Plain)?->getValue() ?? '',
                'html' => self::html($uuid, $message, $assets),
                'raw' => (string) $message->getBody(),
            ],
            timestamp: (float) $frame->time->format('U.u'),
            assets: $assets,
        );
    }

    /**
     * @return \ArrayAccess<non-empty-string, Event\Asset>
     */
    private static function fetchAssets(SmtpMessage $message): \ArrayAccess
    {
        /** @var \ArrayAccess<non-empty-string, Event\Asset> $assets */
        $assets = new \ArrayObject();

        foreach ($message->getAttachments() as $attachment) {
            $asset = self::asset($attachment);
            $assets->offsetSet($asset->uuid, $asset);
        }

        return $assets;
    }

    /**
     * @param non-empty-string $uuid UUID of the event
     */
    private static function assetLink(string $uuid, Event\Asset $asset): string
    {
        return "/api/smtp/$uuid/attachment/$asset->uuid";
    }

    private static function asset(File $attachment): Event\Asset
    {
        /**
         * Detect if the file is an embedded image
         *
         * @var non-empty-string|null $embedded
         */
        $embedded = match (true) {
            // Content-Disposition is inline and name is present
            \str_starts_with($attachment->getHeaderLine('Content-Disposition'), 'inline') && \preg_match(
                '/name=(?:\"([^\"]++)\"|\'([^\']++)\'|([^;,\\s]++))/',
                $attachment->getHeaderLine('Content-Disposition'),
                $matches,
            ) === 1 => $matches[1],

            // Content-Type is image/* and has name
            \str_starts_with($attachment->getHeaderLine('Content-Type'), 'image/') && \preg_match(
                '/name=(?:\"([^\"]++)\"|\'([^\']++)\'|([^;,\\s]++))/',
                $attachment->getHeaderLine('Content-Type'),
                $matches,
            ) === 1 => $matches[1],
            default => null,
        };

        return $embedded === null
            ? new Event\AttachedFile(
                id: Uuid::generate(),
                file: $attachment,
            )
            : new Event\EmbeddedFile(
                id: Uuid::generate(),
                file: $attachment,
                name: $embedded,
            );
    }

    /**
     * @param non-empty-string $uuid
     * @param \ArrayAccess<non-empty-string, Event\Asset> $assets
     */
    private static function html(string $uuid, SmtpMessage $message, \ArrayAccess $assets): string
    {
        $result = $message->getMessage(MessageFormat::Html)?->getValue() ?? '';

        // Replace CID links with actual asset links
        $toReplace = [];
        foreach ($assets as $asset) {
            $asset instanceof Event\EmbeddedFile and $toReplace["cid:{$asset->name}"] = self::assetLink($uuid, $asset);
        }

        return \str_replace(\array_keys($toReplace), \array_values($toReplace), $result);
    }
}
