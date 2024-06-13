<?php
/**
 * Reservation Plan Helper
 *
 * @author Allcreator Co., Ltd. <info@allcreator.net>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */
App::uses('AppHelper', 'View/Helper');
/**
 * Reservation plan Helper
 *
 * @author Allcreator Co., Ltd. <info@allcreator.net>
 * @package NetCommons\Reservations\View\Helper
 */
class ReservationPlanEditRepeatOptionHelper extends AppHelper {

/**
 * Other helpers used by FormHelper
 *
 * @var array
 */
	public $helpers = array(
		'Html',
		'Form',
		'NetCommons.NetCommonsForm',
		'NetCommons.NetCommonsHtml',
		'NetCommons.Button',
		'NetCommons.TitleIcon',
		'Reservations.ReservationMonthly',
		'Reservations.ReservationCommon',
		'Reservations.ReservationUrl',
	);

/**
 * makeEditRepeatOption
 *
 * @param array $eventSiblings イベント情報
 * @param int $firstSibEventKey イベントKey
 * @param int $firstSibYear デフォルト登録年
 * @param int $firstSibMonth デフォルト登録月
 * @param int $firstSibDay デフォルト登録日
 * @param bool $isRecurrence 繰り返し
 * @return string
 */
	public function makeEditRepeatOption(
		$eventSiblings, $firstSibEventKey, $firstSibYear, $firstSibMonth, $firstSibDay, $isRecurrence) {
		$orgNumOfEvSiblings = Hash::get($this->request->data,
			'ReservationActionPlan.origin_num_of_event_siblings');
		if (count($eventSiblings) <= 1 && $orgNumOfEvSiblings <= 1) {
			return '';
		}

		$editRrule = Hash::get($this->request->query, 'editrrule');
		if ($editRrule === null) {
			$editRrule = Hash::get($this->request->data, 'ReservationActionPlan.edit_rrule');
			if ($editRrule === null) {
				$editRrule = 0;
			}
		}

		$html = '<div class="form-group" data-reservation-name="RepeatSet">';
		$html .= '<div class="col-xs-12 col-sm-10 col-sm-offset-1">';
		$html .= '<div class="media"><div class="media-left h2">';
		$html .= $this->TitleIcon->titleIcon('/net_commons/img/title_icon/10_070_warning.svg');
		$html .= '</div><div class="media-body">';
		//全選択用に、繰返し先頭eventのeditボタのリンクを生成しておく
		$firstSibEditLink = '';
		//$key = Hash::get($this->_View->viewVars, 'event.ReservationEvent.key');
		if (!empty($firstSibEventKey)) {
			$firstSibEditLink = $this->Button->editLink('', array(
				'controller' => 'reservation_plans',
				'action' => 'edit',
				'key' => $firstSibEventKey,
				'block_id' => '',
				'frame_id' => Current::read('Frame.id'),
				'?' => array(
					'year' => $firstSibYear,
					'month' => $firstSibMonth,
					'day' => $firstSibDay,
					'editrrule' => 2,
				)
			));
			$firstSibEditLink = str_replace('&quot;', '"', $firstSibEditLink);
			$firstSibEditLink = str_replace('&amp;', '&', $firstSibEditLink);
			if (preg_match('/href="([^"]+)"/', $firstSibEditLink, $matches) === 1) {
				$firstSibEditLink = $matches[1];
			}
		}
		$html .= $this->_getMessage($isRecurrence);

		$html .= '</div></div>';

		$html .= '<div class="alert alert-warning">';
		$options = array();
		$options['0'] = __d('reservations', 'only this one');
		if (!$isRecurrence) {
			//「この予定のみ」指定で変更された予定ではないので、1,2も選択肢に加える。
			$options['1'] = __d('reservations', 'all after this one');
			$options['2'] = __d('reservations', 'all');
		}
		$html .= $this->NetCommonsForm->radio('ReservationActionPlan.edit_rrule', $options, array(
			'div' => 'form-inline',
			'value' => $editRrule,
			'ng-model' => 'editRrule',
			'ng-init' => "editRrule = '" . $editRrule . "'",
			'ng-change' => "changeEditRrule(" . Current::read('Frame.id') . ",'" . $firstSibEditLink . "')",
			//'ng-click' => 'sending=true',
		));
		if (! $isRecurrence) {
			$html .= '<p class="help-block text-right"><small>';
			$html .= __d('reservations',
				'When you select the [all] will be re-set to the contents is repeated first plan.');
			$html .= '<br />';
			$html .= __d('reservations',
				'If you want to edit without changing the schedule key, select [all after this one] and edit.');
			$html .= '</small></p>';
		}
		$html .= '</div></div></div>';
		return $html;
	}

/**
 * _getMessage
 *
 * @param bool $isRecurrence 繰り返し状況
 * @return string
 */
	protected function _getMessage($isRecurrence) {
		$html = __d('reservations',
			'This plan has been repeatedly set. ' .
			'Select the plan that you want to edit from the following items, ' .
			'Repetation of the plan [only this one] is not displayed.');

		if ($isRecurrence) {
			$html .= __d('reservations', 'Because it was specified in the [only this one], ' .
				'repetation of the plan can not be specified.');
		}
		return $html;
	}
}
