<?php
/**
 * Customisations to {@link Payment} specifically
 * for the shop module.
 *
 * @package shop
 */
class ShopPayment extends DataObjectDecorator {
	
	public static $summary_fields = array(
		"OrderID" => "Order ID",
		"ClassName" => "Type",
		"Amount" => "Amount",
		"Status" => "Status"
	);
		
	function extraStatics() {

		return array(
			'has_one' => array(
				'Order' => 'Order' //redundant...should be using PaidObject
			),
			'summary_fields' => self::$summary_fields,
			'searchable_fields' => array(
				'OrderID' => array(
					'title' => 'Order ID',
					'field' => 'TextField'
				),
				//'Created' => array('title' => 'Date','filter' => 'WithinDateRangeFilter','field' => 'DateRangeField'), //TODO: filter and field not implemented yet				
				'IP' => array(
					'title' => 'IP Address',
					'filter' => 'PartialMatchFilter'
				),
				'Status'
			)
		);
	}

	function canCreate($member = null) {
		return false;
	}

	function canDelete($member = null) {
		return false;
	}

	//TODO: this function could get called multiple times, resulting in unwanted logs , changes etc.
	function onAfterWrite() {
		if($this->owner->Status == 'Success' && $order = $this->owner->Order()) {

			if(!$order->ReceiptSent){
				$order->sendReceipt();
				$order->updatePaymentStatus();
			}

		}
	}

	function redirectToOrder() {
		$order = $this->owner->Order();
		Director::redirect($order->Link());
		return;
	}

	function setPaidObject(DataObject $do){
		$this->owner->PaidForID = $do->ID;
		$this->owner->PaidForClass = $do->ClassName;
	}

	function Status() {
   		return _t('Payment.'.$this->owner->Status,$this->owner->Status);
	}


}
