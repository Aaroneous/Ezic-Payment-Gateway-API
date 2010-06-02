<?php

 /**
 * Abstract Gateway Object Class
 *
 * @author Aaron W.
 **/

abstract class gatewayObj {
	
	
	/**
	 * Bind an array to current object
	 *
	 * @return void
	 * @param array $array
	 * @author Aaron W.
	 **/
	
	public function bind($array) {
		foreach ($array as $key=>$val) {
			$this->$key = $val;
		}
	}

	
	
	/**
	 * Verify current object and then return it as an array
	 *
	 * @return array
	 * @author Aaron W.
	 **/
	public function flatten() {
		$this->verify();
		$ret = array();
		
		foreach ($this as $key=>$val) {
			$ret[$key] = $val;
		}
		
		return $ret;
	}
	
	
	/**
	 * Verify all of the public properties are not null
	 *
	 * @return bool
	 * @access private
	 * @author Aaron W.
	 **/
	private function verify() {
		foreach ($this as $key=>$val) {
			if (empty($val)) {
				throw new Exception ('Unable to verify: '.$key.' is empty');
			}
		}
		return true;
	}
		
}


/**
 * Abstract Payment Class
 *
 * @author Aaron W.
 **/

abstract class payment extends gatewayObj {
	
	public $pay_type;
	public $amount;
	
}


/**
 * Credit Card Class
 *
 * @author Aaron W.
 **/

class ccPayment extends payment {
	
	public $card_number;
	public $card_expire;
	public $cvv2;
	
	public function __construct() {
		$this->pay_type = 'C';
	}
	
}


/**
 * Recurring Payment Class
 *
 * @author Aaron W.
 **/

class recurringCCPayment extends ccPayment {
	
	public $recurring_amount;
	public $recurring_period;
	public $recurring_count;
}


/**
 * Customer Class
 *
 * @author Aaron W.
 **/

class customer extends gatewayObj {
	
	public $bill_name1;
	public $bill_name2;
	public $bill_street;
	public $bill_zip;
	public $bill_country;
	public $bill_city;
	public $bill_state;
	public $cust_ip;
	
	
}


/**
 * Gateway Implementation for Ezic.com
 *
 * @author Aaron W.
 **/

class ezic_gateway {
	
	/**
	 * Account ID
	 *
	 * @static string
	 **/
	static $accountId    = '';
	
	/**
	 * Gateway Host
	 *
	 * @var string
	 **/
	public $host         = 'secure-dm3.ezic.com';
	
	/**
	 * Gateway Endpoint
	 *
	 * @var string
	 **/
	public $endPoint     = '/gw/sas/direct3.1';
	
	/**
	 * Gateway Port
	 *
	 * @var int
	 **/
	public $port         = 443;
	
	/**
	 * Enable Test Mode
	 *
	 * @var bool
	 **/
	public $testMode     = true;
	
	/**
	 * Gateway Request Method
	 *
	 * @var string
	 **/
	public $method       = 'GET';
	
	/**
	 * Dynamic IP Security Code
	 *
	 * @var string
	 **/
	public $dynipSecCode = '';
	
	/**
	 * Site Tag
	 *
	 * @var string
	 **/
	public $siteTag      = '';
	
	/**
	 * Customer Info
	 *
	 * @var array
	 **/
	public $customerInfo = array();
	
	/**
	 * Payment Info
	 *
	 * @var array
	 **/
	public $paymentInfo  = array();
	
	/**
	 * Current Transaction ID
	 *
	 * @var int
	 * @access private
	 **/
	private $transId     = null;
	
	/**
	 * Request Array
	 *
	 * @var array
	 * @access private
	 **/
	private $request     = array();
	
	/**
	 * Response Array
	 *
	 * @var array
	 * @access private
	 **/
	private $response    = array();

	
	
	/**
	 * Constructor
	 *
	 * @param int $accountId
	 * @return void
	 * @author Aaron W.
	 **/
	
	public function __construct($accountId = null) {
		
		if ($accountId === null) {
			throw new Exception ('Unable to initialize without an account ID');
		}

		$this->setAccountId($accountId);
		
		if ($this->testMode === true) {
			$this->request['disable_email_receipts'] = 'true';
		}
		
		$this->transId = $this->getNewTransId();
		
	}
	
	
	/**
	 * Return Current Transaction ID
	 *
	 * @return int
	 * @author Aaron W.
	 **/
	public function getTransId() {
		return $this->transId;
	}
	
	
	/**
	 * Authorize A Transaction
	 * 
	 * @param object $customer
	 * @param object $payment
	 * @return mixed
	 * @author Aaron W.
	 **/
	public function auth($customer,$payment) {
		
		$this->request['tran_type'] = 'A';
		
		$this->customerInfo = $customer->flatten();
		$this->paymentInfo = $payment->flatten();
		
		return $this->transact();
	}
	
	
	/**
	 * Sale Transaction
	 *
	 * @param object $customer
	 * @param object $payment
	 * @return mixed
	 * @author Aaron W.
	 **/
	public function sale($customer,$payment) {
		
		$this->request['tran_type'] = 'S';
		
		$this->customerInfo = $customer->flatten();
		$this->paymentInfo = $payment->flatten();
		
		return $this->transact();
	}
	
	
	/**
	 * Recurring Sale
	 *
	 * @param object $customer
	 * @param object $paymentInfo Must be a recurringPayment obj
	 * @return mixed
	 * @author Aaron W.
	 **/
	public function recurringSale($customer, $payment) {
		
		$this->request['tran_type'] = 'S';
		
		if (get_class($payment) != 'recurringCCPayment') {
			throw new Exception ('Payment object must be of type "recurringCCPayment"');
		}
		
		$this->customerInfo = $customer->flatten();
		$this->paymentInfo = $payment->flatten();
		
		return $this->transact();
	}
	
	
	/**
	 * Refund
	 *
	 * @param int $origId
	 * @return mixed
	 * @author Aaron W.
	 **/
	public function refund($origId) {
		$this->request['tran_type'] = 'R';
		$this->request['orig_id'] = $origId;
		
		return $this->transact();
	}
	
	
	/**
	 * Set Account ID
	 *
	 * @static
	 * @param int $id
	 * @return void
	 * @author Aaron W.
	 **/
	static function setAccountId($id) {
		self::$accountId = $id;
	}
	
	
	/**
	 * Process A Transaction
	 * 
	 * @access private
	 * @return Mixed
	 * @author Aaron W.
	 **/
	private function transact() {
		
		$this->request = array_merge($this->request,$this->customerInfo,$this->paymentInfo);
		
		$url = $this->host.$this->endPoint;
		
		$resp = $this->req($url, $this->request);
		
		if ($resp[1]['info']['http_code'] != 200) {
			preg_match('/HTTP\/.* ([0-9]+) .*/', $resp[1]['header'], $status);
			$status = str_replace('HTTP/1.0 '.$resp[1]['info']['http_code'].' ','',$status);
			return array(false, $status[1]);
		} else {
			return array(true,$resp[0]);
		}
	}
	
	
	/**
	 * Return A New Transaction ID
	 *
	 * @access private
	 * @return int
	 * @author Aaron W.
	 **/
	private function getNewTransId() {
		
		$url = $this->host.'/gw/sas/getid3.1';
		$resp = $this->req($url);
		
		if (!ctype_digit($resp[0])) {
			throw new Exception ('Invalid transaction id');
		}
		
		return $resp[0];
	}
	
	
	/**
	 * Make a cURL request to Host's Endpoint
	 *
	 * @access private
	 * @param string $url
	 * @param array $request
	 * @return array
	 * @author Aaron W.
	 **/
	private function req ($url, $request = null) {
		
		if (!empty($request)) {
			
			$request['account_id'] = self::$accountId;
			
			if (!empty($this->transId)) {
				$request['trans_id'] = $this->transId;
			}
			
			if (!empty($this->dynipSecCode)) {
				$request['dynip_sec_code'] = $this->dynipSecCode;
			}
			
			if (!empty($this->siteTag)) {
				$request['site_tag'] = $this->siteTag;
			}
			
			$request = http_build_query($request);
			
		}
		
		$ch = curl_init();
				
		if ($this->method == 'POST') {
			
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
			
		} else if (!empty($request)) {
			
			$url .= '?'.$request;
		}
		
		curl_setopt($ch, CURLOPT_URL, 'https://'.$url);
		curl_setopt($ch, CURLOPT_PORT, $this->port);
		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/4.0 (compatible; MSIE 5.01; Windows NT 5.0)");
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		curl_setopt($ch, CURLOPT_HEADER, true);
		
		$resp = curl_exec($ch);
		
		$respInfo = curl_getinfo($ch);
		
		curl_close($ch);
		
		$header = substr($resp, 0, $respInfo['header_size']);
		$this->response = rtrim(substr($resp, $respInfo['header_size']),"\n");
		
		return array($this->response, array('header'=>$header,'info'=>$respInfo));
		
	}
	
}