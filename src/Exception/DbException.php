<?php
/**
 * Starlit Db.
 *
 * @copyright Copyright (c) 2016 Starweb AB
 * @license   BSD 3-Clause
 */

namespace Starlit\Db\Exception;

/**
 * @author Andreas Nilsson <http://github.com/jandreasn>
 */
class DbException extends \RuntimeException
{
    public function __construct(\PDOException $e, $extraMessage = '')
    {
        // Strip boring unnecessary info from error message
        $strippedMsg = preg_replace('/SQLSTATE\[[A-Za-z-0-9]+\]( \[[A-Za-z-0-9]+\])?:?\s?/', '', $e->getMessage());

        // PDOExceptions' getCode() can  return a code with letters, which normal
        // exceptions won't accept. A converted code is better than no code at all though.
        parent::__construct($strippedMsg . $extraMessage, (int) $e->getCode());
    }
}
