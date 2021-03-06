<?php
class KT_atom_responseFeed extends KT_atom_baseDoc {

	private $baseURI=NULL;
	private $feed=NULL;


	public function __construct($baseURI=NULL,$title=NULL,$link=NULL,$updated=NULL,$author=NULL,$id=NULL){
		parent::__construct();
		$this->constructHeader();
		$this->baseURI=$baseURI;
	}

	private function constructHeader(){
		$feed=$this->newElement('feed');
		$feed->appendChild($this->newAttr('xmlns','http://www.w3.org/2005/Atom'));
		$this->feed=&$feed;
		$this->DOM->appendChild($this->feed);
	}

	public function &newEntry(){
		$entry=$this->newElement('entry');
		$this->feed->appendChild($entry);
		return $entry;
	}

	public function &newField($name=NULL,$value=NULL,&$attachToNode=NULL){
		$field=$this->newElement($name,$value);
		if(isset($attachToNode))$attachToNode->appendChild($field);
		return $field;
	}

	public function render(){
		return $this->formatXmlString(trim($this->DOM->saveXML()));
	}


}

class KT_atom_ResponseFeed_GET extends KT_atom_responseFeed{}
class KT_atom_ResponseFeed_PUT extends KT_atom_responseFeed{}
class KT_atom_ResponseFeed_POST extends KT_atom_responseFeed{}
class KT_atom_ResponseFeed_DELETE extends KT_atom_responseFeed{}

?>