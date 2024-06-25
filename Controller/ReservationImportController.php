<?php
/**
 * 予約のインポート Controller
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Ryuji AMANO <ryuji@ryus.co.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('ReservationsAppController', 'Reservations.Controller');
App::uses('CsvFileReader', 'Files.Utility');

/**
 * 予約のインポート Controller
 *
 * @property ReservationSelectRoomComponent $ReservationSelectRoom
 *
 * @author Ryuji AMANO <ryuji@ryus.co.jp>
 * @package NetCommons\Reservations\Controller
 */
class ReservationImportController extends ReservationsAppController {

/**
 * reservation information
 *
 * @var array
 */
	protected $_vars = array();

/**
 * layout
 *
 * @var array
 */
	public $layout = 'NetCommons.setting';	//PageLayoutHelperのafterRender()の中で利用。
	//
	//$layoutに'NetCommons.setting'があると
	//「Frame設定も含めたコンテンツElement」として
	//ng-controller='FrameSettingsController'属性
	//ng-init=initialize(Frame情報)属性が付与される。
	//
	//'NetCommons.setting'がないと、普通の
	//「コンテンツElement」として扱われる。
	//
	//ちなみに、使用されるLayoutは、Pages.default
	//

/**
 * @var array use models
 */
	public $uses = array(
		'Reservations.ReservationLocation',
		'Reservations.ReservationLocationsRoom',
		'Categories.Category',
		'Reservations.ReservationImport',
		'Reservations.ReservationActionPlan',
		'Reservations.ReservationCsvRecord',
		'Reservations.ReservationEvent',
		'Reservations.ReservationLocationsRoom',
		//'Workflow.WorkflowComment',
	);

/**
 * Components
 *
 * @var array
 */
	public $components = array(
		'Categories.Categories',
		//'Blogs.ReservationLocationPermission',
		'NetCommons.NetCommonsTime',
		'Paginator',
		'Rooms.RoomsForm',
		'Reservations.ReservationSettings', //NetCommons.Permissionは使わず、独自でやる
		'Reservations.ReservationWorks',
		'Reservations.ReservationSelectRoom',
	);

/**
 * @var array helpers
 */
	public $helpers = array(
		'NetCommons.BackTo',
		'NetCommons.NetCommonsForm',
		'Workflow.Workflow',
		'NetCommons.NetCommonsTime',
		'NetCommons.TitleIcon',
		//'Blocks.BlockForm',

		'Blocks.BlockTabs', // 設定内容はReservationSettingsComponentにまとめた

		'Rooms.RoomsForm',
		'Reservations.ReservationLocation',
		'Reservations.ReservationWorkflow',
		'Reservations.ReservationPlan',

		'NetCommons.Date',
		'NetCommons.DisplayNumber',
		'NetCommons.Button',
		'Reservations.ReservationUrl',
		'Reservations.ReservationCommon',
		'Reservations.ReservationMonthly',
		'Reservations.ReservationCategory',
		'Reservations.ReservationShareUsers',
		'Reservations.ReservationEditDatetime',
		//'Reservations.ReservationExposeTarget',
		'Reservations.ReservationPlanRrule',
		'Reservations.ReservationPlanEditRepeatOption',
		'Groups.GroupUserList',
		'Users.UserSearch',


	);

/**
 * beforeFilter
 *
 * @return void
 */
	public function beforeFilter() {
		parent::beforeFilter();
	}

/**
 * edit method
 *
 * @return void
 */
	public function edit() {
		// 施設情報
		$locations = $this->ReservationLocation->getReservableLocations();

		// openTextの追加
		$openText = new ReservationLocationOpenText();
		$locations = $openText->openTextAdd($locations);

		$this->set('locations', $locations);

		$frameId = Current::read('Frame.id');
		if (! $frameId) {
			$this->setAction('can_not_edit');
			return;
		}

		$this->set('isEdit', true);
		if ($this->request->is(array('post', 'put'))) {
			$this->_reservationPost();
		} else {
			$this->request->data['ReservationActionPlan']['timezone'] = 'Asia/Tokyo';
		}
		$this->_reservationGet(ReservationsComponent::PLAN_ADD);

		$userId = Current::read('User.id');
		$defaultLocationKey = $this->request->data['ReservationActionPlan']['location_key'] ?? null;

		$defaultRooms = $this->ReservationSelectRoom->getDefaultPublishableRooms(
			$locations,
			$defaultLocationKey,
			$userId
		);
		$this->set('defaultPublishableRooms', json_encode($defaultRooms));

		// 選択済みルーム
		$selectedRoomId = $this->request->data['ReservationActionPlan']['plan_room_id'] ?? 0;
		$selectedRoom = $this->ReservationSelectRoom->getSelectedRoom(
			$defaultRooms,
			$selectedRoomId
		);
		$this->set('selectedRoom', json_encode($selectedRoom));
	}

/**
 * _reservationPost
 *
 * @return void
 * @SuppressWarnings(PHPMD)
 */
	protected function _reservationPost() {
		//CakeLog::debug("DBG: request_data[" . print_r($this->request->data, true) . "]");

		//CalenarActionPlanモデルの繰返し回数超過フラグをoffにしておく。
		$this->ReservationActionPlan->isOverMaxRruleIndex = false;

		//Xdebugがインストールされている環境だと、xdebug.max_nesting_levelの値（100とか200とか256とか）
		//の制限を受けてしまうので、再帰callを多用する施設予約登録では一時的に閾値を引き上げておく。
		$xdebugMaxNestingLvl = ini_get('xdebug.max_nesting_level');
		if ($xdebugMaxNestingLvl) {
			//Xdebugが入っている環境
			$xdebugMaxNestingLvl = ini_set('xdebug.max_nesting_level',
				ReservationsComponent::CALENDAR_XDEBUG_MAX_NESTING_LEVEL);
		}

		// ステータスは…
		$locationKey = $this->request->data['ReservationActionPlan']['location_key'];
		$location = $this->ReservationLocation->getByKey($locationKey);
		$userId = Current::read('User.id');
		// 1. 選択した施設が承認不要 →　公開
		if ($location['ReservationLocation']['use_workflow']) {
			if (in_array($userId, $location['approvalUserIds'])) {
				//　2.　承認者　→ 公開
				$status = WorkflowComponent::STATUS_PUBLISHED;
			} else {
				//　3. それ以外　公開申請
				$status = WorkflowComponent::STATUS_APPROVAL_WAITING;
			}
		} else {
			//選択した施設が承認不要 →　公開
			$status = WorkflowComponent::STATUS_PUBLISHED;
		}
		//$status = $this->ReservationActionPlan->getStatus($this->request->data);
		$this->request->data['ReservationActionPlan']['status'] = $status;
		$this->ReservationActionPlan->set($this->request->data);

		//公開対象のルームが、ログイン者（編集者・承認者）のプライベートルームかどうかを判断しておく。
		$isMyPrivateRoom =
			($this->request->data['ReservationActionPlan']['plan_room_id'] == $this->_myself);

		if (! $isMyPrivateRoom) {
			//CakeLog::debug("DBG: 予定のルームが、ログインの者のプライベートルーム以外の時");
			if (isset($this->request->data['GroupsUser'])) {
				//CakeLog::debug("DBG: 予定を共有する人情報は存在してはならないので、stripする。");
				unset($this->request->data['GroupsUser']);
			}
		}

		// CSVファイルとフォーム項目のバリデーション
		$this->ReservationImport->set($this->request->data);
		if (!$this->ReservationImport->validates()) {
			$this->ReservationActionPlan->validationErrors = Hash::merge(
				$this->ReservationActionPlan->validationErrors,
				$this->ReservationImport->validationErrors
			);
			$this->NetCommons->handleValidationError($this->ReservationActionPlan->validationErrors);
			return;
		}
		$path = $this->request->data['ReservationActionPlan']['csv_file']['tmp_name'];
		$csvFile = new CsvFileReader($path);

		// ここから登録開始
		$this->ReservationActionPlan->begin();

		if ($this->request->data['ReservationActionPlan']['delete_room_events']) {
			// 公開先の全予約を削除してからインポート
			$roomId = $this->request->data['ReservationActionPlan']['plan_room_id'];
			if (!$this->ReservationEvent->deleteEventByRoomId($roomId)) {
				$this->ReservationActionPlan->validationErrors['delete_room_events'] =
					__d('reservations', 'Delete failed.');
				$this->NetCommons->handleValidationError(
					$this->ReservationActionPlan->validationErrors,
					__d('reservations', 'Delete failed.')
				);
				return;
			}
		}
		$errors = [];
		$result = true;
		foreach ($csvFile as $index => $item) {
			if ($index == 0) {
				// 1行目は読み飛ばす
				continue;
			}
			// CSVデータのバリデーション "件名","利用時間の制限なし","予約日","開始時間","終了時間","連絡先","詳細"の順
			$csvRecord = $this->ReservationCsvRecord->getCsvRecordByRow($item, $location);
			$this->ReservationCsvRecord->create();
			$this->ReservationCsvRecord->set($csvRecord);
			if (!$this->ReservationCsvRecord->validates()) {
				// csvデータのバリデーションエラー
				foreach ($this->ReservationCsvRecord->validationErrors as $error) {
					$errorMessage = implode('', $error);
					//$errors[] = __d('reservations', '%d行目:%s', $index, $errorMessage);
					//$this->ReservationActionPlan->validationErrors['csv_file'][] =
					//	__d('reservations', '%d行目:%s', $index, $errorMessage);
					$errors['csv_file'][] =
						__d('reservations', 'Line %d : %s', $index, $errorMessage);
				}
				$result = false;
				continue; // 次の行へ

			}

			$this->ReservationActionPlan->create();

			$this->request->data['ReservationActionPlan']['timezone'] =
				Current::read('User.timezone');
			$this->request->data['ReservationActionPlan']['enable_time'] = 1;
			$this->request->data['ReservationActionPlan']['is_detail'] = 1;
			$this->request->data['ReservationActionPlan']['is_repeat'] = 0;
			$this->request->data['ReservationActionPlan']['WorkflowComment'] = '';
			$this->request->data['ReservationActionPlan']['title_icon'] = '';
			$this->request->data['ReservationActionPlan']['location'] = '';
			$this->request->data['ReservationActionPlan']['enable_email'] = 0;
			$this->request->data['ReservationActionPlan']['email_send_timing'] = 5;

			$this->request->data['ReservationActionPlan'] = Hash::merge(
				$this->request->data['ReservationActionPlan'],
				$this->ReservationCsvRecord->convertActionPlanData($csvRecord)
			);

			if ($this->request->data['ReservationActionPlan']['skip_duplicate_events']) {
				// 同じ予約を無視する設定なら→同じ予約があるか探す
				// 同じタイトル、日時の予約があったら、このCSVデータは保存しないでスキップ
				if ($this->_existDuplicateEvent($this->request->data)) {
					//$errorMessage = __d('reservations', '件名と予約日時が一致する予約がありました')
					//$errors['csv_file'][] =
					//	__d('reservations', '%d行目:%s', $index, $errorMessage);
					// 特にメッセージいらないかな?
					continue;
				}
			}

			$this->ReservationActionPlan->set($this->request->data);

			//校正用配列の準備
			$this->ReservationActionPlan->reservationProofreadValidationErrors = array();
			if (! $this->ReservationActionPlan->validates()) {

				$error = $this->ReservationActionPlan->validationErrors;
				foreach ($error as $field => $err) {
					if (in_array($field, ['location_key', 'room_id'])) {
						$errors[$field] = $err;
					} else {
						// location_key, room_id以外のバリデーションエラーはCSVレコード単位のエラー
						$errorMessage = implode('', $err);
						$errors['csv_file'][] =
							__d('reservations', 'Line %d : %s', $index, $errorMessage);
					}
				}
				$result = false;
				//$this->NetCommons->handleValidationError($this->ReservationActionPlan->validationErrors);
				continue;
			}

			$eventId = $this->ReservationActionPlan->saveImportRecord(
				$this->request->data,
				$this->_myself);
			if (!$eventId) {
				$errors['csv_file'][] =
					__d('reservations', 'Line %d : %s', $index,
						__d('reservations', 'Registration failed.'));
				$result = false;
			}

		}
		if (!$result) {
			$this->ReservationActionPlan->rollback();

			$this->ReservationActionPlan->validationErrors = $errors;
			$this->NetCommons->handleValidationError(
				$this->ReservationActionPlan->validationErrors
			);
			return;
		}
		$this->ReservationActionPlan->commit();

		$url = NetCommonsUrl::backToPageUrl();
		$this->redirect($url);
	}

/**
 * 件名、予約日時、施設が同じ予約が存在するか?
 * CSVインポートで「件名と予約日時が一致するデータは無視する」のときに利用。
 *
 * @param array $data ReservationActionPlan
 * @return bool
 */
	protected function _existDuplicateEvent($data) {
		$ncTime = new NetCommonsTime();
		$start = $ncTime->toServerDatetime($data['ReservationActionPlan']['detail_start_datetime']);
		$start = date('YmdHis', strtotime($start));
		$end = $ncTime->toServerDatetime($data['ReservationActionPlan']['detail_end_datetime']);
		$end = date('YmdHis', strtotime($end));
		$conditions = [
			'ReservationEvent.title' => $data['ReservationActionPlan']['title'],
			'ReservationEvent.dtstart' => $start,
			'ReservationEvent.dtend' => $end,
			'ReservationEvent.location_key' => $data['ReservationActionPlan']['location_key'],
		];
		$conditions = $this->ReservationEvent->getWorkflowConditions($conditions);
		$count = $this->ReservationEvent->find('count', ['conditions' => $conditions]);
		return ($count > 0);
	}

/**
 * _reservationGet
 *
 * @param string $planViewMode アクション
 * @return void
 */
	protected function _reservationGet($planViewMode) {
		//eventが空の場合、初期値でFILLした表示用配列を取得する。
		list(
			$year, $month, $day, $hour, $minute, $second, $enableTime
			) =
			$this->ReservationWorks->getDateTimeParam($this->request->query);
		$capForView = (new ReservationSupport())->getInitialReservationActionPlanForView(
			$year,
			$month,
			$day,
			$hour,
			$minute,
			$second,
			$enableTime,
			$this->_exposeRoomOptions
		);

		//0件を意味する空配列を入れておく。
		$eventSiblings = array();

		//eventが空なので、1stSibも初期値でFILLしておく
		$capForViewOf1stSib = $capForView;

		$firstSibEventId = 0; //新規だからidは未設定をあらわす0
		$firstSibEventKey = '';

		$startDatetime = $capForViewOf1stSib['ReservationActionPlan']['detail_start_datetime'];
		$year1stSib = substr($startDatetime, 0, 4);
		$month1stSib = substr($startDatetime, 5, 2);
		$day1stSib = substr($startDatetime, 8, 2);

		$firstSib = array(
			'ReservationActionPlan' => array(
				'first_sib_event_id' => $firstSibEventId,
				'first_sib_event_key' => $firstSibEventKey,
				'first_sib_year' => intval($year1stSib),
				'first_sib_month' => intval($month1stSib),
				'first_sib_day' => intval($day1stSib),
			),
		);
		//capForViewのrequest->data反映
		$this->request->data = $this->ReservationWorks->setCapForView2RequestData(
			$capForView,
			$this->request->data
		);

		$mailSettingInfo = $this->getMailSettingInfo();

		//reuqest->data['GroupUser']にある各共有ユーザの情報取得しセット
		$shareUsers = array();
		foreach ($this->request->data['GroupsUser'] as $user) {
			$shareUsers[] = $this->User->getUser($user['user_id'], Current::read('Language.id'));
		}

		//キャンセル時のURLセット
		//testセッション方式
		//$url = $this->__getSessionStoredRedirectUrl();
		//$url = []; //
		//$this->_vars['returnUrl'] = $url;

		$this->set(
			compact(
				'capForView',
				'mailSettingInfo',
				'shareUsers',
				'eventSiblings',
				'planViewMode',
				'firstSib'
			)
		);
		$this->set('vars', $this->_vars);
		$this->set('event', $this->eventData);
		$this->set('frameSetting', $this->_frameSetting);
		$this->set('exposeRoomOptions', $this->_exposeRoomOptions);
		$this->set('myself', $this->_myself);
		$this->set('emailOptions', $this->_emailOptions);
		$this->set('frameId', Current::read('Frame.id', 0));
		$this->set('languageId', Current::read('Language.id'));

		//$this->request->data['ReservationFrameSettingSelectRoom'] =
		//	$this->ReservationFrameSetting->getSelectRooms($this->_frameSetting['ReservationFrameSetting']['id']);
	}
}
