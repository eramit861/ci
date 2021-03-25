<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class utilities_helper
{

    public static function test()
    {
        return ['a' => '1'];
    }

    /**
     * Outputs a success message $msg in json format.
     * 
     * @param mixed<string|array> $msg
     */
    public static function dieJsonSuccess($msg)
    {
        die(json_encode(['status' => '1', 'msg' => $msg]));
    }

    /**
     * Outputs a error message $msg in json format.
     * 
     * @param mixed<string|array> $msg
     */
    public static function dieJsonError($msg)
    {
        die(json_encode(['status' => '0', 'msg' => $msg]));
    }
}
