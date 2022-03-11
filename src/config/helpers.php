<?php

if (! function_exists('isJson')) {
    /**
     * @param $data
     * @return bool|false
     */
    function isJson($data): bool
    {
        if (is_array($data)) {
            return false;
        }

        return (bool) preg_match('/^({.+})|(\[{.+}])$/', $data);
    }
}