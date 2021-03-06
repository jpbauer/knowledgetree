<?php

class client_service{
	public $Response;
	public $KT;
	public $Request;
	public $AuthInfo;
	
	public function __construct(&$ResponseObject,&$KT_Instance,&$Request,&$AuthInfo){
		// set the response object
//		if(get_class($ResponseObject)=='jsonResponseObject'){
//			$this->Response=&$ResponseObject;
//		}else{
//			$this->Response=new jsonResponseObject();
//		}

		
		$this->Response=&$ResponseObject;
		$this->KT=&$KT_Instance;
		$this->AuthInfo=&$AuthInfo;
		$this->Request=&$Request;
	}
	
	protected function addResponse($name,$value){
		$this->Response->setData($name,$value);
	}	
	
	protected function addDebug($name,$value){
		$this->Response->setDebug($name,$value);
	}

	protected function setResponse($value){
		$this->Response->overwriteData($value);
	}

	protected function addError($message,$code){
		$this->Response->addError($message,$code);
	}
	
	protected function xlate($var=NULL){
		return $var;
	}
	
}

?>