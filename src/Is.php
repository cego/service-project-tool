<?php

namespace Cego;

class Is
{
    /**
     * Checks if a variable is an array
     *
     * @param mixed $variable
     *
     * @return bool
     */
    public static function array($variable)
    {
        return is_array($variable);
    }

    /**
     * Checks if a variable is an array
     *
     * @param mixed $variable
     *
     * @return bool
     */
    public static function notArray($variable)
    {
        return ! Is::array($variable);
    }

    /**
     * Checks if a variable is a boolean
     *
     * @param mixed $variable
     *
     * @return bool
     */
    public static function boolean($variable)
    {
        return is_bool($variable);
    }

    /**
     * Checks if a variable is an integer
     *
     * @param mixed $variable
     *
     * @return bool
     */
    public static function integer($variable)
    {
        return is_int($variable);
    }

    /**
     * Checks if a variable is a number
     *
     * @param mixed $variable
     *
     * @return bool
     */
    public static function number($variable)
    {
        return is_numeric($variable);
    }

    /**
     * Checks if a variable is a float
     *
     * @param mixed $variable
     *
     * @return bool
     */
    public static function float($variable)
    {
        return is_float($variable) || is_double($variable);
    }

    /**
     * Checks if a variable is a string
     *
     * @param mixed $variable
     *
     * @return bool
     */
    public static function string($variable)
    {
        return is_string($variable);
    }

    /**
     * Checks if an array is sequential
     *
     * @param array $array
     *
     * @return bool
     */
    public static function sequentialArray(array $array)
    {
        if ($array === []) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * Checks if an array is associative
     *
     * @param array $array
     *
     * @return bool
     */
    public static function associativeArray(array $array)
    {
        return ! Is::sequentialArray($array);
    }
}
