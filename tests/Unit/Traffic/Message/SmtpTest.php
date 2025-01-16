<?php

declare(strict_types=1);

namespace Buggregator\Trap\Tests\Unit\Traffic\Message;

use PHPUnit\Framework\TestCase;
use Buggregator\Trap\Traffic\Message\Smtp;

final class SmtpTest extends TestCase
{
    public function testTo(): void
    {
        $smtp = Smtp::create(
            protocol: [],
            headers: [
                'To' => [
                    'User1      <user1@inline.com>',
                    'User2<user2@inline.com>',
                    'user3@inline.com',
                ],
            ],
        );
        $toList = $smtp->getTo();
        self::assertCount(3, $toList);
        self::assertSame('User1', $toList[0]->name);
        self::assertSame('user1@inline.com', $toList[0]->email);
        self::assertSame('User2', $toList[1]->name);
        self::assertSame('user2@inline.com', $toList[1]->email);
        self::assertSame(null, $toList[2]->name);
        self::assertSame('user3@inline.com', $toList[2]->email);
    }

    public function testToInlineMulti(): void
    {
        $smtp = Smtp::create(
            protocol: [],
            headers: [
                'To' => [
                    'User1 <user1@inline.com>, User2 <user2@inline.com>, user3@inline.com',
                ],
            ],
        );
        $toList = $smtp->getTo();
        self::assertCount(3, $toList);
        self::assertSame('User1', $toList[0]->name);
        self::assertSame('user1@inline.com', $toList[0]->email);
        self::assertSame('User2', $toList[1]->name);
        self::assertSame('user2@inline.com', $toList[1]->email);
        self::assertSame(null, $toList[2]->name);
        self::assertSame('user3@inline.com', $toList[2]->email);
    }
}
