<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db\Monolog;

use Monolog\Formatter\LineFormatter;

/**
 * @author Andreas Nilsson <http://github.com/jandreasn>
 */
class DbFormatter extends LineFormatter
{
    /**
     * @const string
     */
    const FORMAT_DEFAULT = "%message% %context% %extra%";

    /**
     * @const int
     */
    const MAX_LENGTH_DEFAULT = 65535;

    /**
     * @var int
     */
    protected $maxLength;

    /**
     * @param string|null $format
     * @param bool        $allowInlineLineBreaks
     * @param bool        $ignoreEmptyContextAndExtra
     * @param int         $maxLength
     */
    public function __construct(
        $format = null,
        $allowInlineLineBreaks = true,
        $ignoreEmptyContextAndExtra = true,
        $maxLength = self::MAX_LENGTH_DEFAULT
    ) {
        parent::__construct(
            $format ?: static::FORMAT_DEFAULT,
            null,
            $allowInlineLineBreaks,
            $ignoreEmptyContextAndExtra
        );

        $this->maxLength = $maxLength;
    }

    /**
     * {@inheritdoc}
     */
    public function format(array $record)
    {
        $output = trim(parent::format($record));

        if ($this->maxLength !== null && strlen($output) > $this->maxLength) {
            $output = substr($output, 0, $this->maxLength - 3) . '...';
        }

        return $output;
    }
}
