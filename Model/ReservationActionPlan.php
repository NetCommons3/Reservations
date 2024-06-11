<?php
/**
 * ReservationActionPlan Model
 *
 * @property Block $Block
 * @property Room $Room
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author AllCreator Co., Ltd. <info@allcreator.net>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('ReservationsAppModel', 'Reservations.Model');
App::uses('ReservationsComponent', 'Reservations.Controller/Component');
App::uses('ReservationSupport', 'Reservations.Utility');
App::uses('ReservationService', 'Reservations.Service');
App::uses('ReservationRruleParameter', 'Reservations.Parameter');

/**
 * Reservation Action Plan Model
 *
 * @author AllCreator Co., Ltd. <info@allcreator.net>
 * @package NetCommons\Reservations\Model
 * @SuppressWarnings(PHPMD)
 */
class ReservationActionPlan extends ReservationsAppModel {

/**
 * アクセスユーザが予約可能な施設
 *
 * @var array
 */
	protected $_locations = null;

/**
 * use table
 *
 * このモデルはvalidateと
 * insert/update/deletePlan()呼び出しが主目的なのでテーブルを使用しない。
 * @var array
 */
	public $useTable = false;

/**
 * use behaviors
 *
 * @var array
 */
	public $actsAs = array(
		'NetCommons.OriginalKey',
		'NetCommons.Trackable',
		//FUJI'Workflow.Workflow',
		'Workflow.WorkflowComment',
		'Reservations.ReservationValidate',
		'Reservations.ReservationApp',	//baseビヘイビア
		'Reservations.ReservationInsertPlan', //Insert用
		'Reservations.ReservationUpdatePlan', //Update用
		'Reservations.ReservationDeletePlan', //Delete用
		'Reservations.ReservationExposeRoom', //ルーム表示・選択用
		'Reservations.ReservationPlanOption', //予定CRUD画面の各種選択用
		'Reservations.ReservationPlanTimeValidate',	//予定（時間関連）バリデーション専用
		'Reservations.ReservationPlanRruleValidate',	//予定（Rrule関連）バリデーション専用
		'Reservations.ReservationPlanValidate',	//予定バリデーション専用
		////'Reservations.ReservationRruleHandle',	//concatRrule()など
		'Reservations.ReservationPlanGeneration',	//元予定の新世代予定生成関連
		/*
		// 自動でメールキューの登録, 削除。ワークフロー利用時はWorkflow.Workflowより下に記述する
		'Mails.MailQueue' => array(
			'embedTags' => array(
				'X-SUBJECT' => 'ReservationActionPlan.title',
				'X-LOCATION' => 'ReservationActionPlan.location',
				'X-CONTACT' => 'ReservationActionPlan.contact',
				'X-BODY' => 'ReservationActionPlan.description',
				'X-URL' => array(
					'controller' => 'reservation_plans'
				)
			),
			'workflowType' => 'workflow',
		),
		'Mails.MailQueueDelete',
		*/
		'Reservations.ReservationMail',
		'Reservations.ReservationTopics',
		// 'Reservations.RegistCalendar',
	);
	// @codingStandardsIgnoreStart
	// $_schemaはcakePHP2の予約語だが、宣言するとphpcsが警告を出すので抑止する。
	// ただし、$_schemaの直前にIgnoreStartを入れると、今度はphpdocが直前の
	// property説明がないと警告を出す。よって、この位置にIgnoreStartを挿入した。

/**
 * use _schema
 *
 * @var array
 */
	public $_schema = array (
		// @codingStandardsIgnoreEnd

		// 入力カラムの定義、データ型とdefault値、必要ならlength値
		//繰返し編集の指定(0/1/2). このフィールドは渡ってこない時もあるので
		//ViewにてunlockField指定しておくこと。
		'edit_rrule' => array(
			'type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),

		//施設予約元eventId
		'origin_event_id' => array(
			'type' => 'integer', 'null' => false, 'default' => 0, 'unsigned' => false),
		//施設予約元eventKey
		'origin_event_key' => array(
			'type' => 'string', 'default' => ''),
		//施設予約元eventRecurrence
		'origin_event_recurrence' => array(
			'type' => 'integer', 'null' => false, 'default' => 0, 'unsigned' => false),
		//施設予約元eventException
		'origin_event_exception' => array(
			'type' => 'integer', 'null' => false, 'default' => 0, 'unsigned' => false),

		//施設予約元rruleId
		'origin_rrule_id' => array(
			'type' => 'integer', 'null' => false, 'default' => 0, 'unsigned' => false),
		//施設予約元rruleKey
		'origin_rrule_key' => array(
			'type' => 'string', 'default' => ''),
		//施設予約元rruleを共有する兄弟eventの数
		'origin_num_of_event_siblings' => array(
			'type' => 'integer', 'null' => false, 'default' => 0, 'unsigned' => false),

		// 全変更選択時、繰返し先頭eventのeditボタンを擬似クリックする方式用の項目
		// editLink()を呼ぶときの必要パラメータ
		'first_sib_year' => array(
			'type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),
		'first_sib_month' => array(
			'type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),
		'first_sib_day' => array(
			'type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),
		'first_sib_event_id' => array(
			'type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),

		/*
		// -- 以下のcapForViewOf1stSibによるデータすり替え方式用の項目(first_sib_cap_xxx)は、--
		// -- 全変更選択時、繰返し先頭eventのeditボタンを擬似クリックする方式にかえたので、削除. --

		//先頭兄弟（繰返しの先頭）capForView(表示用ReservationActionPlan)の情報
		'first_sib_cap_enable_time' => array(
			'type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),
		'first_sib_cap_easy_start_date' => array('type' => 'string', 'default' => ''),	//YYYY-MM-DD
		'first_sib_cap_easy_hour_minute_from' => array('type' => 'string', 'default' => ''), //hh:mm
		'first_sib_cap_easy_hour_minute_to' => array(
			'type' => 'string', 'default' => ''),	//hh:mm
		'first_sib_cap_detail_start_datetime' => array(
			'type' => 'string', 'default' => ''),	//YYYY-MM-DD or YYYY-MM-DD hh:mm
		'first_sib_cap_detail_end_datetime' => array(
			'type' => 'string', 'default' => ''), //YYYY-MM-DD or YYYY-MM-DD hh:mm
		'first_sib_cap_timezone' => array('type' => 'string', 'default' => ''),
		*/

		//タイトル
		'title' => array('type' => 'string', 'default' => ''),

		//タイトルアイコン
		//注）タイトルアイコンは、ReservationActionPlanモデルを指定することで、以下の形式で渡ってくる。
		//<input id="PlanTitleIcon" class="ng-scope" type="hidden" value="/net_commons/img/title_icon/10_040_left.svg" name="data[ReservationActionPlan][title_icon]">
		'title_icon' => array('type' => 'string', 'default' => ''),

		//時間の指定(1/0)
		'enable_time' => array(
			'type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),

		////完全なる開始日付時刻と終了日付時刻(hidden)
		////'full_start_datetime' => array('type' => 'string', 'default' => ''),	//hidden
		////'full_end_datetime' => array('type' => 'string', 'default' => ''),	//hidden

		//簡易編集の日付時刻エリア
		'easy_start_date' => array('type' => 'string', 'default' => ''),	//YYYY-MM-DD
		'easy_hour_minute_from' => array('type' => 'string', 'default' => ''), //hh:mm
		'easy_hour_minute_to' => array('type' => 'string', 'default' => ''),	//hh:mm
		//詳細編集の日付時刻エリア
		'detail_start_datetime' => array(
			'type' => 'string', 'default' => ''),	//YYYY-MM-DD or YYYY-MM-DD hh:mm
		'detail_end_datetime' => array(
			'type' => 'string', 'default' => ''), //YYYY-MM-DD or YYYY-MM-DD hh:mm

		//公開対象
		'plan_room_id' => array(
			'type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),
		//注）共有するユーザ群は、ReservationActionPlanモデルではなく、GroupsUserモデルの配列として以下形式で渡ってくる。
		//<input type="hidden" value="2" name="data[GroupsUser][0][user_id]">
		//<input type="hidden" value="3" name="data[GroupsUser][1][user_id]">

		//タイムゾーン
		'timezone' => array('type' => 'string', 'default' => ''),
		'timezone' => array('type' => 'string', 'default' => ''),

		//詳細フラグ(1/0) (hidden. 画面表示時点で、detail(or easy)かはわかるので値を指定しておく。
		'is_detail' => array('type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),

		//場所
		'location' => array('type' => 'string', 'default' => ''),
		//連絡先
		'contact' => array('type' => 'string', 'default' => ''),
		//内容(wysiwyg)
		'description' => array('type' => 'string', 'default' => ''),

		//予定を繰り返す(1/0)
		'is_repeat' => array('type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),

		//繰返し周期 DAILY, WEEKLY, MONTHLY, YEARLY
		'repeat_freq' => array('type' => 'string', 'default' => ''),

		//繰返し間隔 rrule_interval[DAILY], rrule_interval[WEEKLY], rrule_interval[MONTHLY], rrule_interval[YEARLY]
		// rrule_interval[DAILY] inList => array(1, 2, 3, 4, 5, 6)  //n日ごと
		// rrule_interval[WEEKLY] inList => array(1, 2, 3, 4, 5) //n週ごと
		// rrule_interval[MONTHLY] inList => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11) //nヶ月ごと
		// rrule_interval[YEARLY] inList => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12) //n年ごと
		'rrule_interval' => array('type' => 'string', 'default' => ''),

		//週単位or月単位 rrule_byday[WEEKLY], rrule_byday[MONTHLY], rrule_byday[YEARLY]
		// rrule_byday[WEEKLY] inList => array('SU', 'MO', 'TU', 'WE', 'TH', 'FR', 'SA')
		// rrule_byday[MONTHLY] inList => array('', '1SU', '1MO', '1TU', ... , '4FR, '4SA', '-1SU', '-2SU', ..., '-1SA')
		// rrule_byday[YEARLY] inList => array('', '1SU', '1MO', '1TU', ... , '4FR, '4SA', '-1SU', '-2SU', ..., '-1SA')
		'rrule_byday' => array('type' => 'string', 'default' => ''),

		//月単位 rrule_bymonthday[MONTHLY]
		// rrule_bymonthday[MONTHLY] inList => array('', 1, 2, ..., 31 );
		'rrule_bymonthday' => array('type' => 'string', 'default' => ''),

		//年単位 rrule_bymonth[YEARLY]
		// rrule_bymonth[YEARLY] inList => array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12) //n月
		'rrule_bymonth' => array('type' => 'string', 'default' => ''),

		//繰返しの終了指定
		// rrule_term inList('COUNT', 'UNTIL')
		'rrule_term' => array('type' => 'string', 'default' => ''),

		//繰返し回数
		'rrule_count' => array('type' => 'string', 'default' => ''),

		//繰返し終了日
		'rrule_until' => array('type' => 'string', 'default' => ''),

		//メールで通知(1/0)
		'enable_email' => array(
			'type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),

		//メール通知タイミング
		'email_send_timing' => array(
			'type' => 'integer', 'null' => false, 'default' => '0', 'unsigned' => false),

		//承認ステータス
		//statusは 施設予約独自stauts取得関数getStatusで取ってくるので、ここからは外す。
		//'status' => array('type' => 'integer', 'null' => false, 'unsigned' => false),

	);

/**
 * Validation rules
 *
 * @var array
 */
	public $validate = array(
	);

/**
 * Constructor. Binds the model's database table to the object.
 *
 * @param bool|int|string|array $id Set this ID for this model on startup,
 * can also be an array of options, see above.
 * @param string $table Name of database table to use.
 * @param string $ds DataSource connection name.
 * @see Model::__construct()
 * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
 */
	public function __construct($id = false, $table = null, $ds = null) {
		parent::__construct($id, $table, $ds);
		$this->loadModels([
			'Frame' => 'Frames.Frame',
			'Reservation' => 'Reservations.Reservation',
		]);
	}

/**
 * _doMergeDisplayParamValidate
 *
 * 画面パラメータ関連バリデーションのマージ
 *
 * @param bool $isDetailEdit 詳細画面かどうか true=詳細(detail)画面, false=簡易(easy)画面
 * @return void
 */
	// 未使用
	//protected function _doMergeDisplayParamValidate($isDetailEdit) {
	//	$this->validate = ValidateMerge::merge($this->validate, array(
	//		'return_style' => array(
	//			'rule1' => array(
	//				'rule' => array('inList', array(
	//					ReservationsComponent::CALENDAR_STYLE_SMALL_MONTHLY,
	//					ReservationsComponent::CALENDAR_STYLE_LARGE_MONTHLY,
	//					ReservationsComponent::CALENDAR_STYLE_WEEKLY,
	//					ReservationsComponent::CALENDAR_STYLE_DAILY,
	//					ReservationsComponent::CALENDAR_STYLE_SCHEDULE,
	//				)),
	//				'required' => false,
	//				'allowEmpty' => true,
	//				'message' => __d('reservations', '戻り先のスタイル指定が不正です。'),
	//			),
	//		),
	//		'return_sort' => array(
	//			'rule1' => array(
	//				'rule' => array('inList', array(
	//					ReservationsComponent::CALENDAR_SCHEDULE_SORT_TIME,
	//					ReservationsComponent::CALENDAR_SCHEDULE_SORT_MEMBER,
	//				)),
	//				'required' => false,	//sort指定はスケジュールの時だけ
	//				'allowEmpty' => true,
	//				'message' => __d('reservations', '戻り先のソート指定が不正です。'),
	//			),
	//		),
	//		'return_tab' => array(
	//			'rule1' => array(
	//				'rule' => array('inList', array(
	//					ReservationsComponent::CALENDAR_DAILY_TAB_LIST,
	//					ReservationsComponent::CALENDAR_DAILY_TAB_TIMELINE,
	//				)),
	//				'required' => false,	//tab指定は単一日の時だけ
	//				'allowEmpty' => true,
	//				'message' => __d('reservations', '戻り先のタブ指定が不正です。'),
	//			),
	//		),
	//	));
	//}

/**
 * _doMergeRruleValidate
 *
 * 繰返し関連バリデーションのマージ
 *
 * @param bool $isDetailEdit 詳細画面かどうか true=詳細(detail)画面, false=簡易(easy)画面
 * @return void
 */
	protected function _doMergeRruleValidate($isDetailEdit) {
		$this->validate = ValidateMerge::merge($this->validate, array(
			'edit_rrule' => array(
				'rule1' => array(
					'rule' => array('inList', array(0, 1, 2)),
					'required' => false,
					'message' => __d('reservations', 'Invalid input. (change of repetition)'),
				),
			),
			'is_repeat' => array(
				'rule1' => array(
					'rule' => array('inList', array(0, 1)),
					'required' => false,
					'message' => __d('reservations', 'Invalid input. (repetition)'),
				),
			),
			'repeat_freq' => array(
				'rule1' => array(
					'rule' => array('checkRrule'),
					'required' => false,
					'message' => ReservationsComponent::CALENDAR_RRULE_ERROR_HAPPEND,
				),
			),
		));
	}

/**
 * _doMergeDatetimeValidate
 *
 * 日付時刻関連バリデーションのマージ
 *
 * @param bool $isDetailEdit 詳細画面かどうか true=詳細(detail)画面, false=簡易(easy)画面
 * @return void
 */
	protected function _doMergeDatetimeValidate($isDetailEdit) {
		$this->validate = ValidateMerge::merge($this->validate, array(
			'enable_time' => array(
				'rule1' => array(
					'rule' => array('inList', array(0, 1)),
					'required' => false,
					'message' => __d('reservations', 'Invalid input. (time)'),
				),
			),
			'easy_start_date' => array(
				'rule1' => array(
					'rule' => array('date', 'ymd'),	//YYYY-MM-DD
					'required' => !$isDetailEdit,
					'allowEmpty' => $isDetailEdit,
					'message' => __d('reservations', 'Invalid input. (year/month/day)'),
				),
			),
			'easy_hour_minute_from' => array(
				'rule1' => array(
					'rule' => array('datetime'), //YYYY-MM-DD hh:mm
					'required' => false,
					'allowEmpty' => true,
					'message' => __d('reservations', 'Invalid input. (start time)(easy edit mode)'),
				),
				'rule2' => array(
					'rule' => array('checkReverseStartEndTime', 'easy'), //YYYY-MM-DD hh:mm
					'message' => __d('reservations', 'Invalid input. (start time and end time)(easy edit mode)'),
				),
			),
			'easy_hour_minute_to' => array(
				'rule1' => array(
					'rule' => array('datetime'), //YYYY-MM-DD hh:mm
					'required' => false,
					'allowEmpty' => true,
					'message' => __d('reservations', 'Invalid input. (end time)'),
				),
			),
			'detail_start_datetime' => array(
				'rule1' => array(
					'rule' => array('customDatetime', 'detail'), //YYYY-MM-DD or YYYY-MM-DD hh:mm
					'message' => __d('reservations', 'Invalid input. (start time)'),
				),
				'rule2' => array(
					'rule' => array('checkReverseStartEndDateTime', 'detail'),
					'message' => __d('reservations', 'Invalid input. (start day (time) and end day (time))'),
				),
				'rule3' => array(
					'rule' => array('validteNotExistReservation'),
					'message' =>
						__d('reservations', 'It has been alreay reserved by someone else.Try different time and date.'),
					// NC2では予約の入ってる日付を表示してた（繰り返し用だが、単発予約でも表示）
				),
				'rule4' => array(
					'rule' => array('validteUseLocationTimeRange'),
					'message' =>
						__d('reservations',
							'Invalid reservation time range.'),
				),
			),
			'detail_end_datetime' => array(
				'rule1' => array(
					'rule' => array('customDatetime', 'detail'), //YYYY-MM-DD or YYYY-MM-DD hh:mm
					'message' => __d('reservations', 'Invalid input. (end date)'),
				),
			),

		));
	}

/**
 * 施設利用時間内の予約になっているか
 *
 * @param array $check チェック対象
 * @return bool
 */
	public function validteUseLocationTimeRange($check) {
		$locationKey = $this->data[$this->alias]['location_key'];
		$startDateTime = $this->data[$this->alias]['detail_start_datetime'] . ':00';
		$endDateTime = $this->data[$this->alias]['detail_end_datetime'] . ':00';

		$rruleParameter = new ReservationRruleParameter();
		$rruleParameter->setData($this->data);
		$rrule = $rruleParameter->getRrule();

		// 施設情報を取得
		$this->loadModels(
			[
				'ReservationLocation' => 'Reservations.ReservationLocation'
			]
		);
		$location = $this->ReservationLocation->findByKeyAndLanguageId(
			$locationKey,
			Current::read('Language.id')
		);
		if (! $location) {
			return false;
		}
		$reservableTimeTable = explode('|', $location['ReservationLocation']['time_table']);
		$locationTimeZone = new DateTimeZone($location['ReservationLocation']['timezone']);

		$startDate = date('Y-m-d', strtotime($startDateTime));
		$startTime = date('H:i:s', strtotime($startDateTime));
		$timeLength = strtotime($endDateTime) - strtotime($startDateTime); // 予約の時間幅
		// 繰り返し予約なら繰り返し日付を生成
		if ($rrule) {
			//繰り返しの日付リストを生成
			$repeatService = new ReservationRepeatService();

			$repeatDateSet = $repeatService->getRepeatDateSet($rrule, $startDate);

		} else {
			// 繰り返しで無ければ1日だけ配列にいれる
			$repeatDateSet = [$startDate];
		}
		// 繰り返し日ごとに予約可能日時かチェック
		$ngDates = [];
		foreach ($repeatDateSet as $checkDate) {
			// 繰り返し生成日付＋時刻でチェックする開始日時、終了日時を生成
			$checkStartDateTime = $checkDate . ' ' . $startTime;
			$checkEndDateTime = date('Y-m-d H:i:s', strtotime($checkStartDateTime) + $timeLength);

			if (!$this->_validateLocationTimeRange(
				$checkStartDateTime,
				$checkEndDateTime,
				$location,
				$locationTimeZone,
				$reservableTimeTable
			)
			) {
				// 予約NG日があったらNG日リストに追加
				$ngDates[] = $checkDate;
			}
		}
		if ($ngDates) {
			// ERROR
			$ret = __d(
				'reservations',
				'Invalid reservation time range.'
			);
			$ret .= implode('<br />', $ngDates);
			return $ret;
		} else {
			return true;
		}
	}

/**
 * 重複予約のチェック
 *
 * @param array $check チェック対象
 * @return bool
 */
	public function validteNotExistReservation($check) {
		$startDateTime = $this->data[$this->alias]['detail_start_datetime'];
		$endDateTime = $this->data[$this->alias]['detail_end_datetime'];
		// This timezone offset is id.
		$inputTimeZone = $this->data[$this->alias]['timezone'];
		$locationKey = $this->data[$this->alias]['location_key'];

		$rruleParameter = new ReservationRruleParameter();
		$rruleParameter->setData($this->data);
		$rrule = $rruleParameter->getRrule();

		// 繰り返しでないか、設定した全ての予定の変更時は$rruleIdを渡す（この繰り返し予約は重複チェック対象外になるので）
		$ignoreConditions = [];
		if (Hash::get($this->data, 'ReservationActionPlan.origin_event_id')) {
			if (empty($rrule)) {
				// 繰り返しでないなら、keyが同じ予約は編集元レコードなので重複チェック時は無視
				$ignoreConditions = [
					'ReservationEvent.key != ' => Hash::get($this->data, 'ReservationActionPlan.origin_event_key')
				];

			} else {
				switch (Hash::get($this->data, 'ReservationActionPlan.edit_rrule')){
					case 0:
						// 一つの予約だけ更新
						$ignoreConditions = [
							'ReservationEvent.key != ' =>
								Hash::get($this->data, 'ReservationActionPlan.origin_event_key')
						];
						// ひとつだけの変更なので重複チェックでは繰り返しさせない
						$rrule = [];
						break;
					case 1:
						// 以降の予約を更新
						$this->loadModels(['ReservationEvent' => 'Reservations.ReservationEvent']);
						$origin = $this->ReservationEvent->findById(
							Hash::get($this->data, 'ReservationActionPlan.origin_event_id'));
						$ignoreConditions = [
							'NOT' => [
								'ReservationEvent.reservation_rrule_id' => Hash::get($this->data,
									'ReservationActionPlan.origin_rrule_id'),
								'ReservationEvent.recurrence_event_id !=' => 0,
								'ReservationEvent.exception_event_id !=' => 0,
							],
							'ReservationEvent.dtstart > ' => $origin['ReservationEvent']['dtstart']
						];
						break;
					case 2:
						// 全ての予約を更新
						$ignoreConditions = [
							'NOT' => [
								'ReservationEvent.reservation_rrule_id' =>
									Hash::get($this->data, 'ReservationActionPlan.origin_rrule_id'),
								'ReservationEvent.recurrence_event_id !=' => 0,
								'ReservationEvent.exception_event_id !=' => 0,
							]

						];
						break;
				}
			}
		}

		$reservationService = new ReservationService();
		$result = $reservationService->getOverlapReservationDate(
			$locationKey,
			$startDateTime,
			$endDateTime,
			$inputTimeZone,
			$rrule,
			$ignoreConditions
		);
		if (count($result) > 0) {
			$ret = __d(
				'reservations',
				'It has been alreay reserved by someone else.Try different time and date.'
			);
			foreach ($result as $date) {
				$ret .= "<br />" . $date;
			}
			return $ret;
		} else {
			return true;
		}
	}

/**
 * _doMergeTitleValidate
 *
 * タイトル関連バリデーションのマージ
 *
 * @param bool $isDetailEdit 詳細画面かどうか true=詳細(detail)画面, false=簡易(easy)画面
 * @return void
 */
	protected function _doMergeTitleValidate($isDetailEdit) {
		$this->validate = ValidateMerge::merge($this->validate, array(
			'title' => array(
				'rule1' => array(
					'rule' => array('notBlank'),
					'required' => true,
					'message' => __d('reservations', 'Invalid input. (plan title)'),
				),
				'rule2' => array(
					'rule' => array('maxLength', ReservationsComponent::CALENDAR_VALIDATOR_TITLE_LEN),
					'message' => sprintf(__d('reservations',
						'%d character limited. (plan title)'), ReservationsComponent::CALENDAR_VALIDATOR_TITLE_LEN),
				),
			),
			'title_icon' => array(
				'rule2' => array(
					'rule' => array('maxLength', ReservationsComponent::CALENDAR_VALIDATOR_GENERAL_VCHAR_LEN),
					'required' => false,
					'allowEmpty' => true,
					'message' => sprintf(__d('reservations',
						'%d character limited. (title icon)'),
						ReservationsComponent::CALENDAR_VALIDATOR_GENERAL_VCHAR_LEN),
				),
			),
		));
	}

/**
 * Called during validation operations, before validation. Please note that custom
 * validation rules can be defined in $validate.
 *
 * @param array $options Options passed from Model::save().
 * @return bool True if validate operation should continue, false to abort
 * @link http://book.cakephp.org/2.0/en/models/callback-methods.html#beforevalidate
 * @see Model::save()
 */
	public function beforeValidate($options = array()) {
		$isDetailEdit = (isset($this->data['ReservationActionPlan']['is_detail']) &&
			$this->data['ReservationActionPlan']['is_detail']) ? true : false;
		//$this->_doMergeDisplayParamValidate($isDetailEdit);	//画面パラメータ関連validation
		$this->_doMergeTitleValidate($isDetailEdit);	//タイトル関連validation
		$this->_doMergeDatetimeValidate($isDetailEdit);	//日付時刻関連validation
		$this->validate = ValidateMerge::merge($this->validate, array(	//コンテンツ関連validation
			'status' => [
				'rule1' => [
					'rule' => ['validateStatus'],
					'message' => __d('net_commons', 'Invalid request.'),
				]
			],
			'plan_room_id' => array(
				'rule1' => array(
					'rule' => array('allowedRoomId'),
					'required' => true,
					'allowEmpty' => false,
					'message' => __d('reservations', 'Invalid input. (authority)'),
				),
			),
			'location_key' => [
				'rule1' => [
					'rule' => ['allowedLocationKey'],
					'required' => true,
					'allowEmpty' => false,
					'message' => __d('reservations', 'Invalid input location')
				]
			],
			//'plan_room_id' => array(
			//	'rule1' => array(
			//		'rule' => array('allowedRoomId'),
			//		'required' => true,
			//		'allowEmpty' => false,
			//		'message' => __d('reservations', 'Invalid input. (authority)'),
			//	),
			//),
			// This timezone offset is id.
			'timezone' => array(
				'rule1' => array(
					'rule' => array('allowedTimezoneOffset'),
					'required' => false,
					'message' => __d('reservations', 'Invalid input. (timezone)'),
				),
			),
			'is_detail' => array(
				'rule1' => array(
					'rule' => array('inList', array(0, 1)),
					'required' => false,
					'message' => __d('reservations', 'Invalid input. (detail)'),
				),
			),
			'location' => array(
				'rule1' => array(
					'rule' => array('maxLength', ReservationsComponent::CALENDAR_VALIDATOR_TITLE_LEN),
					'required' => false,
					'message' => sprintf(__d('reservations',
						'%d character limited. (location)'), ReservationsComponent::CALENDAR_VALIDATOR_TITLE_LEN),
				),
			),
			'contact' => array(
				'rule1' => array(
					'rule' => array('maxLength', ReservationsComponent::CALENDAR_VALIDATOR_TITLE_LEN),
					'required' => false,
					'message' => sprintf(__d('reservations', '%d character limited. (contact)'),
						ReservationsComponent::CALENDAR_VALIDATOR_TITLE_LEN),
				),
			),
			'description' => array(
				'rule1' => array(
					'rule' => array('maxLength', ReservationsComponent::CALENDAR_VALIDATOR_TEXTAREA_LEN),
					'required' => false,
					//'message' => sprintf(__d('reservations', '連絡先は最大 %d 文字です。'),
					'message' => sprintf(__d('reservations', '%d character limited. (detail)'),
						ReservationsComponent::CALENDAR_VALIDATOR_TEXTAREA_LEN),
				),
			),

			//statusの値は 施設予約独自status取得関数getStatusで取ってくるので省略
		));
		$this->_doMergeRruleValidate($isDetailEdit);	//繰返し関連validation

		return parent::beforeValidate($options);
	}

/**
 * statusのチェック
 *
 * @param array $check checkする値
 * @return bool
 */
	public function validateStatus($check) {
		$statusesForEditor = array(
			WorkflowComponent::STATUS_APPROVAL_WAITING,
			WorkflowComponent::STATUS_IN_DRAFT
		);
		$statusesForPublisher = array(
			WorkflowComponent::STATUS_PUBLISHED,
			WorkflowComponent::STATUS_IN_DRAFT,
			WorkflowComponent::STATUS_DISAPPROVED
		);

		$locationKey = $this->data['ReservationActionPlan']['location_key'];
		/** @var ReservationLocation $locationModel */
		$locationModel = ClassRegistry::init('Reservations.ReservationLocation');
		$location = $locationModel->getByKey($locationKey);
		if (! $location) {
			return false;
		}
		if ($location['ReservationLocation']['use_workflow']) {
			// 承認必要
			// 承認者か
			if (in_array(Current::read('User.id'), $location['approvalUserIds'])) {
				//承認者
				$allowList = $statusesForPublisher;
			} else {
				//承認権限無し
				$allowList = $statusesForEditor;
			}
		} else {
			// 承認不要
			$allowList = $statusesForPublisher;
		}

		$stauts = $check['status'];
		return in_array($stauts, $allowList);
	}

/**
 * 予約可能な施設を返す
 * 何度も呼び出すことを考慮して内部キャッシュ
 * ε(　　　　 v ﾟωﾟ)　＜ReservationLocation内でキャッシュすればOKなのでは?
 *
 * @return array
 */
	protected function _getLocations() {
		if (is_null($this->_locations)) {
			$this->loadModels(
				[
					'ReservationLocation' => 'Reservations.ReservationLocation',
				]
			);

			$userId = Hash::get($this->data,
				'ReservationActionPlan.origin_created_user',
				Current::read('User.id'));
			$this->_locations = $this->ReservationLocation->getReservableLocations(null, $userId);
		}
		return $this->_locations;
	}

/**
 * 選択した施設が予約可能な施設かチェックする
 *
 * @param array $check 入力値 location_key
 * @return bool
 */
	public function allowedLocationKey($check) {
		//
		$locationKey = $check['location_key'];

		$locations = $this->_getLocations();
		$locationKeys = Hash::combine($locations, '{n}.ReservationLocation.key', '{n}.ReservableRoom');
		return array_key_exists($locationKey, $locationKeys);
	}

/**
 * allowedRoomId
 *
 * 許可されたルームIDかどうか
 * 予約しようとするユーザのロール、予約する施設により予約可能なルームはことなる。
 *
 * @param array $check 入力配列（room_id）
 * @return bool 成功時true, 失敗時false
 */
	public function allowedRoomId($check) {
		$roomId = $check['plan_room_id'];
		if ($roomId == 0) {
			// 公開先指定無し
			return true;
		}
		$this->loadModels([
			'ReservationLocationsRoom' => 'Reservations.ReservationLocationsRoom',
		]);
		$locationKey = $this->data[$this->alias]['location_key'];
		$userId = Current::read('User.id');
		$rooms = $this->ReservationLocationsRoom->getReservableRoomsByLocationKey($locationKey, $userId);
		$reservableRoomIds = array_column(array_column($rooms, 'Room'), 'id');
		return in_array($roomId, $reservableRoomIds);
	}

/**
 * saveReservationPlan
 *
 * 予定データ登録
 *
 * @param array $data POSTされたデータ
 * @param string $procMode procMode
 * @param bool $isOriginRepeat isOriginRepeat
 * @param bool $isTimeMod isTimeMod
 * @param bool $isRepeatMod isRepeatMod
 * @param int $createdUserWhenUpd createdUserWhenUpd
 * @param bool $isMyPrivateRoom isMyPrivateRoom
 * @return bool 成功時true, 失敗時false
 * @throws InternalErrorException
 */
	public function saveReservationPlan($data, $procMode,
		$isOriginRepeat, $isTimeMod, $isRepeatMod, $createdUserWhenUpd, $isMyPrivateRoom) {
		// 設定画面を表示する前にこのルームのアンケートブロックがあるか確認
		// 万が一、まだ存在しない場合には作成しておく
		$this->Reservation->afterFrameSave(Current::read());

		$this->begin();
		$eventId = 0;
		$this->aditionalData = $data['WorkflowComment'];

		try {
			//備忘）
			//選択したTZを考慮したUTCへの変換は、この
			//convertToPlanParamFormat()の中でcallしている、
			//_setAndMergeDateTime()がさらにcallしている、
			//_setAndMergeDateTimeDetail()で行っています。
			//
			$planParam = $this->convertToPlanParamFormat($data);

			//CakeLog::debug("DBG: request_data[" . print_r($data, true) . "]");

			//call元の_reservationPost()の最初でgetStatus($data)の結果が
			//$data['ReservationActionPlan']['status']に代入されているので
			//ここは、その値を引っ張ってくるだけに直す。
			////$status = $this->getStatus($data);
			$status = $data['ReservationActionPlan']['status'];

			//if ($status === false) { getStatus内でInternalErrorExceptionしている
			//	CakeLog::error("save_Nより、statusが決定できませんでした。data[" .
			//		serialize($data) . "]");
			//	throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			//}
			if ($procMode === ReservationsComponent::PLAN_ADD) {
				//新規追加処理
				//CakeLog::debug("DBG: PLAN_ADD case.");

				//$this->insertPlan($planParam);
				$eventId = $this->insertPlan($planParam, $isMyPrivateRoom);
				//$this->updateCalendar($planParam);
			} else {	//PLAN_EDIT
				//変更処理
				//CakeLog::debug("DBG: PLAN_MODIFY case.");

				//現予定を元に、新世代予定を作成する
				//1. statusは、cal用新statusである。
				//2. createdUserWhenUpdは、変更後の公開ルームidが「元予定生成者の＊ルーム」から「編集者・承認者
				//(＝ログイン者）のプライベート」に変化していた場合、created_userを元予定生成者から編集者・承認者
				//(＝ログイン者）に変更する例外処理用。
				//3. isMyPrivateRoomは、変更後の公開ルームidが「編集者・承認者（＝ログイン者）のプライベート」以外の場合、
				//仲間の予定はプライベートの時のみ許される子情報なので、これらはcopy対象から外す（stripする）例外処理用。
				//
				$editRrule = $this->getEditRruleForUpdate($data);
				$newPlan = $this->makeNewGenPlan(
					$data,
					$status,
					$createdUserWhenUpd,
					$isMyPrivateRoom,
					$editRrule
				);

				$isInfoArray = array($isOriginRepeat, $isTimeMod, $isRepeatMod, $isMyPrivateRoom);
				$eventId = $this->updatePlan($planParam, $newPlan, $status, $isInfoArray, $editRrule,
					$createdUserWhenUpd);
			}

			if ($this->isOverMaxRruleIndex) {
				CakeLog::info("save(ReservationPlanの内部で施設予約のrruleIndex回数超過が" .
				"発生している。強制rollbackし、画面にINDEXオーバーであることを" .
				"出す流れに乗せ、例外は投げないようにする。");
				$this->rollback();
				return false;
			}

			// メールやらなんやらが動作する前にはブロックをちゃんと用意しておかねばならない
			$this->Reservation->prepareBlock(
				$data['ReservationActionPlan']['plan_room_id'],
				Current::read('Language.id'),
				'reservations');

			// 承認メール、公開通知メールの送信
			$this->sendWorkflowAndNoticeMail($eventId, $isMyPrivateRoom);

			$this->saveReservationTopics($eventId);

			$this->_enqueueEmail($data);

			$this->commit();

		} catch (Exception $ex) {

			$this->rollback($ex);

			return false;
		}
		return $eventId;
	}

/**
 * saveReservationPlan
 *
 * 予定データ登録
 *
 * @param array $data POSTされたデータ
 * @param bool $isMyPrivateRoom isMyPrivateRoom
 * @return bool 成功時true, 失敗時false
 * @throws InternalErrorException
 */
	public function saveImportRecord($data, $isMyPrivateRoom) {
		$eventId = 0;
		//$this->aditionalData = $data['WorkflowComment'];

		try {
			//備忘）
			//選択したTZを考慮したUTCへの変換は、この
			//convertToPlanParamFormat()の中でcallしている、
			//_setAndMergeDateTime()がさらにcallしている、
			//_setAndMergeDateTimeDetail()で行っています。
			//
			$planParam = $this->convertToPlanParamFormat($data);

			//CakeLog::debug("DBG: request_data[" . print_r($data, true) . "]");

			//call元の_reservationPost()の最初でgetStatus($data)の結果が
			//$data['ReservationActionPlan']['status']に代入されているので
			//ここは、その値を引っ張ってくるだけに直す。
			////$status = $this->getStatus($data);
			$status = $data['ReservationActionPlan']['status'];

			$eventId = $this->insertPlan($planParam, $isMyPrivateRoom);

			if ($this->isOverMaxRruleIndex) {
				CakeLog::info("save(ReservationPlanの内部で施設予約のrruleIndex回数超過が" .
					"発生している。強制rollbackし、画面にINDEXオーバーであることを" .
					"出す流れに乗せ、例外は投げないようにする。");
				$this->rollback();
				return false;
			}

			// メールやらなんやらが動作する前にはブロックをちゃんと用意しておかねばならない
			$this->Reservation->prepareBlock(
				$data['ReservationActionPlan']['plan_room_id'],
				Current::read('Language.id'),
				'reservations');

			// 承認メール、公開通知メールの送信
			$this->sendWorkflowAndNoticeMail($eventId, $isMyPrivateRoom);

			$this->saveReservationTopics($eventId);

			$this->_enqueueEmail($data);

			//$this->commit();

		} catch (Exception $ex) {

			return false;
		}
		return $eventId;
	}

/**
 * convertToPlanParamFormat
 *
 * 予定データ登録
 *
 * @param array $data POSTされたデータ
 * @return mixed 成功時$planParamデータ
 * @throws InternalErrorException
 */
	public function convertToPlanParamFormat($data) {
		$planParam = array();

		try {
			$model = ClassRegistry::init('Reservations.Reservation');
			if (!($reservation = $model->findByBlockKey($data['Block']['key']))) {
				throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
			}
			$planParam['reservation_id'] = $reservation[$model->alias]['id'];

			////statusは、上流の_reservationPost()直後で施設予約独自status取得・代入
			////が実行され、$data['ReservationAtionPlan']['status']にセットされているので
			////単純なcopyの方に移動させた。
			////$planParam['status'] = $this->getStatus($data);
			$planParam['language_id'] = Current::read('Language.id');
			$planParam['room_id'] = $data[$this->alias]['plan_room_id'];
			//このtimezoneは数値(ここで変換されてる)
			//$planParam['timezone'] = $this->_getTimeZoneOffsetNum(
			//	$data[$this->alias]['timezone']);
			$planParam['timezone'] = $data[$this->alias]['timezone'];
			$planParam['timezone'] = $data[$this->alias]['timezone'];
			$planParam = $this->_setAndMergeDateTime($planParam, $data);
			$planParam = $this->_setAndMergeRrule($planParam, $data);

			$shareUsers = Hash::extract($data, 'GroupsUser.{n}.user_id');
			$myUserId = Current::read('User.id');
			$newShareUsers = array();
			foreach ($shareUsers as $user) {
				if ($user == $myUserId) {
					CakeLog::info('予定を共有する人に自分自身 user_id[' .
						$user . ']がいます。自分自身は除外します。');
					continue;
				}
				$newShareUsers[] = $user;
			}
			$planParam['share_users'] = $newShareUsers;

			//単純なcopyでＯＫな項目群
			$fields = array(
				'title', 'title_icon',		//FIXME: insert/update側に追加実装しないといけない項目
				'location', 'contact', 'description',
				'enable_email', 'email_send_timing', 'status',
				'location_key',
			);
			foreach ($fields as $field) {
				$planParam[$field] = $data[$this->alias][$field];
			}

			//他の機構で渡さないといけないデータはここでセットすること
			//
			$planParam[ReservationsComponent::ADDITIONAL] = array();
			//ワークフロー用
			if (isset($data['WorkflowComment'])) {
				//ワークフローコメントをセットする。
				$planParam[ReservationsComponent::ADDITIONAL]['WorkflowComment'] = $data['WorkflowComment'];
				//ワークフローコメントがsave時Block.keyも一緒に必要としてるので、セットする。
				$planParam[ReservationsComponent::ADDITIONAL]['Block'] = array();
				$planParam[ReservationsComponent::ADDITIONAL]['Block']['key'] = Current::read('Block.key');
			}

		} catch(Exception $ex) {
			//パラメータ変換のどこかでエラーが発生
			CakeLog::error($ex->getMessage());
			throw($ex);	//再throw
		}
		return $planParam;
	}

/**
 * getStatus
 *
 * WorkflowStatus値の取り出し
 *
 * @param array $data POSTされたデータ
 * @return string 成功時 $status, 失敗時 例外をthrowする。
 */
	public function getStatus($data) {
		return $this->_getStatus($data);
	}

/**
 * enqueueEmail
 *
 * メール通知がonの場合、通知時刻等を指定したデータをMailキューに登録する。
 *
 * @param array $data POSTされたデータ
 * @return void 失敗時 例外をthrowする.
 * @throws InternalErrorException
 */
	protected function _enqueueEmail($data) {
		//if ($data[$this->alias]['enable_email']) {
		//	$[email_send_timing] => 60
		//	FIXME: email_send_timingの値をつかって、Mailキューに登録する。
		//}
	}

/**
 * proofreadValidationErrors
 *
 * validationErrors配列の内、対象項目とmessageを動的に校正する。(主にrruleの複合validate対応)
 *
 * @param Model &$model モデル
 * @return array
 */
	public function proofreadValidationErrors(&$model) {
		$msg = Hash::get($model->validationErrors, 'repeat_freq.0');
		if ($msg === ReservationsComponent::CALENDAR_RRULE_ERROR_HAPPEND) {
			unset($model->validationErrors['repeat_freq']);
			//CakeLog::debug("DBG: proofread count[" . count($model->reservationProofreadValidationErrors) . "]");
			if (count($model->reservationProofreadValidationErrors) > 0) {
				$model->validationErrors = Hash::merge($model->validationErrors,
					$model->reservationProofreadValidationErrors);
			}
		}
	}

/**
 * getProcModeOriginRepeatAndModType
 *
 * 追加・変更、元データ繰返し有無、及び時間・繰返し系変更タイプの判断処理
 *
 * @param array $data $this->request->data配列が渡される
 * @param array $originEvent 変更元のevent関連データ
 * @return array 処理モード、元データ繰返し有無、時間系変更有無、繰返し系変更有無を配列で返す。
 */
	public function getProcModeOriginRepeatAndModType($data, $originEvent) {
		$cap = $data['ReservationActionPlan'];

		////////////////////////////////
		//追加処理か変更処理かの判断
		//
		$procMode = ReservationsComponent::PLAN_ADD;
		if (!empty($cap['origin_event_id'])) {
			$procMode = ReservationsComponent::PLAN_EDIT;
		}

		////////////////////////////////
		//元データが繰返しタイプかどうかの判断
		$isOriginRepeat = false;
		if (isset($cap['origin_num_of_event_siblings']) &&
			$cap['origin_num_of_event_siblings'] > 1) {
			$isOriginRepeat = true;
		}

		////////////////////////////////
		//変更内容が、時間系の変更を含むかどうかの判断
		//（Google施設予約の考え方の導入）
		//
		$timeModCnt = 0;
		if (!empty($originEvent)) {
			//１）タイムゾーンの比較
			$this->__compareTz($cap, $originEvent, $timeModCnt);

			//２）日付時刻の比較
			//入力されたユーザ日付（時刻）を、選択TZを考慮し、サーバ系日付時刻に直してから比較する。
			$this->__compareDatetime($cap, $originEvent, $timeModCnt);
		}
		$isTimeMod = false;
		if ($timeModCnt) {	//1箇所以上変化があればtrueにする。
			$isTimeMod = true;
		}

		////////////////////////////////
		//変更内容が、繰返し系の変更を含むかどうかの判断
		//（Google施設予約の考え方の導入）
		//
		$repeatModCnt = 0;
		if (!empty($originEvent)) {
			//１）繰返しの比較
			$cru = new ReservationRruleUtil();

			//POSTされたデータよりrrule配列を生成する。
			$workParam = array();
			$workParam = $this->_setAndMergeRrule($workParam, $data);
			$capRrule = $cru->parseRrule($workParam['rrule']);

			//eventの親rruleモデルよりrruleを取り出し配列化する。
			$originRrule = $cru->parseRrule($originEvent['ReservationRrule']['rrule']);

			//CakeLog::debug("DBG: capRrule[" . serialize($capRrule) .
			//	"] VS originRrule[" . serialize($originRrule) . "]");
			$diff1 = $this->__arrayRecursiveDiff($capRrule, $originRrule);
			$diff2 = $this->__arrayRecursiveDiff($originRrule, $capRrule);
			if (empty($diff1) && empty($diff2)) {
				//a集合=>b集合の差集合、b集合=>a集合の差集合、ともに空なので
				//集合要素に差はない、と判断する。
			} else {
				//差がみつかったので、繰返しに変更あり。
				//CakeLog::debug("DBG: 差がみつかったので、繰返しに変更あり。");
				++$repeatModCnt;
				//CakeLog::debug("DBG 繰返しに変化あり! capRrule[" . serialize($capRrule) .
				//"] VS originRrule[" . serialize($originRrule) . "]");
			}
		}
		$isRepeatMod = false;
		if ($repeatModCnt) {	//1箇所以上変化があればtrueにする。
			$isRepeatMod = true;
		}

		return array($procMode, $isOriginRepeat, $isTimeMod, $isRepeatMod);
	}

/**
 * __compareTz
 *
 * タイムゾーンの比較
 *
 * @param array $cap $data['ReservationActionPlan']情報
 * @param array $originEvent 元イベント関連情報
 * @param int &$timeRepeatModCnt 変更数。タイムゾーンが変更されいていたら１加算する。
 * @return void
 */
	private function __compareTz($cap, $originEvent, &$timeRepeatModCnt) {
		//$tzTbl = ReservationsComponent::getTzTbl();
		//$originTzId = '';
		//foreach ($tzTbl as $tzInfo) {
		//	//dobule と stringで、型が違うので == で比較すること
		//	if ($tzInfo[ReservationsComponent::CALENDAR_timezone_VAL] ==
		//	$originEvent['ReservationEvent']['timezone']) {
		//		$originTzId = $tzInfo[ReservationsComponent::CALENDAR_TIMEZONE_ID];
		//		break;
		//	}
		//}
		$originTzId = $originEvent['ReservationEvent']['timezone'];
		if ($originTzId != $cap['timezone']) {
			//選択したＴＺが変更されている。
			//CakeLog::debug("DBG: 選択したＴＺが変更されている。");
			++$timeRepeatModCnt;
			//CakeLog::debug("DBG: TZに変更あり！ originTzId=[" . $originTzId .
			//	"] VS cap[timezone]=[" . $cap['timezone'] . "]");
		}
	}

/**
 * __compareDatetime
 *
 * 日付時刻の比較
 * 入力されたユーザ日付（時刻）を、選択TZを考慮し、サーバ系日付時刻に直してから比較する。
 *
 * @param array $cap $data['ReservationActionPlan']情報
 * @param array $originEvent 元イベント関連情報
 * @param int &$timeRepeatModCnt 変更数。日付時刻情報が変更されいていたら１加算する。
 * @return void
 */
	private function __compareDatetime($cap, $originEvent, &$timeRepeatModCnt) {
		if ($cap['enable_time']) {
			//開始ー終了. "YYYY-MM-DD hh:mm" - "YYYY-MM-DD hh:mm"
			//
			//FIXME:  YYYY-MM-DD hh:mm のはずだが、手入力の時も問題ないか要確認。
			$nctm = new NetCommonsTime();

			$serverStartDatetime = $nctm->toServerDatetime($cap['detail_start_datetime'] . ':00',
				$cap['timezone']);
			$startDate = ReservationTime::stripDashColonAndSp(substr($serverStartDatetime, 0, 10));
			$startTime = ReservationTime::stripDashColonAndSp(substr($serverStartDatetime, 11, 8));
			$capDtstart = $startDate . $startTime;

			$serverEndDatetime = $nctm->toServerDatetime(
				$cap['detail_end_datetime'] . ':00', $cap['timezone']);
			$endDate = ReservationTime::stripDashColonAndSp(substr($serverEndDatetime, 0, 10));
			$endTime = ReservationTime::stripDashColonAndSp(substr($serverEndDatetime, 11, 8));
			$capDtend = $endDate . $endTime;
		} else {
			//終日指定
			//ReservationsAppMode.phpの_setAndMergeDateTimeEasy()の終日タイプと同様処理をする。
			//
			//FIXME:  YYYY-MM-DDのはずだが、手入力の時も問題ないか要確認.
			$ymd = substr($cap['detail_start_datetime'], 0, 10);	//YYYY-MM-DD
			list($serverStartDateZero, $serverNextDateZero) =
				(new ReservationTime())->convUserDate2SvrFromToDateTime(
					$ymd, $cap['timezone']);
			$startDate = ReservationTime::stripDashColonAndSp(substr($serverStartDateZero, 0, 10));
			$startTime = ReservationTime::stripDashColonAndSp(substr($serverStartDateZero, 11, 8));
			$capDtstart = $startDate . $startTime;

			$endDate = ReservationTime::stripDashColonAndSp(substr($serverNextDateZero, 0, 10));
			$endTime = ReservationTime::stripDashColonAndSp(substr($serverNextDateZero, 11, 8));
			$capDtend = $endDate . $endTime;
		}
		if ($capDtstart == $originEvent['ReservationEvent']['dtstart'] &&
			$capDtend == $originEvent['ReservationEvent']['dtend']) {
			//サーバ日付時間はすべて一致。
		} else {
			//サーバ日付時刻に変更あり。
			//CakeLog::debug("DBG: サーバ日付時刻に変更あり。");
			++$timeRepeatModCnt;
			/*
			CakeLog::debug("DBG: dtstar,dtendに変更あり！ POSTオリジナル enable_time[" .
				$cap['enable_time'] . "] detail_start_datetime[" . $cap['detail_start_datetime'] .
				"] detail_end_datetime[" . $cap['detail_end_datetime'] .
				"] timezone[" . $cap['timezone'] . "]  => サーバ系 capDtstart[" .
				$capDtstart . "] capDtend[" . $capDtend . "] VS origin dtstart[" .
				$originEvent['ReservationEvent']['dtstart'] . "] dtend[" .
				$originEvent['ReservationEvent']['dtend'] . "]");
			*/
		}
	}

/**
 * __arrayRecursiveDiff
 *
 * ２配列の集合の比較
 *
 * @param array $aArray1 配列１
 * @param array $aArray2 配列２
 * @return array 配列１の内、配列２にふくまれてない要素を配列で返す。
 */
	private function __arrayRecursiveDiff($aArray1, $aArray2) {
		$aReturn = array();
		foreach ($aArray1 as $mKey => $mValue) {
			if (array_key_exists($mKey, $aArray2)) {
				if (is_array($mValue)) {
					$aRecursiveDiffResult = $this->__arrayRecursiveDiff($mValue, $aArray2[$mKey]);
					if (count($aRecursiveDiffResult)) {
						$aReturn[$mKey] = $aRecursiveDiffResult;
					}
				} else {
					if ($mValue != $aArray2[$mKey]) {
						$aReturn[$mKey] = $mValue;
					}
				}
			} else {
				$aReturn[$mKey] = $mValue;
			}
		}
		return $aReturn;
	}

/**
 * 施設利用可能時間かのチェック
 *
 * @param int $startUnixTime 予約の開始日時
 * @param int $endUnixTime 予約の終了日時
 * @param string $locationStartTime 予約可能開始時刻
 * @param string $locationEndTime 予約可能終了時刻
 * @param array $reservableTimeTable 予約可能曜日
 * @return bool
 */
	protected function _isReservableLocationTimeRane($startUnixTime, $endUnixTime,
		$locationStartTime, $locationEndTime, $reservableTimeTable) {
		$weekDay = date('D', $startUnixTime); // 曜日 Mon .. Sun形式
		if (!in_array($weekDay, $reservableTimeTable)) {
			return false;
		}
		// 利用可能時間に収まっているか
		$startTime = date('H:i', $startUnixTime);
		$endTime = date('H:i', $endUnixTime);
		if ($startTime < $locationStartTime ||
			$locationEndTime < $endTime) {
			return false;
		}
		return true;
	}

/**
 * 施設の予約可能時間かチェック
 *
 * @param string $startDateTime Y-m-d H:i:s ユーザタイムゾーン
 * @param string $endDateTime Y-m-d H:i:s ユーザタイムゾーン
 * @param array $location 施設情報
 * @param string $locationTimeZone 施設のタイムゾーン
 * @param array $reservableTimeTable 予約可能曜日
 * @return bool
 */
	protected function _validateLocationTimeRange(
		$startDateTime,
		$endDateTime,
		$location,
		$locationTimeZone,
		$reservableTimeTable
	) {
		// 予約時間を施設のタイムゾーンの時間に変換
		// This timezone offset is id.
		$planTimeZone = new DateTimeZone($this->data[$this->alias]['timezone']);
		$startDateTime = new DateTime($startDateTime, $planTimeZone);
		$startDateTime->setTimezone($locationTimeZone);
		$startDateTime = $startDateTime->format('Y-m-d H:i:s');

		$endDateTime = new DateTime($endDateTime, $planTimeZone);
		$endDateTime->setTimezone($locationTimeZone);
		$endDateTime = $endDateTime->format('Y-m-d H:i:s');

		// 施設の利用可能時刻をUTCから施設のタイムゾーンに変換
		$locationStartTime = new DateTime(
			$location['ReservationLocation']['start_time'],
			new DateTimeZone('UTC')
		);
		$locationStartTime->setTimezone($locationTimeZone);
		$locationStartTime = $locationStartTime->format('H:i');

		$locationEndTime = new DateTime(
			$location['ReservationLocation']['end_time'],
			new DateTimeZone('UTC')
		);
		$locationEndTime->setTimezone($locationTimeZone);
		$locationEndTime = $locationEndTime->format('H:i');
		if ($locationStartTime == '00:00' && $locationStartTime == $locationEndTime) {
			// 00:00-00:00は00:00-24:00にする
			$locationEndTime = '24:00';
		}
		//
		//$length = strtotime($locationEndTime) = strtotime($locationStartTime);
		//$locationEndTime = strtotime($locationStartTime) + $length;
		//

		// 予約を日付毎に分割する
		// 以下、日付毎にチェックする
		// 　曜日の制約OKかをチェック
		// 　施設の利用可能時刻におさまってるかチェック
		$startDate = date('Y-m-d', strtotime($startDateTime));
		$endDate = date('Y-m-d', strtotime($endDateTime));
		if ($startDate != $endDate) {
			//　日付またぎの予約なら日付毎に分割してチェックする
			// $startDateから1日ずつたして$endDateまで
			$endDateUnixtime = strtotime($endDate);
			$current = strtotime($startDate);
			for ($current = $current; $current <= $endDateUnixtime; $current = $current + (24 * 60 * 60)) {
				if ($current == strtotime($startDate)) {
					// 開始日
					$startUnixTime = strtotime($startDateTime);
				} else {
					$startUnixTime = $current;
				}
				if ($current == strtotime($endDate)) {
					// 終了日
					$endUnixTime = strtotime($endDateTime);
				} else {
					$endUnixTime = $current + (24 * 60 * 60);
				}
				$result = $this->_isReservableLocationTimeRane(
					$startUnixTime,
					$endUnixTime,
					$locationStartTime,
					$locationEndTime,
					$reservableTimeTable
				);
				if (!$result) {
					return false;
				}
			}
			return true;
		} else {
			// 予約OKな曜日か

			$startUnixTime = strtotime($startDateTime);
			$endUnixTime = strtotime($endDateTime);

			return $this->_isReservableLocationTimeRane(
				$startUnixTime,
				$endUnixTime,
				$locationStartTime,
				$locationEndTime,
				$reservableTimeTable
			);
		}
	}
}
