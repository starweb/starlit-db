<?php

namespace Starlit\Db\Monolog;

use Monolog\Logger;

class DbFormatterTest extends \PHPUnit_Framework_TestCase
{
    public function testFormat()
    {
        $formatter = new DbFormatter();

        $record = [
            'message' => 'The error message',
            'context' => [],
            'level' => Logger::ERROR,
            'level_name' => Logger::getLevelName(Logger::ERROR),
            'channel' => 'system',
            'extra' => [],
        ];

        $this->assertEquals('The error message', $formatter->format($record));
    }

    public function testFormatExceedMaxLength()
    {
        $formatter = new DbFormatter(null, true, true, 5);

        $record = [
            'message' => 'The error message',
            'context' => [],
            'level' => Logger::ERROR,
            'level_name' => Logger::getLevelName(Logger::ERROR),
            'channel' => 'system',
            'extra' => [],
        ];

        $this->assertEquals('Th...', $formatter->format($record));
    }
}
