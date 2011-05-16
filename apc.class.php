<?php

define("VERSION", "1.0");

class apcPduConnect {
    private $_ip;
    private $_ftpConnection;
    private $_ftpUsername;
    private $_ftpPassword;
    private $_connected;
    
    function __construct() {
        $this->_connected = FALSE;
    }
    
    function __destruct() {
	if($this->_connected) {
	    $this->close();
	}
    }
    
    /**
     * Sets PDU IP
     * @param string $passedIP IP address to set
     * @return bool true on set, false on failure
     */
    
    public function setIP($passedIP) {
        if(!preg_match('/^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:[.](?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3}$/', $passedIP)) {
            trigger_error(htmlentities($passedIP) . " is not an IP");
            return FALSE;
        }
        $this->_ip = $passedIP;
        return TRUE;
    }
    
    /**
     * Returns current PDU IP
     * @return string IP address
     */
    
    public function getIP() {
        if(isset($this->_ip)) {
            return $this->_ip;   
        }
        
        trigger_error("IP is not set");
        return FALSE;
    }
    
    /**
     * Sets PDU username
     * @param string $username username to set
     * @return bool true on set, false on failure
     */
    public function setUsername($username) {
        $this->_ftpUsername = $username;
        return TRUE;
    }
    
    /**
     * Returns current PDU username
     * @return string username
     */
    public function getUsername() {
        if(isset($this->_ftpUsername)) {
            return $this->_ftpUsername;
        }
        
        trigger_error("FTP username is not set");
        return FALSE;
    }
    
    /**
     * Sets PDU password
     * @param string $password password to set
     * @return bool true on set, false on failure
     */
    public function setPassword($password) {
        $this->_ftpPassword = $password;
        return TRUE;
    }
    
    /**
     * Returns current PDU password
     * @return string password
     */
    public function getPassword() {
        if(isset($this->_ftpPassword)) {
            return $this->_ftpPassword;
        }
        
        trigger_error("FTP password is not set");
        return FALSE;
    }
    
    /**
     * Open connection to PDU FTP server
     * @return bool true on successful connect, false otherwise
     */
    public function connect() {
        if(!isset($this->_ip) || !isset($this->_ftpUsername) || !isset($this->_ftpPassword)) {
            trigger_error("IP, username, or password is not set");
            return FALSE;
        }
        
        $this->_ftpConnection = ftp_connect($this->_ip);
        
        if(!$this->_ftpConnection) {
            trigger_error("Couldn't open FTP connection to " . $this->_ip);
            return FALSE;
        }
        
        if(!ftp_login($this->_ftpConnection, $this->_ftpUsername, $this->_ftpPassword)) {
            trigger_error("Couldn't login to FTP");
            return FALSE;
        }
        
        $this->_connected = TRUE;
        
        return TRUE;
    }
    
    /**
     * Sets PASV mode 
     * @param bool $mode TRUE to set PASV on, FALSE to turn off
     * @return bool true on success, false otherwise
     */
    public function pasv($mode = TRUE) {
	return ftp_pasv($this->_ftpConnection, $mode);
    }
    
    /**
     * Downloads config from PDU to local machine
     * @return bool true on success, false on failure
     */
    public function getConfig() {
        if(!$this->_connected) {
            trigger_error("Can't get config without being connected");
            return FALSE;
        }
        
        if(!ftp_get($this->_ftpConnection, 'configs/' . $this->_ip . '.ini', 'config.ini', FTP_ASCII)) {
            trigger_error("Couldn't get config.ini");
            return FALSE;
        }
        
        if(file_exists('configs/' . $this->_ip . '.ini')) {
            return TRUE;   
        }
        
        trigger_error("Transfer appeared successful but config does not exist.");
        return FALSE;
    }
    
    /**
     * Closes the open connection
     * @return bool true on successful close, false otherwise
     */
    public function close() {
        if($this->_connected) {
            if(ftp_close($this->_ftpConnection)) {
                $this->_ftpConnection = FALSE;
                $this->_connected = FALSE;
                return TRUE;
            } else {
                trigger_error("Couldn't close connection");
                return FALSE;
            }
        } else {
            trigger_error("Tried to close non-existent connection");
            return FALSE;
        }
    }
    
    /**
     * Parses 'configs/<ip>.ini' into an array of config
     * directives and values
     * @return array parsed config or false on failure
     */
    public function parseConfig() {
        $configArray = file('configs/' . $this->_ip . '.ini');
        
        if($configArray === FALSE || sizeof($configArray) <= 1) {
            trigger_error("Couldn't read config");
            return FALSE;
        }
        
        $config = array();
        $configDirective = '';
        
        foreach($configArray as $lineNumber => $line) {
            $line = trim($line);
            
            // Filter out comments
            if(preg_match('/^;/', $line)) {
                continue;
            }
            
            // Config directive
            if(preg_match('/^\[/', $line)) {
                $configDirective = substr(substr($line, 1), 0, -1);
                continue;
            }
            
            // Filter out blank lines
            if($line == '') {
                continue;
            }
            
            // Looks to be a regular config directive
            // Split on = and place into config array
            
            $lineSplit = explode("=", $line);
            
            $config[$configDirective][$lineSplit[0]] = $lineSplit[1];
            
            unset($lineSplit);
            
        }
        
        return $config;
    }
    
    /**
     * Writes the passed config array to 'configs/<ip>.ini'
     * @param array $config configuration array to write
     * @return bool true on success, false on failure
     */
    public function writeConfig($config) {
        if(!is_array($config)) {
            trigger_error("writeConfig must be called with an array");
            return FALSE;
        }
        
        if(!$this->_connected) {
            trigger_error("Can't write config when not connected");
            return FALSE;
        }
        
        $toWrite  = "; Config written at " . date('m-d-Y h:i:s') . "\n";
        $toWrite .= "; PDU writer version " . VERSION . "\n";
        $toWrite .= "; 2011 Kevin O'Connor - kjoconnor@gmail.com\n";
        
        foreach($config as $directive => $values) {
            $toWrite .= '[' . $directive . "]\n";
            
            foreach($values as $configName => $configValue) {
                $toWrite .= $configName . "=" . $configValue . "\n";
            }
        }
        
        if(!file_put_contents('configs/' . $this->_ip . '.ini_updated', $toWrite)) {
            trigger_error("Couldn't write temporary config");
            return FALSE;
        }
        
        if(!ftp_put($this->_ftpConnection, 'config.ini', 'configs/' . $this->_ip . '.ini_updated', FTP_ASCII)) {
            trigger_error("Couldn't ftp_put");
            return FALSE;
        }
	
	unlink('configs/' . $this->_ip . '.ini_updated');
	unlink('configs/' . $this->_ip . '.ini');
        
        return TRUE;
    }
}

?>