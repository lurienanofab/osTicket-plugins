<?php

class XmlUtility {
	static function createXml($data, $root = 'root') {
		$xml = new SimpleXMLElement('<?xml version="1.0"?><'.$root.'></'.$root.'>');
		self::arrayToXml($data, $xml);
		return $xml;
        }

	private static function arrayToXml($data, &$xml_data) {
		foreach ($data as $key => $value) {
			if (is_array($value)) {
				if (is_numeric($key)) {
					$key = 'item'.$key; //dealing with <0/>..<n/> issues
				}
				$subnode = $xml_data->addChild($key);
				self::arrayToXml($value, $subnode);
			} else {
				$xml_data->addChild("$key", htmlspecialchars("$value"));
			}
		}
	}
}
