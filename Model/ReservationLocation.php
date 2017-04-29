<?php
/**
 * ReservationLocation Model
 *
 * @property Language $Language
 * @property Category $Category
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Your Name <yourname@domain.com>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('ReservationsAppModel', 'Reservations.Model');

/**
 * Summary for ReservationLocation Model
 */
class ReservationLocation extends ReservationsAppModel {

/**
 * use behaviors
 *
 * @var array
 */
	public $actsAs = array(
		'NetCommons.OriginalKey',
		'Wysiwyg.Wysiwyg' => array(
			'fields' => array('detail'),
		),
		//多言語
		'M17n.M17n' => array(
			'commonFields' => array( // 言語が異なっても同じにするフィールド
				'category_id',
				'weight'
			),
			'afterCallback' => false,
		),
		'Reservations.ReservationValidate',
	);

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
		'Language' => array(
			'className' => 'M17n.Language',
			'foreignKey' => 'language_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
		'Category' => array(
			'className' => 'Categories.Category',
			'foreignKey' => 'category_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
	);

/**
 * Called during validation operations, before validation. Please note that custom
 * validation rules can be defined in $validate.
 *
 * @param array $options Options passed from Model::save().
 * @return bool True if validate operation should continue, false to abort
 * @link http://book.cakephp.org/2.0/en/models/callback-methods.html#beforevalidate
 * @see Model::save()
 * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
 */
	public function beforeValidate($options = array()) {
		$this->validate = Hash::merge($this->validate,
			array(
				'language_id' => array(
					'numeric' => array(
						'rule' => array('numeric'),
						'message' => __d('net_commons', 'Invalid request.'),
					),
				),
				'category_id' => array(
					'numeric' => array(
						'rule' => array('numeric'),
						'message' => __d('net_commons', 'Invalid request.'),
						'allowEmpty' => true,
					),
				),
				'location_name' => array(
					'notBlank' => array(
						'rule' => array('notBlank'),
						'message' => __d('net_commons', 'Please input %s.', __d('reservations', 'Location name')),
					),
				),
				'time_table' => array(
					'notBlank' => array(
						'rule' => array('notBlank'),
						'message' => __d('reservations', 'Invalid input. Please enter week day.'),
					),
				),
				'start_time' => array(
					'rule1' => array(
						'rule' => array('validateTime'),
						'message' => __d('reservations', 'Invalid input. (time)'),
					),
					'rule2' => array(
						'rule' => array('validateTimeRange', 'end_time'),
						'message' => __d('reservations', 'Invalid input. Please enter the correct time.'),
					),
				),
				'end_time' => array(
					'rule1' => array(
						'rule' => array('validateTime'),
						'message' => __d('reservations', 'Invalid input. (time)'),
					),
				),
				'use_private' => array(
					'boolean' => array(
						'rule' => array('boolean'),
						'message' => __d('net_commons', 'Invalid request.'),
					),
				),
				'use_all_rooms' => array(
					'boolean' => array(
						'rule' => array('boolean'),
						'message' => __d('net_commons', 'Invalid request.'),
					),
				),
				'weight' => array(
					'numeric' => array(
						'rule' => array('numeric'),
						'message' => __d('net_commons', 'Invalid request.'),
					),
				),
		));

		return parent::beforeValidate($options);
	}

/**
 * Called before each find operation. Return false if you want to halt the find
 * call, otherwise return the (modified) query data.
 *
 * @param array $query Data used to execute this query, i.e. conditions, order, etc.
 * @return mixed true if the operation should continue, false if it should abort; or, modified
 *  $query to continue with new $query
 * @link http://book.cakephp.org/2.0/en/models/callback-methods.html#beforefind
 */
	public function beforeFind($query) {
		if (Hash::get($query, 'recursive') > -1 && ! $this->id) {
			$belongsTo = $this->Category->bindModelCategoryLang('ReservationLocation.category_id');
			$this->bindModel($belongsTo, true);
		}
		return true;
	}

/**
 * Called after each find operation. Can be used to modify any results returned by find().
 * Return value should be the (modified) results.
 *
 * @param mixed $results The results of the find operation
 * @param bool $primary Whether this model is being queried directly (vs. being queried as an association)
 * @return mixed Result of the find operation
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 * @link http://book.cakephp.org/2.0/en/models/callback-methods.html#afterfind
 */
	public function afterFind($results, $primary = false) {
		foreach ($results as $key => $value) {
			if (array_key_exists('time_table', $results[$key][$this->alias]) &&
					array_key_exists('start_time', $results[$key][$this->alias]) &&
					array_key_exists('end_time', $results[$key][$this->alias]) &&
					array_key_exists('timezone', $results[$key][$this->alias])) {
				$results[$key][$this->alias]['openText'] = $this->_openText($value);
			}
		}
		return $results;
	}

/**
 * 施設生成処理
 *
 * @return array
 */
	public function createLocation() {
		$newLocation = $this->create();
		$newLocation['ReservationLocation'] = Hash::merge(
			$newLocation['ReservationLocation'],
			[
				'start_time' => '09:00',
				'end_time' => '18:00',
				'time_table' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
				'use_all_rooms' => '1',
				'use_workflow' => '1',
				'timezone' => Current::read('User.timezone'),
			]
		);
		return $newLocation;
	}

/**
 * 施設データの登録
 *
 * @param array $data 登録データ
 * @return bool
 * @throws InternalErrorException
 */
	public function saveLocation($data) {
		$this->loadModels([
			'ReservationLocationsRoom' => 'Reservations.ReservationLocationsRoom',
			'ReservationLocationReservable' => 'Reservations.ReservationLocationReservable',
			'ReservationLocationsApprovalUser' => 'Reservations.ReservationLocationsApprovalUser',
		]);

		$data = $this->_prepareData($data);

		$this->begin();
		try {
			$this->create();

			// 先にvalidate 失敗したらfalse返す
			$this->set($data);
			if (!$this->validates($data)) {
				return false;
			}
			$savedData = $this->save($data, false);
			if (! $savedData) {
				//このsaveで失敗するならvalidate以外なので例外なげる
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			// ReservationLocationsRoom登録
			if (isset($savedData['ReservationLocationsRoom'])) {
				$key = $savedData[$this->alias]['key'];
				if (! $this->ReservationLocationsRoom->saveReservationLocaitonsRoom($key, $savedData)) {
					throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
				}
			}
			// ReservationLoationReservable登録
			if (! $this->ReservationLocationReservable->saveReservable($key, $savedData)) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}
			// ReservationLocationsApprovalUser登録
			if (! $this->ReservationLocationsApprovalUser->saveApprovalUser($key, $savedData)) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			//多言語化の処理
			$this->set($savedData);
			$this->saveM17nData();

			$this->commit();

		} catch (Exception $e) {
			$this->rollback($e);
		}
		return $savedData;
	}

/**
 * 並び替えの保存
 *
 * @param array $data 並び替えデータ
 * @throws InternalErrorException 例外エラー
 * @return bool
 */
	public function saveWeights($data) {
		//トランザクションBegin
		$this->begin();

		//バリデーション
		if (! $this->validateMany($data['ReservationLocations'])) {
			return false;
		}

		try {
			//登録処理
			if (! $this->saveMany($data['ReservationLocations'], ['validate' => false])) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			//トランザクションCommit
			$this->commit();

		} catch (Exception $ex) {
			//トランザクションRollback
			$this->rollback($ex);
		}

		return true;
	}

/**
 * 施設データの削除
 *
 * @param string $locationKey 施設キー
 * @return bool
 * @throws InternalErrorException
 */
	public function deleteLocation($locationKey) {
		$this->loadModels([
			'ReservationLocationsRoom' => 'Reservations.ReservationLocationsRoom',
			'ReservationLocationReservable' => 'Reservations.ReservationLocationReservable',
			'ReservationLocationsApprovalUser' => 'Reservations.ReservationLocationsApprovalUser',
			'ReservationEvent' => 'Reservations.ReservationEvent',
			'ReservationEventContent' => 'Reservations.ReservationEventContent',
			'ReservationEventShareUser' => 'Reservations.ReservationEventShareUser',
			'ReservationFrameSetting' => 'Reservations.ReservationFrameSetting',
		]);

		$this->begin();
		try {
			// ReservationLocation 削除
			$conditions = [
				$this->alias . '.key' => $locationKey
			];
			if (! $this->deleteAll($conditions)) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			// ReservationLocationsRoom 削除
			$conditions = [
				$this->ReservationLocationsRoom->alias . '.reservation_location_key' => $locationKey
			];
			if (! $this->ReservationLocationsRoom->deleteAll($conditions)) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			// ReservationLocationReservable 削除
			$conditions = [
				$this->ReservationLocationReservable->alias . '.location_key' => $locationKey
			];
			if (! $this->ReservationLocationReservable->deleteAll($conditions)) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			// ReservationLocationsApprovalUser 削除
			$conditions = [
				$this->ReservationLocationsApprovalUser->alias . '.location_key' => $locationKey
			];
			if (! $this->ReservationLocationsApprovalUser->deleteAll($conditions)) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			// 削除するReservationEvent.id 取得
			$conditions = [
				$this->ReservationEvent->alias . '.location_key' => $locationKey
			];
			$reserveIds = $this->ReservationEvent->find('list', [
				'recursive' => -1,
				'conditions' => $conditions
			]);
			$reserveIds = array_values($reserveIds);

			// ReservationEventContent 削除
			$conditions = [
				$this->ReservationEventContent->alias . '.reservation_event_id' => $reserveIds
			];
			if (! $this->ReservationEventContent->deleteAll($conditions)) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			// ReservationEventShareUser 削除
			$conditions = [
				$this->ReservationEventShareUser->alias . '.reservation_event_id' => $reserveIds
			];
			if (! $this->ReservationEventShareUser->deleteAll($conditions)) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			// ReservationEvent 削除
			$conditions = [
				$this->ReservationEvent->alias . '.location_key' => $locationKey
			];
			if (! $this->ReservationEvent->deleteAll($conditions)) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			//ReservationFrameSetting のlocation_keyを変更する
			$updateKey = $this->_getLocationKeyByMinWeight();
			$update = [
				$this->ReservationFrameSetting->alias . '.location_key' => '\'' . $updateKey . '\''
			];
			$conditions = [
				$this->ReservationFrameSetting->alias . '.location_key' => $locationKey
			];
			if (! $this->ReservationFrameSetting->updateAll($update, $conditions)) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}

			$this->commit();

		} catch (Exception $e) {
			$this->rollback($e);
		}

		return true;
	}

/**
 * weightの最大値取得
 *
 * @return int
 */
	protected function _getMaxWeight() {
		$order = $this->find('first', array(
				'recursive' => -1,
				'fields' => array('weight'),
				'order' => array('weight' => 'DESC')
			));

		if (isset($order[$this->alias]['weight'])) {
			$weight = (int)$order[$this->alias]['weight'];
		} else {
			$weight = 0;
		}
		return $weight;
	}

/**
 * weightの最小値の施設キー取得
 *
 * @return int
 */
	protected function _getLocationKeyByMinWeight() {
		$location = $this->find('first', array(
			'recursive' => -1,
			'fields' => array('key', 'weight'),
			'order' => array('weight' => 'ASC')
		));

		if (! $location) {
			$locationKey = null;
		} else {
			$locationKey = $location[$this->alias]['key'];
		}
		return $locationKey;
	}

/**
 * 施設データを取得する
 *
 * アクセス可能なルームで予約可能な施設だけに絞り込んで返す
 * とりあえず、すべて取得する
 *
 * @param int $categoryId カテゴリID
 * @return array
 */
	public function getLocations($categoryId = null) {
		// ログインユーザが参加してるルームを取得
		$accessibleRoomIds = $this->getReadableRoomIds();
		$this->loadModels([
			'ReservationLocationsRoom' => 'Reservations.ReservationLocationsRoom'
		]);
		$locationsRooms = $this->ReservationLocationsRoom->find('all', ['conditions' => [
			'room_id' => $accessibleRoomIds,
		]]);
		$locationKeys = Hash::combine($locationsRooms,
			'{n}.ReservationLocationsRoom.reservation_location_key',
			'{n}.ReservationLocationsRoom.reservation_location_key'
			);
		// そのルームからの予約を受け付ける施設を取得
		$options = [
			'conditions' => [
				'language_id' => Current::read('Language.id'),
				'OR' => [
					'use_all_rooms' => 1, // 全てのルームから予約を受け付ける施設
					'ReservationLocation.key' => $locationKeys
				]
			],
			'order' => 'ReservationLocation.weight ASC'
		];
		if (isset($categoryId)) {
			$options['conditions']['category_id'] = $categoryId;
		}

		$locations = $this->find('all', $options);
		return $locations;
	}

/**
 * openTextを返す
 *
 * @param array $reservationLocation 施設データ
 * @return string
 */
	protected function _openText($reservationLocation) {
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

		$ret = sprintf('%s %s - %s',
			$ret,
			$reservationLocation['ReservationLocation']['start_time'],
			$reservationLocation['ReservationLocation']['end_time']
		);
		if (AuthComponent::user('timezone') != $reservationLocation['ReservationLocation']['timezone']) {
			$SiteSetting = new SiteSetting();
			$SiteSetting->prepare();
			$ret .= ' ';
			$ret .= $SiteSetting->defaultTimezones[$reservationLocation['ReservationLocation']['timezone']];
		}
		return $ret;
	}

/**
 * 保存前にpostされたdataを保存用に加工する
 *
 * @param array $data POSTされたdata
 * @return array 保存用に加工されたdata
 */
	protected function _prepareData($data) {
		if (is_array(Hash::get($data, 'ReservationLocation.time_table'))) {
			$timeTable = implode('|', $data['ReservationLocation']['time_table']);
			$data['ReservationLocation']['time_table'] = $timeTable;
		}

		// 全日フラグあったら00:00-24:00あつかいにする
		if ($data['ReservationLocation']['allday_flag']) {
			$data['ReservationLocation']['start_time'] = '00:00';
			$data['ReservationLocation']['end_time'] = '24:00';
		}
		// start_time end_timeをUTCに変換
		$startDateTime = Date('Y-m-d') . $data['ReservationLocation']['start_time'] . ':00';
		$endDateTime = Date('Y-m-d') . $data['ReservationLocation']['end_time'] . ':00';
		$ncTime = new NetCommonsTime();
		$startDateTime4UTC = $ncTime->toServerDatetime($startDateTime,
			$data['ReservationLocation']['timezone']);
		$endDateTime4UTC = $ncTime->toServerDatetime($endDateTime,
			$data['ReservationLocation']['timezone']);
		$data['ReservationLocation']['start_time'] = $startDateTime4UTC;
		$data['ReservationLocation']['end_time'] = $endDateTime4UTC;

		// category_id=0だったらnullにする。そうしないと空文字としてSQL発行される
		if (empty($data[$this->alias]['category_id'])) {
			$data[$this->alias]['category_id'] = null;
		}

		//新規の場合、順番を最大値＋１にする
		if (empty($data['ReservationLocation']['id'])) {
			$data[$this->alias]['weight'] = $this->_getMaxWeight() + 1;
		}

		// 予約を受け付けるルームがひとつもえらばれてないとき
		if (!$data['ReservationLocationsRoom']['room_id']) {
			$data['ReservationLocationsRoom']['room_id'] = array();
		}

		return $data;
	}
}
