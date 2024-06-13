<?php
/**
 * Reservation Plans Controller
 *
 * @property PaginatorComponent $Paginator
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Ryuji AMANO <ryuji@ryus.co.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('ReservationsAppController', 'Reservations.Controller');
App::uses('NetCommonsTime', 'NetCommons.Utility');
App::uses('ReservationTime', 'Reservations.Utility');
App::uses('ReservationPermissiveRooms', 'Reservations.Utility');

/**
 * ReservationPlansController
 *
 * @property ReservationSelectRoomComponent $ReservationSelectRoom
 *
 * @author Ryuji AMANO <ryuji@ryus.co.jp>
 * @package NetCommons\Reservations\Controller
 */
class ReservationPlansController extends ReservationsAppController {

/**
 * event data
 *
 * @var array
 */
	public $eventData = array();

/**
 * event share users
 *
 * @var array
 */
	public $shareUsers = array();

/**
 * reservation event create permission settings
 *
 * @var array
 */
	public $roomPermRoles = array();

/**
 * reservation information
 *
 * @var array
 */
	protected $_vars = array();

/**
 * use models
 *
 * @var array
 */
	public $uses = array(
		'Reservations.ReservationRrule',
		'Reservations.ReservationEvent',
		'Reservations.ReservationFrameSetting',
		'Reservations.ReservationEventShareUser',
		'Reservations.ReservationSetting',
		'Reservations.ReservationWorkflow',
		'Reservations.ReservationLocation',
		'Reservations.ReservationLocationsRoom',
		'Holidays.Holiday',
		'Rooms.Room',
		'Reservations.ReservationActionPlan',	//予定追加変更action専用
		'Reservations.ReservationDeleteActionPlan',	//予定削除action専用
		'Rooms.RoomsLanguage',
		'Users.User',
		'Mails.MailSetting',
	);

/**
 * use component
 *
 * @var array
 */
	public $components = array(
		/* ここは施設予約では無理。施設予約は全空間を相手にするから
		'NetCommons.Permission' => array(
			//アクセスの権限
			'allow' => array(
				//indexとviewは祖先基底クラスNetCommonsAppControllerで許可済
				'edit,add,delete' => 'content_creatable',
				//null, //content_readableは全員に与えられているので、チェック省略
				'view' => 'content_readable',
				////'select' => null,
			),
		),*/
		'Reservations.ReservationPermission',
		'Reservations.ReservationSelectRoom',
		'Paginator',
		'Reservations.ReservationsDaily',
		'Reservations.ReservationWorks',
		'UserAttributes.UserAttributeLayout',	//グループ管理の初期値
												//設定の時に必要
		'Categories.Categories',
	);

/**
 * use helpers
 *
 * @var array
 */
	public $helpers = array(
		'Workflow.Workflow',
		'NetCommons.Date',
		'NetCommons.DisplayNumber',
		'NetCommons.Button',
		'NetCommons.TitleIcon',
		'Reservations.ReservationUrl',
		'Reservations.ReservationCommon',
		'Reservations.ReservationMonthly',
		'Reservations.ReservationPlan',
		'Reservations.ReservationCategory',
		'Reservations.ReservationShareUsers',
		'Reservations.ReservationEditDatetime',
		//'Reservations.ReservationExposeTarget',
		'Reservations.ReservationPlanRrule',
		'Reservations.ReservationPlanEditRepeatOption',
		'Groups.GroupUserList',
		'Users.UserSearch',
		//'Categories.Category',
	);

/**
 * beforeRender
 *
 * @return void
 */
	public function beforeFilter() {
		parent::beforeFilter();

		// 以前はここでCurrentのブロックIDをチェックする処理があったが
		// 施設予約はCurrentのブロックID（＝現在表示中ページのブロックID）は
		// 表示データ上の意味がないのでチェックは行わない
		// 表示ブロックIDがないときは、パブリックTOPページで仮表示されることに話が決まった

		//ε(　　　　 v ﾟωﾟ)　＜ 不要ではないか？ by NakaZii
		//$this->Auth->allow('add', 'delete', 'edit', 'view');

		$this->ReservationEvent->initSetting($this->Workflow);

		// 施設予約権限設定情報確保
		$this->roomPermRoles = $this->ReservationEvent->prepareCalRoleAndPerm();
		ReservationPermissiveRooms::setRoomPermRoles($this->roomPermRoles);

		// 表示のための各種共通パラメータ設定
		$this->_vars = $this->__getVarsForShow();
	}

/**
 * delete
 *
 * @return void
 * @SuppressWarnings(PHPMD)
 */
	public function delete() {
		//CakeLog::debug("DBG: delete()開始");

		//レイアウトの設定
		$this->viewClass = 'View';
		$this->layout = 'NetCommons.modal';
		if ($this->request->is('delete')) {
			//CakeLog::debug("DBG: 削除処理がPOSTされました。");

			//Eventデータ取得
			//内部でCurrent::permission('content_creatable'),Current::permission('content_editable')
			//が使われている。
			//
			$eventData = $this->ReservationEvent->getWorkflowContents('first', array(
				'recursive' => -1,
				'conditions' => array(
					$this->ReservationEvent->alias . '.id' =>
						$this->data['ReservationDeleteActionPlan']['origin_event_id'],
				)
			));
			if (!$eventData) {
				//該当eventが存在しない。
				//他の人が先に削除した、あるいは、自分が他のブラウザから削除
				//した可能性があるので、エラーとせず、
				//削除成功扱いにする。
				CakeLog::notice("指定したevent_id[" .
					$this->data['ReservationDeleteActionPlan']['origin_event_id'] .
					"]はすでに存在しませんでした。");

				//testセッション方式
				$url = $this->__getSessionStoredRedirectUrl();
				$this->redirect($url);
				return;	//redirect後なので、ここには到達しない
			}
			if ($eventData) {
				//削除対象イベントあり

				//施設予約権限管理の承認を考慮した、Event削除権限チェック
				if (! $this->ReservationEvent->canDeleteContent($eventData)) {
					// 削除権限がない？！
					$this->throwBadRequest();
					return false;
				}

				$this->ReservationDeleteActionPlan->set($this->request->data);
				if (!$this->ReservationDeleteActionPlan->validates()) {
					//バリデーションエラー
					$this->NetCommons->handleValidationError($this->ReservationDeleteActionPlan->validationErrors);
				} else {
					//削除実行
					//

					//元データ繰返し有無の取得
					$eventSiblings = $this->ReservationEvent->getSiblings(
						$eventData['ReservationEvent']['reservation_rrule_id']);
					$isOriginRepeat = false;
					if (count($eventSiblings) > 1) {
						$isOriginRepeat = true;
					}

					if ($this->ReservationDeleteActionPlan->deleteReservationPlan($this->request->data,
						$eventData['ReservationEvent']['id'],
						$eventData['ReservationEvent']['key'],
						$eventData['ReservationEvent']['reservation_rrule_id'],
						$isOriginRepeat)) {
						//削除成功
						//testセッション方式
						$url = $this->__getSessionStoredRedirectUrl();
						$this->redirect($url);
						return;	//redirect後なので、ここには到達しない
					} else {
						CakeLog::error("削除実行エラー");
						//エラーメッセージのセット. 便宜的にis_repeatを利用
						$this->ReservationDeleteActionPlan->validationErrors['is_repeat'] =
							__d('reservations', 'Delete failed.');
						return $this->throwBadRequest();
					}
				}
			}
		}

		//Viewに必要な処理があれば以下にかく。

		$this->request->data['ReservationDeleteActionPlan']['is_repeat'] = 0;
		if (!empty($this->request->query['action'])) {
			if ($this->request->query['action'] == 'repeatdelete') {
				$this->request->data['ReservationDeleteActionPlan']['is_repeat'] = 1;
			}
		}
		$isRepeat = $this->request->data['ReservationDeleteActionPlan']['is_repeat'];

		$this->request->data['ReservationDeleteActionPlan']['first_sib_event_id'] = 0;
		if (!empty($this->request->query['first_sib_event_id'])) {
			$this->request->data['ReservationDeleteActionPlan']['first_sib_event_id'] =
				intval($this->request->query['first_sib_event_id']);
		}
		$firstSibEventId = $this->request->data['ReservationDeleteActionPlan']['first_sib_event_id'];

		$this->request->data['ReservationDeleteActionPlan']['origin_event_id'] = 0;
		if (!empty($this->request->query['origin_event_id'])) {
			$this->request->data['ReservationDeleteActionPlan']['origin_event_id'] =
				intval($this->request->query['origin_event_id']);
		}
		$originEventId = $this->request->data['ReservationDeleteActionPlan']['origin_event_id'];

		$this->request->data['ReservationDeleteActionPlan']['is_recurrence'] = 0;
		if (!empty($this->request->query['is_recurrence'])) {
			$this->request->data['ReservationDeleteActionPlan']['is_recurrence'] =
				intval($this->request->query['is_recurrence']);
		}
		$isRecurrence = $this->request->data['ReservationDeleteActionPlan']['is_recurrence'];

		$this->set(compact('isRepeat', 'firstSibEventId', 'originEventId', 'isRecurrence'));
		$this->set('event', $this->eventData);

		//renderを発行しないので、デフォルトのdelete.ctpがレンダリングされる。
	}

/**
 * add
 *
 * @return void
 */
	public function add() {
		// 施設情報
		$locations = $this->ReservationLocation->getReservableLocations();
		$locations = $this->__mergeApprovalUserName($locations);
		$this->set('locations', $locations);

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
			(int)$selectedRoomId
		);
		$this->set('selectedRoom', json_encode($selectedRoom));

		$frameId = Current::read('Frame.id');
		if (! $frameId) {
			$this->setAction('can_not_edit');
			return;
		}
		if ($this->request->is('post')) {
			$this->_reservationPost();
		}
		// 表示のための処理
		$this->_reservationGet(ReservationsComponent::PLAN_ADD);
		// 表示画面CTPはdetail_edit
		$this->view = 'detail_edit';
	}

/**
 * edit
 *
 * @return void
 */
	public function edit() {
		if ($this->request->is('post')) {
			$editRrule = $this->ReservationActionPlan->getEditRruleForUpdate($this->request->data);
			if ($editRrule == ReservationAppBehavior::CALENDAR_PLAN_EDIT_THIS) {
				$this->request->data['ReservationActionPlan']['is_repeat'] = '0';
			}
			$this->_reservationPost();
		}
		// 表示のための処理
		$this->_reservationGet(ReservationsComponent::PLAN_EDIT);
		//コメントデータのセット(コメントデータは編集のときしかないので共通処理に持っていってない）
		$comments =
			$this->ReservationEvent->getCommentsByContentKey($this->eventData['ReservationEvent']['key']);
		$this->set('comments', $comments);
		// 表示画面CTPはdetail_edit
		$this->view = 'detail_edit';

		// 施設情報
		$locations = $this->ReservationLocation->getReservableLocations();
		$locations = $this->__mergeApprovalUserName($locations);
		$this->set('locations', $locations);

		// 公開対象選択肢
		$userId = Current::read('User.id');
		$defaultLocationKey = $this->request->data['ReservationActionPlan']['location_key'];
		$defaultRooms = $this->ReservationSelectRoom->getDefaultPublishableRooms(
			$locations,
			$defaultLocationKey,
			$userId
		);
		$this->set('defaultPublishableRooms', json_encode($defaultRooms));

		// 選択済みルーム
		$selectedRoomId = $this->request->data['ReservationActionPlan']['plan_room_id'];
		$selectedRoom = $this->ReservationSelectRoom->getSelectedRoom(
			$defaultRooms,
			(int)$selectedRoomId
		);
		$this->set('selectedRoom', json_encode($selectedRoom));
	}

/**
 * can_not_edit
 *
 * 施設予約は現在フレームIDがないと、編集ができないため
 * フレームID未指定で編集画面へ来てしまった時のエラーメッセージ画面を用意しておく
 *
 * @return void
 */
	public function can_not_edit() {
		//実装中
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

		// 押されたボタンからステータスをセット
		$status = $this->ReservationActionPlan->getStatus($this->request->data);
		$this->request->data['ReservationActionPlan']['status'] = $status;
		$this->ReservationActionPlan->set($this->request->data);

		//校正用配列の準備
		$this->ReservationActionPlan->reservationProofreadValidationErrors = array();
		if (! $this->ReservationActionPlan->validates()) {

			//validationエラーの内、いくつか（主にrrule関連)を校正する。
			$this->ReservationActionPlan->proofreadValidationErrors($this->ReservationActionPlan);

			//これでエラーmsgが画面上部に数秒間flashされる。
			$this->NetCommons->handleValidationError($this->ReservationActionPlan->validationErrors);

			return;
		}

		// validate OK
		$originEvent = array();
		if (!empty($this->request->data['ReservationActionPlan']['origin_event_id'])) {
			$originEvent = $this->ReservationEvent->getEventById(
				$this->request->data['ReservationActionPlan']['origin_event_id']);
		}
		//追加・変更、元データ繰返し有無、及び時間・繰返し系変更タイプの判断処理
		list($procMode, $isOriginRepeat, $isTimeMod, $isRepeatMod) =
			$this->ReservationActionPlan->getProcModeOriginRepeatAndModType(
				$this->request->data, $originEvent
			);

		//変更時の生成者を勘案・取得する。
		$createdUserWhenUpd = $this->__getCreatedUserWhenUpd(
			$procMode, $originEvent,
			$this->request->data['ReservationActionPlan']['plan_room_id'],
			$this->_myself
		);

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

		//成功なら元画面(施設予約orスケジューラー)に戻る。
		//FIXME: 遷移元がview.ctpなら、戻り先をview.ctpに変える必要あり。
		//

		$eventId = $this->ReservationActionPlan->saveReservationPlan(
			$this->request->data, $procMode, $isOriginRepeat, $isTimeMod, $isRepeatMod,
			$createdUserWhenUpd, $this->_myself);
		if (!$eventId) {
			//保存失敗
			CakeLog::error("保存失敗");	//FIXME: エラー処理を記述のこと。

			if ($this->ReservationActionPlan->isOverMaxRruleIndex) {
				CakeLog::info("save(ReservationPlanの内部で施設予約のrruleIndex回数超過が" .
					"発生している。");
				$this->ReservationActionPlan->validationErrors['rrule_until'] = array();
				$this->ReservationActionPlan->validationErrors['rrule_until'][] =
					sprintf(__d('reservations',
						'Cyclic rules using deadline specified exceeds the maximum number of %d',
						intval(ReservationsComponent::CALENDAR_RRULE_COUNT_MAX)));
			} else {
				CakeLog::error("DBG: その他の不明なエラーが発生しました。");
				$this->ReservationActionPlan->validationErrors['rrule_until'] = array();
				$this->ReservationActionPlan->validationErrors['rrule_until'][] =
						__d('reservations', 'An unknown error occurred.');
			}

			//これでエラーmsgが画面上部に数秒間flashされる。
			$this->NetCommons->handleValidationError($this->ReservationActionPlan->validationErrors);

			return;

		}
		//保存成功
		$event = $this->ReservationEvent->findById($eventId);
		$url = NetCommonsUrl::actionUrlAsArray(array(
			'plugin' => 'reservations',
			'controller' => 'reservation_plans',
			'action' => 'view',
			'key' => $event['ReservationEvent']['key'],
			'frame_id' => Current::read('Frame.id'),
		));
		$this->redirect($url);
	}

/**
 * _reservationGet
 *
 * @param string $planViewMode アクション
 * @return void
 */
	protected function _reservationGet($planViewMode) {
		//eventのデータを取り出しセットするか、初期データをセットする
		//かのいずれかを行う。
		if ($planViewMode == ReservationsComponent::PLAN_EDIT) {
			//eventが存在する場合、該当eventの表示用配列を取得する。
			$capForView = (new ReservationSupport())->getReservationActionPlanForView($this->eventData);

			//eventの兄弟も探しておく。この時、dtstartでソートし繰返し先頭データが取得できるようにしておく。
			$eventSiblings = $this->ReservationEvent->getSiblings(
				$this->eventData['ReservationEvent']['reservation_rrule_id']);

			//自分もふくむので1件以上あることはまちがいない。
			$capForViewOf1stSib = (new ReservationSupport())->getReservationActionPlanForView(
				$eventSiblings[0]
			);

			$firstSibEventId = $eventSiblings[0]['ReservationEvent']['id'];
			$firstSibEventKey = $eventSiblings[0]['ReservationEvent']['key'];
		} else {
			//eventが空の場合、初期値でFILLした表示用配列を取得する。
			list($year, $month, $day, $hour, $minute, $second, $enableTime) =
				$this->ReservationWorks->getDateTimeParam($this->request->query);
			$capForView = (new ReservationSupport())->getInitialReservationActionPlanForView(
				$year, $month, $day, $hour, $minute, $second, $enableTime, $this->_exposeRoomOptions);

			//0件を意味する空配列を入れておく。
			$eventSiblings = array();

			//eventが空なので、1stSibも初期値でFILLしておく
			$capForViewOf1stSib = $capForView;

			$firstSibEventId = 0;	//新規だからidは未設定をあらわす0
			$firstSibEventKey = '';
		}

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
			$capForView, $this->request->data);

		$mailSettingInfo = $this->getMailSettingInfo();

		//reuqest->data['GroupUser']にある各共有ユーザの情報取得しセット
		$shareUsers = array();
		foreach ($this->request->data['GroupsUser'] as $user) {
			$shareUsers[] = $this->User->getUser($user['user_id'], Current::read('Language.id'));
		}

		//キャンセル時のURLセット
		//testセッション方式
		$url = $this->__getSessionStoredRedirectUrl();
		$this->_vars['returnUrl'] = $url;

		$this->set(compact('capForView', 'mailSettingInfo', 'shareUsers', 'eventSiblings',
			'planViewMode', 'firstSib'));
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

/**
 * view
 *
 * @return void
 */
	public function view() {
		$event = $this->eventData;
		$shareUserInfos = array();
		foreach ($this->shareUsers as $shareUser) {
			$shareUserInfos[] =
				$this->User->getUser(
					$shareUser[$this->ReservationEventShareUser->alias]['share_user'],
					$event[$this->ReservationEvent->alias]['language_id']);
		}

		$createdUserInfo =
			$this->User->getUser($event[$this->ReservationEvent->alias]['created_user'],
				$event[$this->ReservationEvent->alias]['language_id']);

		$isRepeat = $event['ReservationRrule']['rrule'] !== '' ? true : false;

		//testセッション方式
		$url = $this->__getSessionStoredRedirectUrl();
		$this->_vars['returnUrl'] = $url;
		$this->set(compact('shareUserInfos', 'createdUserInfo', 'isRepeat'));
		$this->set('vars', $this->_vars);
		$this->set('event', $this->eventData);
		$this->set('frameId', Current::read('Frame.id'));
		$this->set('languageId', Current::read('Language.id'));
	}

/**
 * getVarsForShow
 *
 * 個別予定表示用のCtp名および予定情報の取得
 *
 * @return void
 * @throws InternalErrorException
 */
	private function __getVarsForShow() {
		$vars = array();
		$this->setReservationCommonVars($vars);

		$eventKey = Hash::get($this->request->params, 'key');
		if ($eventKey) {
			$this->eventData = $this->ReservationEvent->getEventByKey($eventKey);
			$vars['eventId'] = Hash::get($this->eventData, 'ReservationEvent.id');
			$this->shareUsers = $this->ReservationEventShareUser->find('all', array(
				'conditions' => array(
					$this->ReservationEventShareUser->alias . '.reservation_event_id' =>
						$vars['eventId'],
				),
				'recursive' => -1,
				'order' => array($this->ReservationEventShareUser->alias . '.share_user'),
			));
		}
		//表示方法設定情報を取り出し、requestのdataに格納する。
		$this->_frameSetting = $this->ReservationFrameSetting->getFrameSetting();

		//公開対象一覧のoptions配列と自分自身のroom_idとルーム別空間名を取得
		$this->_exposeRoomOptions = $vars['exposeRoomOptions'];
		$this->_myself = null;
		$userId = Current::read('User.id');
		if ($userId) {
			$myRoom = $this->Room->getPrivateRoomByUserId($userId);
			if ($myRoom) {
				$this->_myself = $myRoom['Room']['id'];
			}
		}

		//eメール通知の選択options配列を取得
		$this->_emailOptions = $this->ReservationActionPlan->getNoticeEmailOption();
		return $vars;
	}

/**
 * __getSessionStoredRedirectUrl
 *
 * セッションに保存している戻りURLを取り出す
 *
 * @return mixed
 */
	private function __getSessionStoredRedirectUrl() {
		$frameId = Current::read('Frame.id');
		$sessPath = CakeSession::read('Config.userAgent') . 'reservations.' . $frameId;
		$url = $this->Session->read($sessPath);
		if (! $url) {
			$url = NetCommonsUrl::backToPageUrl();
		}
		return $url;
	}

/**
 * __getCreatedUserWhenUpd
 *
 * 変更時の生成者を勘案・取得する
 *
 * @param string $procMode procMode
 * @param array $originEvent originEvent
 * @param int $planRoomId planRoomId 選択された公開対象となるroomId
 * @param int $myself myself ログイン者のプライベートroomId
 * @return mixed
 */
	private function __getCreatedUserWhenUpd($procMode, $originEvent, $planRoomId, $myself) {
		//reservationの編集は、元予定のcopy＝＞copiedデータのupdate、で実現している。
		//keyが変わらな場合は、これで問題ない。
		//が、keyが変わる場合、＝時間ルールや繰返しルールがかわって、
		//keyの対応が取れない場合、元eventは削除（物理削除or論理削除）し、
		//あらたな繰り返しルールで新keyのeventを生成(save)している。
		//（＝google施設予約がこの考え方で、eventのkeyを変えているアルゴリズム仕様に
		//似せている＋もともとＮＣ２もその考え方を一部導入していた）
		//これにより、編集の時でも、新しいevent群（そしてその子レコード）がつくられるが
		//このときの、created（生成者）を、だれにするかが重要。
		//基本、生成者は現ログインユーザ（編集者）、ではないことに注意。
		//生成者は、元予定のcreated_userさんである！
		//
		//なので、新規saveでありながら、created_userは、元予定のそれ（created_user）
		//を継承する必要がある。(created日付時刻は、saveするその時でいいとおもう）
		//
		//ただし、例外がある。それは、公開予定のルームIDが、元予定の公開予定ルームID
		//にかかわらず、編集者のプライベートルームＩＤ（注！これは編集者により、ひとりひとり
		//違うから、要注意）になった場合は、、created_userは、元予定のそれを継承しては
		//「いけなく」て、編集者自身のuser.idをつかうこと。

		$createdUserWhenUpd = null;	//初期値はnull

		if ($procMode == ReservationsComponent::PLAN_EDIT) {
			$createdUserWhenUpd = $originEvent['ReservationEvent']['created_user'];
			if ($planRoomId == $myself) {
				//例外. この時は、作成者は、元予定生成者ではなく、現ユーザとする。
				$createdUserWhenUpd = Current::read('User.id');
			}
		}
		return $createdUserWhenUpd;
	}

/**
 * 承認者のハンドル名をセットする
 *
 * @param array $locations 施設リスト
 * @return array
 */
	private function __mergeApprovalUserName(array $locations) : array {
		$userIds = array_column($locations, 'approvalUserIds');
		$userIds = array_merge(...$userIds);
		$userIds = array_unique($userIds);
		$result = $this->User->find('all', [
			'conditions' => [
				'id' => $userIds
			],
			'recursive' => -1,
			'fields' => ['id', 'handlename']
		]);
		$nameWithId = [];
		foreach ($result as $user) {
			$nameWithId[$user['User']['id']] = $user['User']['handlename'];
		}
		foreach ($locations as $key => $location) {
			foreach ($location['approvalUserIds'] as $userId) {
				$location['approvalUserNames'][] = $nameWithId[$userId];
			}
			$locations[$key] = $location;
		}

		return $locations;
	}
}
