<?php
/**
 * Handles tasks to be performed on orders, particularly placing and processing/fulfilment.
 * Placing, Emailing Reciepts, Status Updates, Printing, Payments - things you do with a completed order.
 * 
 * @package shop
 * @todo split into different classes relating to individual concerns.
 * @todo bring over status updating code
 * @todo figure out reference issues ...if you store a reference to order in here, it can get stale.
 */
class OrderProcessor{
	
	protected $order;
	protected $error;
	
	/**
	* This is the from address that the receipt
	* email contains. e.g. "info@shopname.com"
	*
	* @var string
	*/
	protected static $email_from;
	
	/**
	* This is the subject that the receipt
	* email will contain. e.g. "Joe's Shop Receipt".
	*
	* @var string
	*/
	protected static $receipt_subject = "Shop Sale Information #%d";
	
	/**
	 * Static way to create the order processor.
	 * Makes creating a processor easier.
	 * @param Order $order
	 */
	static function create(Order $order){		
		return new OrderProcessor($order);
	}
	
	/**
	* Set the from address for receipt emails.
	*
	* @param string $email From address. e.g. "info@myshop.com"
	*/
	public static function set_email_from($email) {
		self::$email_from = $email;
	}
	
	public static function set_receipt_subject($subject) {
		self::$receipt_subject = $subject;
	}
	
	/**
	 * Assign the order to a local variable
	 * @param Order $order
	 */
	private function __construct(Order $order){
		$this->order = $order;
	}
	
	/**
	 * Takes an order from being a cart to awaiting payment.
	 * @param Member $member - assign a member to the order
	 * @return boolean - success/failure
	 */
	function placeOrder(){
		if(!$this->order){
			$this->error(_t("OrderProcessor.NULL","A new order has not yet been started."));
			return false;
		}
		//TODO: check price hasn't changed since last calculation??
		$this->order->calculate(); //final re-calculation
		if(!$this->canPlace($this->order)){ //final cart validation
			return false;
		}
		$this->order->Status = 'Unpaid'; //update status
		//re-write all attributes and modifiers to make sure they are up-to-date before they can't be changed again
		$attributes = $this->order->Items();
		if($attributes->exists()){
			foreach($attributes as $attribute){
				$attribute->write();
			}
		}
		$attributes = $this->order->Modifiers();
		if($attributes->exists()){
			foreach($attributes as $attribute){
				$attribute->write();
			}
		}
		//TODO: add member to customer group
		OrderManipulation::add_session_order($this->order); //save order reference to session
		$this->order->extend('onPlaceOrder'); //allow decorators to do stuff when order is saved.
		$this->order->write();
		return true; //report success
	}
	
	/**
	 * Create a new payment for an order
	 */
	function createPayment($paymentClass = "Payment"){
		if($this->order->canPay(Member::currentUser())){
			$payment = class_exists($paymentClass) ? new $paymentClass() : null;
			if(!($payment && $payment instanceof Payment)) {
				$this->error(_t("PaymentProcessor.NOTPAYMENT","Incorrect payment class."));
				return false;
			}
			//TODO: check if chosen payment type is allowed
			$payment->OrderID = $this->order->ID;
			$payment->PaidForID = $this->order->ID;
			$payment->PaidForClass = $this->order->class;
			$payment->Amount->Amount = $this->order->TotalOutstanding();
			$payment->write();
			$this->order->Payments()->add($payment);
			return $payment;
		}
		return false;
	}
	
	/**
	 * Determine if an order can be placed.
	 * @param unknown_type $order
	 */
	function canPlace(Order $order){
		if(!$order){
			$this->error(_t("OrderProcessor.NULL","Order does not exist"));
			return false;
		}
		//order status is applicable	
		if(!$order->IsCart()){
			$this->error(_t("OrderProcessor.NOTCART","Order is not a cart"));
			return false;
		}
		//order has products
		if($order->Items()->Count() <= 0){
			$this->error(_t("OrderProcessor.NOITEMS","Order has no items"));
			return false;
		}
		//totals are >= 0?
		//shipping has been selected (if required)
		//modifiers have been calculated
		return true;
	}
	
	/**
	* Send a mail of the order to the client (and another to the admin).
	*
	* @param $emailClass - the class name of the email you wish to send
	* @param $copyToAdmin - true by default, whether it should send a copy to the admin
	*/
	function sendEmail($emailClass, $copyToAdmin = true){
		$from = self::$email_from ? self::$email_from : Email::getAdminEmail();
		$to = $this->order->getLatestEmail();
		$subject = sprintf(self::$receipt_subject ,$this->order->ID);
		$purchaseCompleteMessage = DataObject::get_one('CheckoutPage')->PurchaseComplete;
		$email = new $emailClass();
		$email->setFrom($from);
		$email->setTo($to);
		$email->setSubject($subject);
		if($copyToAdmin){
			$email->setBcc(Email::getAdminEmail());
		}
		$email->populateTemplate(array(
			'PurchaseCompleteMessage' => $purchaseCompleteMessage,
			'Order' => $this->order
		));
		return $email->send();
	}
	
	/**
	* Send the receipt of the order by mail.
	* Precondition: The order payment has been successful
	*/
	function sendReceipt() {
		$this->sendEmail('Order_ReceiptEmail');
		$this->order->ReceiptSent = true;
		$this->order->write();
	}
	
	/**
	* Send a message to the client containing the latest
	* note of {@link OrderStatusLog} and the current status.
	*
	* Used in {@link OrderReport}.
	*
	* @param string $note Optional note-content (instead of using the OrderStatusLog)
	*/
	function sendStatusChange($title, $note = null) {
		if(!$note) {
			$logs = DataObject::get('OrderStatusLog', "\"OrderID\" = {$this->order->ID} AND \"SentToCustomer\" = 1", "\"Created\" DESC", null, 1);
			if($logs) {
				$latestLog = $logs->First();
				$note = $latestLog->Note;
				$title = $latestLog->Title;
			}
		}
		$member = $this->order->Member();
		if(self::$receipt_email) {
			$adminEmail = self::$receipt_email;
		}else {
			$adminEmail = Email::getAdminEmail();
		}
		$e = new Order_statusEmail();
		$e->populateTemplate($this);
		$e->populateTemplate(array(
			"Order" => $this->order,
			"Member" => $member,
			"Note" => $note
		));
		$e->setFrom($adminEmail);
		$e->setSubject($title);
		$e->setTo($member->Email);
		$e->send();
	}
	
	function getError(){
		return $this->error;
	}
	
	private function error($message){
		$this->error = $message;
	}
	
}