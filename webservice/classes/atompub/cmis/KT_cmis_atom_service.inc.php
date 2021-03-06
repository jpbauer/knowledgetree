<?php

include_once(KT_ATOM_LIB_FOLDER.'KT_atom_service.inc.php');

class KT_cmis_atom_service extends KT_atom_service {

	// override and extend as needed
    
    protected $serviceType = null;
    protected $contentDownload = false;
        
    public public function isContentDownload()
    {
        return $this->contentDownload;
    }
    
    public function notModified()
    {
        return $this->status == self::STATUS_NOT_MODIFIED;
    }
    
    public function getOutput()
    {
        return $this->output;
    }
    
    public function getServiceType()
    {
        return $this->serviceType;
    }
    
    protected function setHeader($header = null, $value = null)
    {
		if ($header) header($header . ': ' . $value);
	}

}
?>