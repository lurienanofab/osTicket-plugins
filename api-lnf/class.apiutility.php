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

    static function getval($key, $defval, $arr = null) {
        if ($arr == null) $arr = $_REQUEST;
        $result = $defval;
        if (isset($arr[$key]))
            $result = $arr[$key];
        return $result;
    }

    static function getnum($key, $defval, $arr = null) {
        $val = self::getval($key, $defval, $arr);
        if (is_numeric($val)) return $val;
        else return $defval;
    }
}
