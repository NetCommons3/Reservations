<?php
/**
 * ReservationWorkflowBehavior.php
 *
 * @author   Ryuji AMANO <ryuji@ryus.co.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 */

App::uses('WorkflowBehavior', 'Workflow.Model/Behavior');

/**
 * Class ReservationWorkflowBehavior
 */
class ReservationWorkflowBehavior extends WorkflowBehavior {

/**
 * beforeValidate is called before a model is validated, you can use this callback to
 * add behavior validation rules into a models validate array. Returning false
 * will allow you to make the validation fail.
 *
 * @param Model $model Model using this behavior
 * @param array $options Options passed from Model::save().
 * @return mixed False or null will abort the operation. Any other result will continue.
 * @see Model::save()
 */
	public function beforeValidate(Model $model, $options = array()) {
		// statusのバリデーションはスルー
	}

/**
 * Get workflow conditions
 *
 * @param Model $model Model using this behavior
 * @param array $conditions Model::find conditions default value
 * @return array Conditions data
 */
	public function getWorkflowConditions(Model $model, $conditions = array()) {
		$this->log(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true), 'debug');

		// ε(　　　　 v ﾟωﾟ)　＜ 施設予約にあわせて変更する
		if (Current::permission('content_editable')) {
			$activeConditions = array();
			$latestConditons = array(
				$model->alias . '.is_latest' => true,
			);
		} elseif (Current::permission('content_creatable')) {
			$activeConditions = array(
				$model->alias . '.is_active' => true,
				$model->alias . '.created_user !=' => Current::read('User.id'),
			);
			// 時限公開条件追加
			if ($model->hasField('public_type')) {
				$publicTypeConditions = $this->_getPublicTypeConditions($model);
				$activeConditions[] = $publicTypeConditions;
			}
			$latestConditons = array(
				$model->alias . '.is_latest' => true,
				$model->alias . '.created_user' => Current::read('User.id'),
			);
		} else {
			// 時限公開条件追加
			$activeConditions = array(
				$model->alias . '.is_active' => true,
			);
			if ($model->hasField('public_type')) {
				$publicTypeConditions = $this->_getPublicTypeConditions($model);
				$activeConditions[] = $publicTypeConditions;
			}
			$latestConditons = array();
		}

		if ($model->hasField('language_id')) {
			if (Current::read('Plugin.is_m17n') === false && $model->hasField('is_origin')) {
				$langConditions = array(
					$model->alias . '.is_origin' => true,
				);
			} elseif ($model->hasField('is_translation')) {
				$langConditions = array(
					'OR' => array(
						$model->alias . '.language_id' => Current::read('Language.id'),
						$model->alias . '.is_translation' => false,
					)
				);
			} else {
				$langConditions = array(
					$model->alias . '.language_id' => Current::read('Language.id'),
				);
			}
		} else {
			$langConditions = array();
		}

		$conditions = Hash::merge(
			array(
				$langConditions,
				array('OR' => array($activeConditions, $latestConditons))
			),
			$conditions
		);

		return $conditions;
	}

/**
 * Get workflow contents
 *
 * @param Model $model Model using this behavior
 * @param string $type Type of find operation (all / first / count / neighbors / list / threaded)
 * @param array $query Option fields (conditions / fields / joins / limit / offset / order / page / group / callbacks)
 * @return array Conditions data
 */
	public function getWorkflowContents(Model $model, $type, $query = array()) {
		//$this->log(var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5), true), 'debug');

		$query = Hash::merge(array(
			'recursive' => -1,
			'conditions' => $this->getWorkflowConditions($model)
		), $query);

		return $model->find($type, $query);
	}

/**
 * コンテンツの編集権限があるかどうかのチェック
 * - 編集権限あり(content_editable)
 * - 自分自身のコンテンツ
 *
 * @param Model $model Model using this behavior
 * @param array $data コンテンツデータ
 * @return bool true:編集可、false:編集不可
 */
	public function canEditWorkflowContent(Model $model, $data) {
		// ε(　　　　 v ﾟωﾟ)　＜ ReservationEventで使われてる
		if (Current::permission('content_editable')) {
			return true;
		}
		if (! isset($data[$model->alias])) {
			$data[$model->alias] = $data;
		}
		if (! isset($data[$model->alias]['created_user'])) {
			return false;
		}
		return ((int)$data[$model->alias]['created_user'] === (int)Current::read('User.id'));
	}
}