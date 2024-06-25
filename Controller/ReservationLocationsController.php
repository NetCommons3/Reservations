<?php
/**
 * 施設設定 Controller
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Ryuji AMANO <ryuji@ryus.co.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('ReservationsAppController', 'Reservations.Controller');
App::uses('ReservationLocationReservable', 'Reservations.Model');

/**
 * 施設設定 Controller
 *
 * @author Ryuji AMANO <ryuji@ryus.co.jp>
 * @package NetCommons\Reservations\Controller
 */
class ReservationLocationsController extends ReservationsAppController {

/**
 * @var int 施設編集画面で表示する最大ルーム数
 */
	const MAX_ROOMS = 300;

/**
 * layout
 *
 * @var array
 */
	public $layout = 'NetCommons.setting';

/**
 * @var array use models
 */
	public $uses = array(
		'Reservations.ReservationLocation',
		'Reservations.ReservationLocationChangeWeight',
		'Reservations.ReservationLocationsRoom',
		'Categories.Category',
		'Roles.Role',
		'Rooms.RoomRole',
		'Reservations.ReservationLocationReservable',
		'Reservations.ReservationLocationsApprovalUser',
		'Users.User',
	);

/**
 * Components
 *
 * @var array
 */
	public $components = array(
		'Categories.Categories',
		'NetCommons.NetCommonsTime',
		'Paginator',
		'Rooms.RoomsForm',
		'Reservations.ReservationSettings', //NetCommons.Permissionは使わず、独自でやる
	);

/**
 * @var array helpers
 */
	public $helpers = array(
		'Blocks.BlockTabs', // 設定内容はReservationSettingsComponentにまとめた
		'Rooms.RoomsForm',
		'Reservations.ReservationLocation',
		'Groups.GroupUserList',
		'Users.UserSearch'
	);

/**
 * index
 *
 * @return void
 */
	public function index() {
		//条件
		$conditions = array(
			'ReservationLocation.language_id' => Current::read('Language.id'),
		);
		if (isset($this->params['named']['category_id'])) {
			$conditions['ReservationLocation.category_id'] = $this->params['named']['category_id'];
		}
		$query = [
			'conditions' => $conditions,
			'recursive' => 0,
			'order' => 'ReservationLocation.weight ASC'
		];

		$this->Paginator->settings = [
			'ReservationLocation' => $query
		];
		$reservationLocations = $this->Paginator->paginate('ReservationLocation');

		// openTextの追加
		$openText = new ReservationLocationOpenText();
		$reservationLocations = $openText->openTextAdd($reservationLocations);

		$this->set('reservationLocations', $reservationLocations);
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		$this->set('isEdit', false);

		$this->_processPermission();

		// 施設管理者保持
		if ($this->request->is('post')) {
			$result = $this->ReservationLocation->saveLocation($this->request->data);
			if ($result) {
				$url = NetCommonsUrl::actionUrl(
					array(
						'controller' => 'reservation_locations',
						'action' => 'index',
						'frame_id' => Current::read('Frame.id'),
					)
				);
				return $this->redirect($url);
			}
			$this->NetCommons->handleValidationError($this->ReservationLocation->validationErrors);

			//未選択の場合、文字列の空値が入ってくる
			if (! is_array($this->request->data['ReservationLocationsRoom']['room_id'])) {
				$this->request->data['ReservationLocationsRoom']['room_id'] = [];
			}

			$isMyUser = false;
		} else {
			$newLocation = $this->ReservationLocation->createLocation();
			$this->request->data['ReservationLocation'] = $newLocation['ReservationLocation'];
			$isMyUser = true;
		}

		//施設管理者のセット
		$this->_setSelectUsers($isMyUser);

		// プライベートルームは除外する
		$roomConditions = [
			//'Room.space_id !=' => Space::PRIVATE_SPACE_ID,
		];
		$this->RoomsForm->setRoomsForCheckbox(
			$roomConditions,
			['limit' => self::MAX_ROOMS, 'maxLimit' => self::MAX_ROOMS]
		);

		$this->view = 'form';
	}

/**
 * 予約できる権限の処理
 *
 * @param null $key locationKey
 * @return void
 */
	protected function _processPermission($key = null) {
		$reservables = $this->ReservationLocationReservable->getPermissions($key);

		$this->request->data['ReservationLocationReservable'] = Hash::merge(
			$reservables,
			Hash::get($this->request->data, 'ReservationLocationReservable', array())
		);

		//Role取得
		$roles = $this->Role->find('all', array(
			'recursive' => -1,
			'conditions' => array(
				'Role.type' => Role::ROLE_TYPE_ROOM,
				'Role.language_id' => Current::read('Language.id'),
			),
		));
		$roles = Hash::combine($roles, '{n}.Role.key', '{n}.Role');

		//RoomRole取得
		$roomRoles = $this->RoomRole->find('all', array(
			'recursive' => -1,
		));
		$roomRoles = Hash::combine($roomRoles, '{n}.RoomRole.role_key', '{n}.RoomRole');
		$roomRoles = Hash::remove($roomRoles, '{n}.RoomRole.id');

		$this->set('roles', Hash::merge($roomRoles, $roles));
	}

/**
 * 担当者ユーザを設定
 *
 * @param bool $isMyUser 作成者ユーザー取得フラグ
 * @return void
 */
	protected function _setSelectUsers($isMyUser) {
		if ($isMyUser) {
			$this->request->data['ReservationLocationsApprovalUser'][] = array(
				'user_id' => Current::read('User.id')
			);
		}

		$this->request->data['selectUsers'] = array();
		if (isset($this->request->data['ReservationLocationsApprovalUser'])) {
			$selectUsers = Hash::extract(
				$this->request->data['ReservationLocationsApprovalUser'],
				'{n}.user_id'
			);
			foreach ($selectUsers as $userId) {
				$user = $this->User->getUser($userId);
				$this->request->data['selectUsers'][] = $user;
			}
		}
	}

/**
 * edit method
 *
 * @return void
 */
	public function edit() {
		$this->set('isEdit', true);
		$key = $this->params['key'];

		$this->_processPermission($key);

		//施設データの取得
		$reservationLocation = $this->ReservationLocation->find('first', [
			'recursive' => 0,
			'conditions' => [
				'ReservationLocation.key' => $key,
				'ReservationLocation.language_id' => Current::read('Language.id')
			]
		]);
		if (empty($reservationLocation)) {
			return $this->throwBadRequest();
		}
		$timeTable = explode('|', $reservationLocation['ReservationLocation']['time_table']);
		$reservationLocation['ReservationLocation']['time_table'] = $timeTable;

		if ($this->request->is(array('post', 'put'))) {
			// リクエストデータから施設管理者保持
			$this->_setSelectUsers(false);

			$data = $this->request->data;
			if ($this->ReservationLocation->saveLocation($data)) {
				$url = NetCommonsUrl::actionUrl(
					array(
						'controller' => 'reservation_locations',
						'action' => 'index',
						'frame_id' => Current::read('Frame.id'),
					)
				);
				return $this->redirect($url);
			}

			$this->NetCommons->handleValidationError($this->ReservationLocation->validationErrors);

			//未選択の場合、文字列の空値が入ってくる
			if (! is_array($this->request->data['ReservationLocationsRoom']['room_id'])) {
				$this->request->data['ReservationLocationsRoom']['room_id'] = [];
			}

		} else {
			// start_time, end_timeを施設のタイムゾーンに変換してH:i形式へ
			$locationTimeZone = new DateTimeZone($reservationLocation['ReservationLocation']['timezone']);
			$startDate = new DateTime(
				$reservationLocation['ReservationLocation']['start_time'], new DateTimeZone('UTC')
			);

			$startDate->setTimezone($locationTimeZone);
			$reservationLocation['ReservationLocation']['start_time'] = $startDate->format('H:i');

			$endDate = new DateTime(
				$reservationLocation['ReservationLocation']['end_time'], new DateTimeZone('UTC')
			);
			$endDate->setTimezone($locationTimeZone);
			$reservationLocation['ReservationLocation']['end_time'] = $endDate->format('H:i');

			$this->request->data['ReservationLocation'] = $reservationLocation['ReservationLocation'];

			//施設管理者のデータセット
			$approvalUsers = $this->ReservationLocationsApprovalUser->find('all', [
				'recursive' => 0,
				'conditions' => [
					'location_key' => $key
				]
			]);
			foreach ($approvalUsers as $approvalUser) {
				$this->request->data['selectUsers'][] = ['User' => $approvalUser['User']];
			}

			//予約を受け付けるルームを取得
			$result = $this->ReservationLocationsRoom->find('list', array(
				'recursive' => -1,
				'fields' => array('id', 'room_id'),
				'conditions' => ['reservation_location_key' =>
					$this->request->data['ReservationLocation']['key']],
			));
			$roomIds = array_unique(array_values($result));
			$this->request->data['ReservationLocationsRoom']['room_id'] = $roomIds;
		}

		$this->set('reservationLocation', $reservationLocation);
		$this->set('isDeletable', true);

		// プライベートルームは除外する
		$roomConditions = [
			//'Room.space_id !=' => Space::PRIVATE_SPACE_ID,
		];
		$this->RoomsForm->setRoomsForCheckbox(
			$roomConditions,
			['limit' => self::MAX_ROOMS, 'maxLimit' => self::MAX_ROOMS]
		);

		$this->view = 'form';
	}

/**
 * delete method
 *
 * @throws InternalErrorException
 * @return void
 */
	public function delete() {
		if (! $this->request->is('delete')) {
			return $this->throwBadRequest();
		}

		$key = $this->request->data['ReservationLocation']['key'];
		if (! $this->ReservationLocation->deleteLocation($key)) {
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}

		$url = NetCommonsUrl::actionUrl(
			array(
				'controller' => 'reservation_locations',
				'action' => 'index',
				'frame_id' => Current::read('Frame.id'),
			)
		);
		return $this->redirect($url);
	}

/**
 * 施設並び替え
 *
 * @return void
 */
	public function sort() {
		if ($this->request->is('post')) {
			if ($this->ReservationLocation->saveWeights($this->data)) {
				$url = NetCommonsUrl::actionUrl(
					array(
						'controller' => 'reservation_locations',
						'action' => 'index',
						'frame_id' => Current::read('Frame.id'),
					)
				);
				return $this->redirect($url);
			}
			$this->NetCommons->handleValidationError($this->ReservationLocation->validationErrors);

		} else {
			//条件
			$conditions = array(
				'ReservationLocation.language_id' => Current::read('Language.id'),
			);
			if (isset($this->params['named']['category_id'])) {
				$conditions['ReservationLocation.category_id'] = $this->params['named']['category_id'];
			}
			$query = [
				'conditions' => $conditions,
				'recursive' => 0,
				'order' => 'ReservationLocation.weight ASC',
				'limit' => PHP_INT_MAX,
				'maxLimit' => PHP_INT_MAX
			];

			$this->Paginator->settings = [
				'ReservationLocation' => $query
			];
			$reservationLocations = $this->Paginator->paginate('ReservationLocation');

			// openTextの追加
			$openText = new ReservationLocationOpenText();
			$reservationLocations = $openText->openTextAdd($reservationLocations);

			$this->set('reservationLocations', $reservationLocations);

			$this->request->data['ReservationLocations'] = $reservationLocations;
			$this->request->data['Frame'] = Current::read('Frame');
		}
	}
}
