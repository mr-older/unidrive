<?php
/*
*	Gets system storage devices (NAME,VENDOR,MODEL,SERIAL,WWN,SIZE)
*	and its SMART values into "devices" variable
*
*	Author: Evgeny Zakharenko, 2024
*/

namespace UniDrive;

class StorageDevices
{
	public $devices, $error;

	public function __construct() {
		if(exec("lsblk -pdo NAME,VENDOR,MODEL,SERIAL,WWN,SIZE", $devices) === false || empty($devices)) {
			$this->error = "Error listing storage devices";
			return false;
		}

		if(($devices = $this->get($devices)) === false || empty($devices)) {
			$this->error = "Error getting storage devices info";
			return false;
		}

		foreach($devices as $device_name => $device) {
			if(($devices[$device_name]['SMART'] = $this->getSMART($device_name)) === false) {
				return false;
			}
		}

		$this->devices = $devices;
	}

	private function get($devices) {
		$devices_array = [];
		foreach($devices as $device) {
			$devices_array[] = explode(" ", preg_replace("/\s+/", " ", $device));
		}

		$devices_array = array_slice($devices_array, 1, count($devices_array) - 1);


		$devices = [];
		foreach($devices_array as $device) {
			$devices[$device[0]]['VENDOR'] = $device[1];
			$devices[$device[0]]['MODEL'] = $device[2];
			$devices[$device[0]]['SERIAL'] = $device[3];
			$devices[$device[0]]['WWN'] = $device[4];
			$devices[$device[0]]['SIZE'] = $device[5];
		}

		return $devices;
	}

	private function getSMART($device) {
		$first_to_seek = "ATTRIBUTE_NAME";
		$last_to_seek = "SMART Error Log";
		if(exec("smartctl -a $device", $smart) === false || empty($smart)) {
			$this->error = "Error running smartctl, make sure it is available";
			return false;
		}

		foreach($smart as $key => $smart_string) {
			if(strpos($smart_string, $first_to_seek) !== false) {
				$start = $key;
			}

			if(strpos($smart_string, $last_to_seek) !== false) {
				$end = $key;
			}
		}

		$start = $start ?? 0;
		$end = $end ?? 0;

		if(($length = $end - $start) < 1) {
			$this->error = "No data";
			return false;
		}

		$smart = array_slice($smart, $start, $length);
		$headers = explode(" ", preg_replace("/\s+/", " ", $smart[0]));
		$smart = array_slice($smart, 1, count($smart) - 2);
		$smart_array = [];

		foreach($smart as $key => $value) {
			$spaces = 0;
			$content = explode(" ", preg_replace("/\s+/", " ", $value));

			foreach($content as $content_key => $content_value)	{
				if($content_value === "") {
					$spaces++;
					continue;
				}

				if(empty($headers[($position = $content_key - $spaces)]))	{
					continue;
				}

				$smart_array[$key][$headers[$position]] = $content_value;
			}
		}

		$smart = [];
		foreach($smart_array as $value) {
			if(empty($value['ID#'])) continue;
			$smart[$value['ID#']] = array_slice($value, 1, count($value) - 1);
		}

		return $smart;
	}

	public function getSMARTValue($device_name, $value_name) {
		foreach((array) $this->devices[$device_name]['SMART'] as $key => $content) {
			if($content["ATTRIBUTE_NAME"] == $value_name) {
				 return $content["RAW_VALUE"];
			}
		}
		return false;
	}

}