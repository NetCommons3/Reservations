<?php
/**
 * カテゴリー別 - 週表示 template
 *
 * @author Noriko Arai <arai@nii.ac.jp>
 * @author Ryuji AMANO <ryuji@ryus.co.jp>
 * @link http://www.netcommons.org NetCommons Project
 * @license http://www.netcommons.org/license.txt NetCommons License
 * @copyright Copyright 2014, NetCommons Project
 */
echo $this->element('Reservations.scripts');
?>

<article ng-controller="ReservationsDetailEdit" class="block-setting-body">
	<?php
		//共通タブ(カテゴリー別、施設別)
		echo $this->element('Reservations.Reservations/common_tabs');
	?>
	<?php
		//カテゴリー別の表示方法タブ(週、日)
		echo $this->element('Reservations.Reservations/tabs_by_category');
	?>

	<div class="clearfix">
		<?php echo $this->ReservationTurnReservation->getTurnReservationOperations('week', 'top', $vars); ?>

		<div class="reservation-category-operations reservation-category-operations-top pull-left">
			<?php echo $this->element('Reservations.Reservations/dropdown_category'); ?>
		</div>
	</div>

	<div class="text-center reservation-weekly-locations-table table-responsive">
		<table>
			<thead>
				<?php echo $this->ReservationWeekly->makeWeeklyHeaderHtml($vars); ?>
			</thead>

			<tbody>
				<?php echo $this->ReservationWeekly->makeWeeklyBodyHtml($vars); ?>
				<?php $reservationLinePlans = $this->ReservationWeekly->getLineData() ?>
			</tbody>
		</table>

		<div ng-controller="ReservationsMonthlyLinePlan"
				ng-style="initialize(<?php echo h(json_encode(array('reservationLinePlans' => $reservationLinePlans))) ?>)" resize>
		</div>
	</div>

	<?php echo $this->ReservationLegend->getReservationLegend($vars); ?>
</article>

