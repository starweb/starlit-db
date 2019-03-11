<?php declare(strict_types=1);

namespace Starlit\Db {
    class ErrorStackTestHelper
    {
        public static $errors = [];
    }

    function trigger_error(string $error_msg, $type = E_USER_NOTICE)
    {
        ErrorStackTestHelper::$errors[] = [
            'message' => $error_msg,
            'type' => $type
        ];
    }
}
