<?php

class ApiUtility {
    static function createXml($data, $root = 'root') {
        $xml = new SimpleXMLElement('<?xml version="1.0"?><'.$root.'></'.$root.'>');
        self::arrayToXml($data, $xml);
        return $xml;
    }

    private static function arrayToXml($data, &$xml_data) {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    //$key = 'item'.$key; //dealing with <0/>..<n/> issues
                    $key = 'row';
                }
                $subnode = $xml_data->addChild($key);
                self::arrayToXml($value, $subnode);
            } else {
                $xml_data->addChild("$key", htmlspecialchars("$value"));
            }
        }
    }

    static function getValue($key, $defval, $arr = null) {
        if ($arr === null)
            $arr = $_REQUEST;

        if (!isset($arr[$key]))
            return $defval;

        return $arr[$key];
    }

    static function getNumber($key, $defval, $arr = null) {
        $val = self::getValue($key, "", $arr);

        if ($val === "")
            return $defval;

        if (!is_numeric($val))
            return $defval;

        return $val;
    }

    static function getDate($key, $defval, $arr = null) {
        // this method check for a valid date in the form YYYY-MM-DD
        $val = self::getValue($key, "", $arr);

        if ($val === "")
            return $defval;

        $test = false;
        $parts = explode('-', $val);
        if (count($parts) == 3 && is_numeric($parts[0]) && is_numeric($parts[1]) && is_numeric($parts[2]))
            $test = checkdate($parts[1], $parts[2], $parts[0]);
        
        if ($test === false)
            return $defval;

        return $val;
    }

    static function dump($var) {
        die(json_encode($var));
    }
}
