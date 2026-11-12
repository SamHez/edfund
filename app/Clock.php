<?php
declare(strict_types=1);
namespace EdFund;
final class Clock implements \Psr\Clock\ClockInterface
{
    public function now(): \DateTimeImmutable { return new \DateTimeImmutable('now', new \DateTimeZone('UTC')); }
}
