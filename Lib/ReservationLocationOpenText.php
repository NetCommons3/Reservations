<?php
/**
 * ReservationLocationOpenText.php
 *
 * @author   Ryuji AMANO <ryuji@ryus.co.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

/**
 * Class ReservationLocationOpenText
 */
class ReservationLocationOpenText {

/**
 * openTextを返す
 *
 * @param array $reservationLocation 施設データ
 * @return string
 */
	public function openText($reservationLocation) {
		$ret = '';
		$weekDaysOptions = [
			'Sun' => __d('holidays', 'Sunday'),
			'Mon' => __d('holidays', 'Monday'),
			'Tue' => __d('holidays', 'Tuesday'),
			'Wed' => __d('holidays', 'Wednesday'),
			'Thu' => __d('holidays', 'Thursday'),
			'Fri' => __d('holidays', 'Friday'),
			'Sat' => __d('holidays', 'Saturday'),
		];
		$timeTable = $reservationLocation['ReservationLocation']['time_table'];
		if ($timeTable === 'Sun|Mon|Tue|Wed|Thu|Fri|Sat') {
			//毎日
			$ret = __d('reservations', '毎日');
		} elseif ($timeTable === 'Mon|Tue|Wed|Thu|Fri') {
			// 平日
			$ret = __d('reservations', '平日');
		} else {
			$timeTable = explode('|', $timeTable);
			$weekList = [];
			foreach ($timeTable as $weekday) {
				if ($weekday) {
					$weekList[] = $weekDaysOptions[$weekday];
				}
			}
			$ret = implode(', ', $weekList);
		}

		//時間
		$startTime = $reservationLocation['ReservationLocation']['start_time'];
		$locationTimeZone = new DateTimeZone($reservationLocation['ReservationLocation']['timezone']);
		$startDate = new DateTime($startTime, new DateTimeZone('UTC'));

		$startDate->setTimezone($locationTimeZone);
		$reservationLocation['ReservationLocation']['start_time'] = $startDate->format('H:i');

		$endTime = $reservationLocation['ReservationLocation']['end_time'];
		$endDate = new DateTime($endTime, new DateTimeZone('UTC'));
		$endDate->setTimezone($locationTimeZone);
		$reservationLocation['ReservationLocation']['end_time'] = $endDate->format('H:i');
		// endで00:00は24:00あつかい
		if ($reservationLocation['ReservationLocation']['end_time'] == '00:00') {
			$reservationLocation['ReservationLocation']['end_time'] = '24:00';
		}

		$ret = sprintf('%s %s - %s',
			$ret,
			$reservationLocation['ReservationLocation']['start_time'],
			$reservationLocation['ReservationLocation']['end_time']
		);
		if (Current::read('User.timezone') !=
			$reservationLocation['ReservationLocation']['timezone']) {
			$SiteSetting = new SiteSetting();
			$SiteSetting->prepare();
			$ret .= ' ';
			$ret .= $SiteSetting->defaultTimezones[$reservationLocation['ReservationLocation']['timezone']];
		}
		return $ret;
	}

/**
 * openTextをセットした施設データを返す
 *
 * @param array $reservationLocations 施設データ
 * @return array
 */
	public function openTextAdd($reservationLocations) {
		foreach ($reservationLocations as $key => $value) {
			if (array_key_exists('time_table', $value['ReservationLocation']) &&
					array_key_exists('start_time', $value['ReservationLocation']) &&
					array_key_exists('end_time', $value['ReservationLocation']) &&
					array_key_exists('timezone', $value['ReservationLocation'])) {
				$reservationLocations[$key]['ReservationLocation']['openText'] = $this->openText($value);
			}
		}
		return $reservationLocations;
	}
}