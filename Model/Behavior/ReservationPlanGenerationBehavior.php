<?php
	/**
	 * ReservationPlanGeneration Behavior
	 *
	 * @author Noriko Arai <arai@nii.ac.jp>
	 * @author Allcreator <info@allcreator.net>
	 * @link http://www.netcommons.org NetCommons Project
	 * @license http://www.netcommons.org/license.txt NetCommons License
	 * @copyright Copyright 2014, NetCommons Project
	 */

App::uses('ReservationAppBehavior', 'Reservations.Model/Behavior');
App::uses('WorkflowComponent', 'Workflow.Controller/Component');

	/**
	 * ReservationPlanGenerationBehavior
	 *
	 * @property array $reservationWdayArray reservation weekday array 施設予約曜日配列
	 * @property array $editRrules editRules　編集ルール配列
	 * @author Allcreator <info@allcreator.net>
	 * @package NetCommons\Reservations\Model\Behavior
	 */
class ReservationPlanGenerationBehavior extends ReservationAppBehavior {

/**
 * Default settings
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author AllCreator Co., Ltd. <info@allcreator.net>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2015, NetCommons Project
 */
	protected $_defaults = array(
		);

/**
 * 現世代の予定を作り出す
 *
 * @param Model $model 実際のモデル名
 * @param array $data data POSTされたrequest->data配列
 * @param int $originEventId originEventId（現eventのid）
 * @param string $originEventKey originEventKey（現eventのkey）
 * @param int $originRruleId originRruleId（現eventのkey）
 * @return int 成功時 現世代予定を返す。失敗時 InternalErrorExceptionを投げる。
 * @throws InternalErrorException
 */
	public function makeCurGenPlan(Model $model, $data,
		$originEventId, $originEventKey, $originRruleId) {
		$action = 'delete';
		$plan = $this->__makeCommonGenPlan($model, $action, $data, $originRruleId);

		//現世代予定のrruleDataのidとkeyをマーキングしておく.
		$plan['cur_rrule_id'] = $plan['ReservationRrule']['id'];
		$plan['cur_rrule_key'] = $plan['ReservationRrule']['key'];

		//現世代予定の指定されたeventDataのidとkeyをマーキングしておく.
		$plan['cur_event_id'] = $originEventId;
		$plan['cur_event_key'] = $originEventKey;

		return $plan;
	}

/**
 * 元予定の新世代予定を作り出す
 *
 * @param Model $model 実際のモデル名
 * @param array $data POSTされたrequest->data配列
 * @param string $status status 変更時の施設予約独自の新status
 * @param int $createdUserWhenUpd createdUserWhenUpd
 * @param bool $isMyPrivateRoom isMyPrivateRoom
 * @param string|int $editRrule 編集ルール（この予約のみ、これ以降に指定した全ての予約、設定した全ての予約）
 * @return int 生成成功時 新しく生成した次世代予定($plan)を返す。失敗時 InternalErrorExceptionを投げる。
 * @throws InternalErrorException
 * @SuppressWarnings(PHPMD.CyclomaticComplexity)
 */
	public function makeNewGenPlan(Model $model, $data, $status,
		$createdUserWhenUpd, $isMyPrivateRoom, $editRrule) {
		$action = 'update';
		$plan = $this->__makeCommonGenPlan($model, $action, $data,
			$data['ReservationActionPlan']['origin_rrule_id']);

		//keyが同じrrule -> key同一のevents -> eventsの各子供をcopy保存する

		$plan = $this->__copyRruleData($model, $plan, $createdUserWhenUpd, $editRrule);

		$baseDate = str_replace('-', '', $data['ReservationActionPlanForDisp']['detail_start_datetime']);

		unset($plan['new_event_id']);	//念のため変数クリア
		$effectiveEvents = array();	//有効なeventだけを格納する配列を用意
		foreach ($plan['ReservationEvent'] as &$event) {
			//exception_event_id int ... 1以上のとき、例外（削除）イベントidを指す」より、
			//ここの値が１以上の時は、例外（削除）イベントなので、copy対象から外す.
			if ($event['exception_event_id'] >= 1) {
				continue;
			}
			if ($editRrule == self::CALENDAR_PLAN_EDIT_THIS &&
					$data['ReservationActionPlan']['origin_event_id'] != $event['id']) {
				//この予約のみの場合、自分自身の予約以外は無視する。
				continue;
			}
			if ($editRrule == self::CALENDAR_PLAN_EDIT_AFTER &&
					$data['ReservationActionPlan']['origin_event_id'] != $event['id'] &&
					$baseDate > $event['start_date']) {
				//これ以降に指定した全ての予約の場合、基準日より過去のものは無視する。
				continue;
			}

			$newEventId = $newEventKey = null;
			list($event, $newEventId, $newEventKey) = $this->__copyEventData($model,
				$event,
				$plan['ReservationRrule']['id'],
				$status,
				$data['ReservationActionPlan']['origin_event_id'],
				$data['ReservationActionPlan']['origin_event_key'],
				$createdUserWhenUpd, $isMyPrivateRoom
			);
			if (!isset($plan['new_event_id']) && !empty($newEventId) && !empty($newEventKey)) {
				//対象元となったeventの新世代なので、新世代eventのidとkeyの値をplanにセットしておく
				//なお、この処理は１度だけ実行され２度は実行されない。
				$plan['new_event_id'] = $newEventId;
				$plan['new_event_key'] = $newEventKey;
			}
			$effectiveEvents[] = $event;	//有効なeventだったので配列にappend
		}

		//有効なイベント集合配列に置き換える
		$plan['ReservationEvent'] = $effectiveEvents;

		return $plan;
	}

/**
 * __copyRruleData
 *
 * 元予定の次世代CalenarRruleを作り出す
 *
 * @param Model $model 実際のモデル名
 * @param array $plan plan
 * @param int $createdUserWhenUpd createdUserWhenUpd
 * @param string|int $editRrule 編集ルール（この予約のみ、これ以降に指定した全ての予約、設定した全ての予約）
 * @return int 生成成功時 新しい$planを返す。失敗時 InternalErrorExceptionを投げる。
 * @throws InternalErrorException
 */
	private function __copyRruleData(Model $model, $plan, $createdUserWhenUpd, $editRrule) {
		//ReservationRruleには、status, is_latest, is_activeはない。

		$rruleData = array();
		$rruleData['ReservationRrule'] = $plan['ReservationRrule'];

		//次世代データの新規登録
		$originRruleId = $rruleData['ReservationRrule']['id'];
		$rruleData['ReservationRrule']['id'] = null;
		if ($editRrule == self::CALENDAR_PLAN_EDIT_THIS) {
			$rruleData['ReservationRrule']['rrule'] = '';
		}

		//作成者・作成日は原則、元予定のデータを引き継ぐ、、、が！例外がある。
		//例外追加１）
		//変更後の公開ルームidが、「元予定生成者の＊ルーム」から「編集者・承認者(＝ログイン者）の
		//プライベート」に変化していた場合、created_userを、元予定生成者「から」編集者・承認者(＝ログイン者）
		//「へ」に変更すること。
		//＝＞これを考慮したcreatedUserWhenUpdを使えばよい。
		if ($createdUserWhenUpd !== null) {
			$rruleData['ReservationRrule']['created_user'] = $createdUserWhenUpd;
		}

		$rruleData['ReservationRrule']['modified_user'] = null;
		$rruleData['ReservationRrule']['modified'] = null;

		if (!isset($model->ReservationRrule)) {
			$model->loadModels(['ReservationRrule' => 'Reservations.ReservationRrule']);
		}
		$model->ReservationRrule->set($rruleData);
		if (!$model->ReservationRrule->validates()) {	//ReservationRruleのチェック
			$model->validationErrors = Hash::merge(
				$model->validationErrors, $model->ReservationRrule->validationErrors);
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}
		$rruleData = $model->ReservationRrule->save($rruleData, false);
		if (!$rruleData) { //保存のみ
			CakeLog::error("変更時に指定された元予定(reservation_rrule_id=[" .
				$originRruleId . "])のCOPYに失敗");
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}
		$plan['ReservationRrule'] = $rruleData['ReservationRrule'];

		//新世代のrruleのidとkeyをReservationActionPlan直下に保存しておく。
		//
		$plan['new_rrule_id'] = $rruleData['ReservationRrule']['id'];
		$plan['new_rrule_key'] = $rruleData['ReservationRrule']['key'];

		return $plan;
	}

/**
 * __copyEventData
 *
 * 元予定の次世代CalenarEventを作り出す
 * なお、対象元となったeventのCOPYの時だけ、newEventId, newEventKeyをセットして返す。
 *
 * @param Model $model 実際のモデル名
 * @param array $event event
 * @param int $reservationRruleId reservationRruleId
 * @param string $status status 変更時の施設予約独自新status
 * @param sring $originEventId 選択されたeventのid(origin_event_id)
 * @param sring $originEventKey 選択されたeventのkey(origin_event_key)
 * @param int $createdUserWhenUpd createdUserWhenUpd
 * @param bool $isMyPrivateRoom isMyPrivateRoom
 * @return int 生成成功時 新しい$event、newEventId, newEventKeyを返す。失敗時 InternalErrorExceptionを投げる。
 * @throws InternalErrorException
 * @SuppressWarnings(PHPMD)
 */
	private function __copyEventData(Model $model, $event, $reservationRruleId, $status,
		$originEventId, $originEventKey, $createdUserWhenUpd, $isMyPrivateRoom) {
		//ReservationEventには、status, is_latest, is_activeがある。
		//
		//通常、WFを組み込んでいる時は、is_latest,is_activeは、WFのbeforeSaveで、
		//insertの時だけstatusに従い自動調整セットされ、update(updateAll含む)の時は、
		//is_latest,is_activeは自動調整セットされない。
		//が！以下では、WF,WFCommentをunloadして外し、代わりに施設予約拡張の処理を実行
		//させているので、注意すること。

		$eventData = array();
		$eventData['ReservationEvent'] = $event;

		//次世代データの新規登録
		$originEventId = $eventData['ReservationEvent']['id'];

		$setNewIdAndKey = false;
		if ($eventData['ReservationEvent']['id'] == $originEventId &&
			$eventData['ReservationEvent']['key'] == $originEventKey) {
			//このeventは対象元となったeventである。
			$setNewIdAndKey = true;
		}

		$eventData['ReservationEvent']['id'] = null;
		$eventData['ReservationEvent']['reservation_rrule_id'] = $reservationRruleId;

		//「status, is_active, is_latest, created, created_user について」
		//statusは、元世代のstatus値を引き継ぐ。
		$eventData['ReservationEvent']['modified_user'] = null;
		$eventData['ReservationEvent']['modified'] = null;

		if (!isset($model->ReservationEvent)) {
			$model->loadModels(['ReservationEvent' => 'Reservations.ReservationEvent']);
		}
		// 各種Behaviorはずす FUJI
		$model->ReservationEvent->Behaviors->unload('Reservations.ReservationWorkflow');
		$model->ReservationEvent->Behaviors->unload('Workflow.WorkflowComment');

		$model->ReservationEvent->set($eventData);
		if (!$model->ReservationEvent->validates()) {	//ReservationEventのチェック
			$model->validationErrors = Hash::merge(
				$model->validationErrors, $model->ReservationEvent->validationErrors);
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}
		//各種Behavior終わったら戻す FUJI
		//
		//＝＞ WFのbeforeSaveのis_active調整処理は
		//INSERTではなく、UPDATEまで処理delayさせる必要があるが、is_latestとcreatedは
		// ここで行なうべき。ゆえに、(a) load(WF.WF)をsave()の後に移動し、(b)カレンダ
		// のLatestおよびCreated準備処理をここに差し込む。
		// (eventDataの値、一部更新等しています) HASHI
		//
		//例外追加）createdUserWhenUpdにnull以外の値（ユーザID)が入っていたら、
		//keyが一致する過去世代予定の有無に関係なく、そのcreatedUserWhenUpdを、created_userに
		//セットするようにした。
		$model->ReservationEvent->prepareLatestCreatedForIns($eventData, $createdUserWhenUpd);

		// $eventDataがprepareLatestCreatedForIns()で変更されるので、再度setする
		$model->ReservationEvent->set($eventData);
		//子もsave（）で返ってくる。
		$eventData = $model->ReservationEvent->save(null, false); //aaaaaaaaaaaaa
		if (!$eventData) { //保存のみ
			CakeLog::error("変更時に指定された元イベント(reservation_event_id=[" .
				$originEventId . "])のCOPYに失敗");
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}
		// 各種Behavior終わったら戻す FUJI ＝＞ 再load(WF.WF)の発行位置をsave後に変更 HASHI
		$model->ReservationEvent->Behaviors->load('Reservations.ReservationWorkflow');

		// 各種Behavior終わったら戻す FUJI
		$model->ReservationEvent->Behaviors->load('Workflow.WorkflowComment');

		$newEventId = $newEventKey = null;
		if ($setNewIdAndKey) {
			//対象元となったeventの新世代なので、新世代eventのidとkeyの値をセットする
			$newEventId = $eventData['ReservationEvent']['id'];
			$newEventKey = $eventData['ReservationEvent']['key'];
		}

		////reservation_event_contentsをcopyする
		//foreach ($eventData['ReservationEvent']['ReservationEventContent'] as &$content) {
		//	$content = $this->__copyEventContentData($model, $content,
		//		$eventData['ReservationEvent']['id'], $createdUserWhenUpd);
		//}

		if ($isMyPrivateRoom) {
			//変更後の公開ルームidが、「編集者・承認者（＝ログイン者）のプライベート」なので
			//reservation_event_share_usersをcopyする
			//CakeLog::debug("DBG: 変更後の公開ルームidがログイン者のプライべートのケース.");
			foreach ($eventData['ReservationEvent']['ReservationEventShareUser'] as &$shareUser) {
				$shareUser = $this->__copyEventShareUserData(
					$model, $shareUser, $eventData['ReservationEvent']['id'], $createdUserWhenUpd);
			}
		} else {
			//変更後の公開ルームidが、「編集者・承認者（＝ログイン者）のプライベート」「以外」の場合、
			//仲間の予定はプライベートの時のみ許される子情報なので、これらはcopy対象から外す（stripする)こと。
			if (isset($eventData['ReservationEvent']['ReservationEventShareUser'])) {
				//unset($eventData['ReservationEvent']['ReservationEventShareUser']);
				//CakeLog::debug("DBG: 変更後の公開ルームidがログイン者のプライべート「以外」のケース..");
				//CakeLog::debug("DBG: copyされない共有予定データ群[" . print_r($eventData['ReservationEvent']['ReservationEventShareUser'], true) . "]");
				$eventData['ReservationEvent']['ReservationEventShareUser'] = array();
			}
		}

		$event = $eventData['ReservationEvent'];

		return array($event, $newEventId, $newEventKey);
	}

/**
 * __copyEventContentData
 *
 * 元予定の次世代CalenarEventContentを作り出す
 *
 * @param Model $model 実際のモデル名
 * @param array $content content
 * @param int $reservationEventId reservationEventId
 * @param int $createdUserWhenUpd createdUserWhenUpd
 * @return int 生成成功時 新しい$contentを返す。失敗時 InternalErrorExceptionを投げる。
 * @throws InternalErrorException
 */
	//private function __copyEventContentData($model, $content,
	//								$reservationEventId, $createdUserWhenUpd) {
	//	//ReservationEventContentには、status, is_latest, is_activeはない
	//
	//	$contentData = array();
	//	$contentData['ReservationEventContent'] = $content;
	//
	//	//次世代データの新規登録
	//	$originContentId = $contentData['ReservationEventContent']['id'];
	//	$contentData['ReservationEventContent']['id'] = null;
	//	$contentData['ReservationEventContent']['reservation_event_id'] = $reservationEventId;
	//
	//	//作成日と作成者は、元予定のreservation_event_contentsのものを継承する、、が！例外がある。
	//	//例外追加１）
	//	//変更後の公開ルームidが、「元予定生成者の＊ルーム」から「編集者・承認者(＝ログイン者）の
	//	//プライベート」に変化していた場合、created_userを、元予定生成者「から」編集者・承認者(＝ログイン者）
	//	//「へ」に変更すること。
	//	//＝＞これを考慮したcreatedUserWhenUpdを使えばよい。
	//	if ($createdUserWhenUpd !== null) {
	//		$contentData['ReservationEventContent']['created_user'] = $createdUserWhenUpd;
	//	}
	//
	//	$contentData['ReservationEventContent']['modified_user'] = null;
	//	$contentData['ReservationEventContent']['modified'] = null;
	//
	//	if (!isset($model->ReservationEventContent)) {
	//		$model->loadModels(['ReservationEventContent' => 'Reservations.ReservationEventContent']);
	//	}
	//	$model->ReservationEventContent->set($contentData);
	//	if (!$model->ReservationEventContent->validates()) {	//ReservationEventContentのチェック
	//		$model->validationErrors = Hash::merge(
	//			$model->validationErrors, $model->ReservationEventContent->validationErrors);
	//		throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
	//	}
	//	$contentData = $model->ReservationEventContent->save($contentData, false);
	//	if (!$contentData) { //保存のみ
	//		CakeLog::error("変更時に指定された元コンテンツ(reservation_event_content_id=[" .
	//			$originContentId . "])のCOPYに失敗");
	//		throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
	//	}
	//
	//	$content = $contentData['ReservationEventContent'];
	//	return $content;
	//}

/**
 * __copyEventShareUserData
 *
 * 元予定の次世代CalenarEventShareUserを作り出す
 *
 * @param Model $model 実際のモデル名
 * @param array $shareUser shareUser
 * @param int $reservationEventId reservationEventId
 * @param int $createdUserWhenUpd createdUserWhenUpd
 * @return int 生成成功時 新しい$shareUserを返す。失敗時 InternalErrorExceptionを投げる。
 * @throws InternalErrorException
 */
	private function __copyEventShareUserData($model, $shareUser, $reservationEventId,
		$createdUserWhenUpd) {
		//ReservationEventShareUserには、status, is_latest, is_activeはない

		$shareUserData = array();
		$shareUserData['ReservationEventShareUser'] = $shareUser;

		//次世代データの新規登録
		$originShareUserId = $shareUserData['ReservationEventShareUser']['id'];
		$shareUserData['ReservationEventShareUser']['id'] = null;
		$shareUserData['ReservationEventShareUser']['reservation_event_id'] = $reservationEventId;

		//作成日と作成者は、元予定のreservation_event_share_usersのものを継承する、、が！例外がある。
		//例外追加１）
		//変更後の公開ルームidが、「元予定生成者の＊ルーム」から「編集者・承認者(＝ログイン者）の
		//プライベート」に変化していた場合、created_userを、元予定生成者「から」編集者・承認者(＝ログイン者）
		//「へ」に変更すること。
		//＝＞これを考慮したcreatedUserWhenUpdを使えばよい。
		if ($createdUserWhenUpd !== null) {
			$shareUserData['ReservationEventShareUser']['created_user'] = $createdUserWhenUpd;
		}

		$shareUserData['ReservationEventShareUser']['modified_user'] = null;
		$shareUserData['ReservationEventShareUser']['modified'] = null;

		if (!isset($model->ReservationEventShareUser)) {
			$model->loadModels(['ReservationEventShareUser' => 'Reservations.ReservationEventShareUser']);
		}

		//ReservationEventShareUserのチェック
		$model->ReservationEventShareUser->set($shareUserData);
		if (!$model->ReservationEventShareUser->validates()) {
			$model->validationErrors = Hash::merge(
				$model->validationErrors, $model->ReservationEventShareUser->validationErrors);
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}
		$shareUserData = $model->ReservationEventShareUser->save($shareUserData, false);
		if (!$shareUserData) { //保存のみ
			CakeLog::error(
				'変更時に指定された元共有ユーザ' .
					'(reservation_event_share_user_id=[' . $originShareUserId . '])' .
				'のCOPYに失敗');
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}

		$shareUser = $shareUserData['ReservationEventShareUser'];
		return $shareUser;
	}

/**
 * __makeCommonGenPlan
 *
 * 共通の世代生成処理
 *
 * @param Model $model 実際のモデル名
 * @param string $action action('update' or 'delete')
 * @param array $data data
 * @param int $rruleId rruleId
 * @return array 生成した予定($plan)
 * @throws InternalErrorException
 */
	private function __makeCommonGenPlan(Model $model, $action, $data, $rruleId) {
		if (!isset($model->ReservationRrule)) {
			$model->loadModels(['ReservationRrule' => 'Reservations.ReservationRrule']);
		}
		$options = array(
			'conditions' => array(
				$model->ReservationRrule->alias . '.id' => $rruleId,
			),
			'recursive' => 1,
			//'callbacks' => false,	//callbackは呼ばない
		);
		$plan = $model->ReservationRrule->find('first', $options);
		if (empty($plan)) {
			if ($action === 'update') {
				CakeLog::error("変更時に指定された元予定(reservation_rrule_id=[" .
					$data['origin_rrule_id'] . "])が存在しない。");
			} else {	//delete
				CakeLog::error("削除時に指定された元予定(reservation_rrule_id=[" .
					$data['origin_rrule_id'] . "])が存在しない。");
			}
			throw new InternalErrorException(__d('net_commons', 'Internal Server Error'));
		}

		//ReservationEventsの関係データをとってきて必要なもののみ加える。
		//
		if (!isset($model->ReservationEvent)) {
			$model->loadModels(['ReservationEvent' => 'Reservations.ReservationEvent']);
		}
		foreach ($plan['ReservationEvent'] as &$event) {
			$options2 = array(
				'conditions' => array(
					//copyはdeadcopyイメージなので、言語ID,除去フラグに関係なくとってくる。
					$model->ReservationEvent->alias . '.id' => $event['id'],
				),
				'recursive' => 1,
				'order' => array($model->ReservationEvent->alias . '.dtstart' => 'ASC'),
			);
			$eventData = $model->ReservationEvent->find('first', $options2);
			//event配下の配下関連テーブルだけ追加しておく
			//
			$event['ReservationEventShareUser'] = $eventData['ReservationEventShareUser'];
			//$event['ReservationEventContent'] = $eventData['ReservationEventContent'];
		}
		return $plan;
	}

}
