<?php
/**
 * BlogEntriesEdit
 */
App::uses('ReservationsAppController', 'Reservations.Controller');

/**
 * BlogEntriesEdit Controller
 *
 *
 * @author   Ryuji AMANO <ryuji@ryus.co.jp>
 * @link     http://www.netcommons.org NetCommons Project
 * @license  http://www.netcommons.org/license.txt NetCommons License
 * @property NetCommonsWorkflow $NetCommonsWorkflow
 * @property PaginatorComponent $Paginator
 * @property ReservationTimeframe $ReservationTimeframe
 * @property BlogCategory $BlogCategory
 */
class ReservationTimeframesController extends ReservationsAppController {

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
		'Reservations.ReservationTimeframe',
		'Categories.Category',
		//'Workflow.WorkflowComment',
	);

/**
 * Components
 *
 * @var array
 */
	public $components = array(
		'NetCommons.Permission' => array(
			//アクセスの権限
			'allow' => array(
				'edit' => 'page_editable',
			),
		),
		//'Workflow.Workflow',

		'Categories.Categories',
		//'Blogs.ReservationTimeframePermission',
		'NetCommons.NetCommonsTime',
		'Paginator',
		'Rooms.RoomsForm',

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

		'Blocks.BlockTabs' => array(
			//画面上部のタブ設定
			'mainTabs' => array(
				'category_settings' => [
					'label' => ['reservations', 'Location category setting'],
					'url' => array('controller' => 'reservation_settings', 'action' => 'edit')
				],
				'location_settings' => array(
					'label' => ['reservations', 'Location setting'],
					'url' => array('controller' => 'reservation_locations', 'action' => 'index')
				),
				'timeframe_settings' => array(
					'label' => ['reservations', 'TimeFrame setting'],
					'url' => array('controller' => 'reservation_timeframes', 'action' => 'index')
				),
				'import_reservations' => array(
					'label' => ['reservations', 'Import Reservations'],
					'url' => array('controller' => 'reservation_import', 'action' => 'edit')
				),
				'frame_settings' => array(	//表示設定変更
					'url' => array('controller' => 'reservation_frame_settings')
				),
				//'role_permissions' => array(
				//	'url' => array('controller' => 'reservation_block_role_permissions'),
				//),
				'mail_settings' => array(
					'url' => array('controller' => 'reservation_mail_settings'),
				),
			),
			'mainTabsOrder' => [
				'frame_settings', 'location_settings', 'category_settings', 'timeframe_settings',
				'mail_settings', 'import_reservations'

			],
		),
		'Rooms.RoomsForm',
	);

/**
 * index
 *
 * @return void
 */
	public function index() {
		//$data = $this->ReservationTimeframe->findById(1);
		// FAQの並び替え参考にしよう
		$query = array();

		//条件
		$conditions = array(
			'ReservationTimeframe.language_id' => Current::read('Language.id'),
		);
		$query['conditions'] = $conditions;
		$query['order'] = 'ReservationTimeframe.start_time ASC';

		$query['recursive'] = 0;
		$this->Paginator->settings = $query;
		$timeframes = $this->Paginator->paginate('ReservationTimeframe');
		$this->set('reservationTimeframes', $timeframes);
	}

/**
 * add method
 *
 * @return void
 */
	public function add() {
		$this->set('isEdit', false);

		if ($this->request->is('post')) {
			$this->ReservationTimeframe->create();
			$this->request->data['ReservationTimeframe']['language_id'] = Current::read('Language.id');
			$result = $this->ReservationTimeframe->saveTimeframe($this->request->data);
			if ($result) {
				$url = NetCommonsUrl::actionUrl(
					array(
						'controller' => 'reservation_timeframes',
						'action' => 'index',
						'frame_id' => Current::read('Frame.id'),
						)
				);
				return $this->redirect($url);
			}

			$this->NetCommons->handleValidationError($this->ReservationTimeframe->validationErrors);

		} else {
			$newLocation = $this->ReservationTimeframe->create();
			$newLocation['ReservationTimeframe'] = [
				'start_time' => '09:00',
				'end_time' => '18:00',
			];
			$this->request->data = $newLocation;
		}
		$this->render('form');
	}

/**
 * edit method
 *
 * @return void
 */
	public function edit() {
		$this->set('isEdit', true);
		//$key = $this->request->params['named']['key'];
		$key = $this->params['key'];

		//  keyのis_latstを元に編集を開始
		$this->ReservationTimeframe->recursive = 0;
		$options = [
			'conditions' => [
				'ReservationTimeframe.key' => $key,
				'ReservationTimeframe.language_id' => Current::read('Language.id')
			]
		];

		$ReservationTimeframe = $this->ReservationTimeframe->find('first', $options);

		if (empty($ReservationTimeframe)) {
			return $this->throwBadRequest();
		}

		if ($this->request->is(array('post', 'put'))) {

			$this->ReservationTimeframe->create();

			$this->request->data['ReservationTimeframe']['language_id'] = Current::read('Language.id');

			$data = $this->request->data;

			if ($this->ReservationTimeframe->saveTimeframe($data)) {
				$url = NetCommonsUrl::actionUrl(
					array(
						'controller' => 'reservation_timeframes',
						'action' => 'index',
						'frame_id' => Current::read('Frame.id'),
					)
				);

				return $this->redirect($url);
			}

			$this->NetCommons->handleValidationError($this->ReservationTimeframe->validationErrors);

		} else {

			$this->request->data = $ReservationTimeframe;

		}

		$this->set('ReservationTimeframe', $ReservationTimeframe);
		$this->set('isDeletable', true);

		$this->render('form');
	}

/**
 * delete method
 *
 * @throws InternalErrorException
 * @return void
 */
	public function delete() {
		$this->request->allowMethod('post', 'delete');

		$key = $this->request->data['ReservationTimeframe']['key'];
		$blogEntry = $this->ReservationTimeframe->getWorkflowContents('first', array(
			'recursive' => 0,
			'conditions' => array(
				'ReservationTimeframe.key' => $key
			)
		));

		// 権限チェック
		if ($this->ReservationTimeframe->canDeleteWorkflowContent($blogEntry) === false) {
			return $this->throwBadRequest();
		}

		if ($this->ReservationTimeframe->deleteEntryByKey($key) === false) {
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}
		return $this->redirect(
			NetCommonsUrl::actionUrl(
				array(
					'controller' => 'blog_entries',
					'action' => 'index',
					'frame_id' => Current::read('Frame.id'),
					'block_id' => Current::read('Block.id')
				)
			)
		);
	}
}