<?php
/**
 * 表示方法変更 Controller
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Ryuji AMANO <ryuji@ryus.co.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */

App::uses('ReservationsAppController', 'Reservations.Controller');
App::uses('ReservationsComponent', 'Reservations.Controller/Component');
App::uses('ReservationSettingsComponent', 'Reservations.Controller/Component');

/**
 * 表示方法変更 Controller
 *
 * @author Ryuji AMANO <ryuji@ryus.co.jp>
 * @package NetCommons\Reservations\Controller
 */
class ReservationFrameSettingsController extends ReservationsAppController {

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
 * use components
 *
 * @var array
 */
	public $components = array(
		'Categories.Categories',
		'Reservations.ReservationSettings' => [ //NetCommons.Permissionは使わず、独自でやる
			'permission' => ReservationSettingsComponent::PERMISSION_ROOM_EDITABLE
		],
	);

/**
 * use helpers
 *
 * @var array
 */
	public $helpers = array(
		'Blocks.BlockTabs', // 設定内容はReservationSettingsComponentにまとめた
	);

/**
 * uses model
 */
	public $uses = array(
		'Reservations.Reservation',
		'Reservations.ReservationFrameSetting',
		'Reservations.ReservationLocation',
		'Reservations.ReservationTimeframe'
	);

/**
 * frame display type options
 */
	protected $_displayTypeOptions;

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
		$this->_displayTypeOptions = array(
			ReservationsComponent::RESERVATION_DISP_TYPE_CATEGORY_WEEKLY =>
				__d('reservations', 'Weekly Reservation (Category)'),
			ReservationsComponent::RESERVATION_DISP_TYPE_CATEGORY_DAILY =>
				__d('reservations', 'Daily Reservation (Category)'),
			ReservationsComponent::RESERVATION_DISP_TYPE_LACATION_MONTHLY =>
				__d('reservations', 'Monthly Reservation (Location)'),
			ReservationsComponent::RESERVATION_DISP_TYPE_LACATION_WEEKLY =>
				__d('reservations', 'Weekly Reservation (Location)'),
		);
	}

/**
 * beforeFilter
 *
 * @return void
 */
	public function beforeFilter() {
		parent::beforeFilter();
		$this->Auth->deny('index');
	}

/**
 * edit
 *
 * @return void
 */
	public function edit() {
		$locations = $this->ReservationLocation->getLocations();
		if (! $locations) {
			$this->view = 'nolocation';
			return;
		}

		// openTextの追加
		$openText = new ReservationLocationOpenText();
		$locations = $openText->openTextAdd($locations);

		$this->set('locations', $locations);

		// 設定情報取り出し
		$setting = $this->ReservationFrameSetting->getFrameSetting();
		$settingId = $setting['ReservationFrameSetting']['id'];
		$this->set('settingId', $settingId);

		if ($this->request->is(['put', 'post'])) {
			//登録(PUT)処理
			$data = $this->request->data;

			$displayType = (int)$data['ReservationFrameSetting']['display_type'];
			$data['ReservationFrameSetting']['display_type'] = $displayType;
			if ($this->ReservationFrameSetting->saveFrameSetting($data)) {
				$this->redirect(NetCommonsUrl::backToPageUrl(true));
				return;
			}
			$this->NetCommons->handleValidationError($this->ReservationFrameSetting->validationErrors);
			//NC3用のvalidateErrorHandler.エラー時、非ajaxならSession->setFalsh()する.又は.(ajaxの時は)jsonを返す.
		} else {
			$this->request->data['ReservationFrameSetting'] = $setting['ReservationFrameSetting'];
		}

		// フレーム情報
		$this->request->data['Frame'] = Current::read('Frame');

		// 施設予約表示種別
		$this->set('displayTypeOptions', $this->_displayTypeOptions);

		// 時間枠データを取得する。なければ、時間枠の表示設定を表示しない
		$this->set('hasTimeframe', (bool)$this->ReservationTimeframe->find('count', ['recursive' => -1]));
	}
}
