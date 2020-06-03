<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ScanTest extends TestCase
{
    public function testGetSeekTime(): void
    {
        $stub = $this->createStub(\App\Controllers\Elevator::class);

        $stub->method('getSeekTime')
            ->with([4, 5, 7, 9], 3)
            ->willReturn(6);

        $this->assertSame(6, $stub->getSeekTime([4, 5, 7, 9], 3));
    }
}