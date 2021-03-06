<?php

class jsonContentException extends Exception{
	const INPUT_ERROR=100001;
}

class jsonResponseObject{
	protected $title='';
	protected $errors=array();
	protected $status=array('session_id'=>'','random_token'=>'');
	protected $data=array();
	protected $request=array();
	protected $debug=array();
	public $additional=array();
	
	public $response=array(
		'requestName'		=>'',
		'errors'			=>array(
			'hadErrors'			=>0 ,
			'errors'			=>array()
		),
		'status'			=>array(
			'session_id'		=>'',
			'random_token'		=>''
		),
		'data'				=>array(),
		'request'			=>array(),
		'debug'				=>array()
	);	
	
	
	public function addError($message=NULL,$code=NULL){
		$this->errors[]=array('code'=>$code,'message'=>$message);
	}
	
	public function setStatus($varName=NULL,$value=NULL){
		$this->status[$varName]=$value;
	}
	
	public function setData($varName=NULL,$value=NULL){
		$this->data[$varName]=$value;
	}
	
	public function overwriteData($value=NULL){
		$this->data=$value;
	}
	
	public function setDebug($varName=NULL,$value=NULL){
		$this->debug[$varName]=$value;
	}
	
	public function setRequest($request=NULL){
		$this->request=$request;
	}
	
	public function setTitle($title=NULL){
		$title=(string)$title;
		$this->title=$title;
	}
	
	public function getJson(){
		$response=array_merge(array(
			'title'		=>$this->title,
			'errors'	=>array(
				'hadErrors'		=>(count($this->errors)>0?1:0),
				'errors'		=>$this->errors
			),
			'status'	=>$this->status,
			'data'		=>$this->data,
			'request'	=>$this->request,
			'debug'		=>$this->debug,
		),$this->additional);
		
		$response=json_encode($response);
		return $response;
	}
}



class jsonWrapper{
	public $raw='';
	public $jsonArray=array();
	
	public function __construct($content=NULL){
		$this->raw=$content;
		$content=@json_decode($content,true);
		if(!is_array($content))throw new jsonContentException('Invalid JSON input',jsonContentException::INPUT_ERROR);
		$this->jsonArray=$content;
	}
	
	public function getVersion(){
		$ver=$this->jsonArray['auth']['version'];
		$ver="{$ver['major']}.{$ver['minor']}.{$ver['build']}";
		return $ver;
	}
}

?>