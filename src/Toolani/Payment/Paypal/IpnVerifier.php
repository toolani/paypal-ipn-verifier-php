<?php

/*
 *
 * Copyright (c) 2015, toolani
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Toolani\Payment\Paypal;

/**
 * Verifies PayPal IPN data with PayPal.
 */
class IpnVerifier
{    
    const PAYPAL_HOST  = 'www.paypal.com';
    const SANDBOX_HOST = 'www.sandbox.paypal.com';
    
    const STATUS_UNKNOWN  = 'UNKNOWN';     // We didn't get as far as trying to verify
    const STATUS_ERROR    = 'ERROR';       // Tried to verify and something went wrong
    const STATUS_NO_DATA  = 'NO_DATA';     // No data was provided to be verified
    const STATUS_VERIFIED = 'VERIFIED';    // IPN verified OK
    const STATUS_INVALID  = 'INVALID';     // PayPal reports that IPN is invalid
    const STATUS_TIMEOUT  = 'IPN_TIMEOUT'; // Verification request timed out
    
    /**
     *  If true, the paypal sandbox URI www.sandbox.paypal.com is used for the
     *  post back. If false, the live URI www.paypal.com is used. Default false.
     */
    public $useSandbox;
    
    /**
     *  The amount of time, in seconds, to wait for the PayPal server to respond
     *  before timing out. Default 30 seconds.
     */
    public $timeout = 30;
    
    private $postData       = array();
    private $postUri        = '';     
    private $responseStatus = '';
    private $response       = '';
    private $verificationStatus = self::STATUS_UNKNOWN;
    
    /**
     * @param boolean $useSandbox If true, IPNs will be verified using the PayPal sandbox, rather than the live URL.
     */
    public function __construct($useSandbox)
    {
        $this->useSandbox = $useSandbox;
    }
    
    /**
     *  Verifies IPN data with PayPal.
     *
     *  Handles the IPN post back to PayPal and parsing the response. Call this
     *  method from your IPN listener script.
     *  
     *  Throws an exception if there is an error.
     *
     *  @param  array   $postData  POST variables as a key/value array
     *  @return boolean            True if the response came back as "VERIFIED", false if the response came back "INVALID"
     */ 
    public function verify($postData)
    {
        if ($postData === null || empty($postData)) {
            $this->verificationStatus = self::STATUS_NO_DATA;
            
            throw new \Exception("No POST data found.", 103);
        }
        
        $encodedData    = 'cmd=_notify-validate';
        $this->postData = $postData;
            
        foreach ($this->postData as $key => $value) {
            $encodedData .= "&$key=".urlencode($value);
        }
        
        // parent::debug('encoded_data:'.$encodedData);

        $this->curlPost($encodedData);
        
        if (strpos($this->responseStatus, '200') === false) {
            $this->verificationStatus = self::STATUS_ERROR;
            
            throw new \Exception("Invalid response status: ".$this->responseStatus, 104);
        }
        
        if (strpos($this->response, "VERIFIED") !== false) {
            $this->verificationStatus = self::STATUS_VERIFIED;
            
            return true;
        } elseif (strpos($this->response, "INVALID") !== false) {
            $this->verificationStatus = self::STATUS_INVALID;
            
            return false;
        } else {
            // parent::error("Unexpected response from PayPal.");
            $this->verificationStatus = self::STATUS_ERROR;
            
            throw new \Exception("Unexpected response from PayPal.", 105);
        }
    }
    
    /**
     *  Process IPN
     *
     *  An alias for 'verify', provided for compatibility with 
     *  https://github.com/Quixotix/PHP-PayPal-IPN.
     *  
     *  Note that while this method will fall back to using $_POST if given no
     *  $postData, 'verify' will NOT.
     *
     *  @param  array   $postData  POST variables as a key/value array, $_POST will be used directly if not provided
     *  @return boolean            True if the response came back as "VERIFIED", false if the response came back "INVALID"
     */    
    public function processIpn($postData = null)
    {
        if ($postData === null) {
            $postData = $_POST;
        }
        
        return $this->verify($postData);
    }
    
    /**
     * Gets the IPN verification status.
     * 
     * @return string One of the STATUS_ constants from this class.
     */
    public function getVerificationStatusString()
    {
        return $this->verificationStatus;
    }
    
    /**
     *  Get Text Report
     *
     *  This is useful in emails to order processors and system administrators.
     *  Override this method in your own class to customize the report.
     *
     *  @return string A report of the IPN transaction in plain text format
     */
    public function getTextReport()
    {
        $r = '';
        
        // date and POST url
        for ($i=0; $i<80; $i++) { $r .= '-'; }
        $r .= "\n[".date('m/d/Y g:i A').'] - '.$this->getPostUri();
        $r .= " (curl)\n";
        
        // HTTP Response
        for ($i=0; $i<80; $i++) { $r .= '-'; }
        $r .= "\n{$this->getResponse()}\n";
        
        // POST vars
        for ($i=0; $i<80; $i++) { $r .= '-'; }
        $r .= "\n";
        
        foreach ($this->postData as $key => $value) {
            $r .= str_pad($key, 25)."$value\n";
        }
        $r .= "\n\n";
        
        return $r;
    }

    /**
     *  Get POST URI
     *
     *  This can be useful for troubleshooting connection problems. The default URI
     *  would be "ssl://www.sandbox.paypal.com:443/cgi-bin/webscr".
     *
     *  @return string The URI that was used to send the post data back to PayPal
     */
    public function getPostUri()
    {
        return $this->postUri;
    }
    
    /**
     *  Get Response
     *
     *  @return string The entire response from PayPal as a string including all the
     *  HTTP headers
     */
    public function getResponse()
    {
        return $this->response;
    }
    
    /**
     *  Get Response Status
     *
     *  This should be "200" if the post back was successful.
     *
     *  @return string The HTTP response status code from PayPal 
     */
    public function getResponseStatus()
    {
        return $this->responseStatus;
    }

    /**
     *  Post Back Using cURL
     *
     *  Sends the post back to PayPal using the cURL library. Called by
     *  the processIpn() method. Throws an exception if the post fails. 
     *  Populates the response, responseStatus, and postUri properties on 
     *  success.
     *
     *  @param string The post data as a URL encoded string
     */
    protected function curlPost($encodedData)
    {
        $this->postUri = 'https://'.$this->getPaypalHost().'/cgi-bin/webscr';

        // parent::debug('curlPost uri:'.$this->postUri);
        
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CAINFO, dirname(__FILE__)."/../../../../cert/api_cert_chain.crt");
        curl_setopt($ch, CURLOPT_URL, $this->postUri);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $encodedData);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        // Ref: http://stackoverflow.com/questions/26378351/error1408f10bssl-routinesssl3-get-recordwrong-version-number-paypal-maybe
        curl_setopt($ch, CURLOPT_SSLVERSION, 1);
        
        $this->response = curl_exec($ch);
        $this->responseStatus = strval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
        
        if ($this->response === false || $this->responseStatus == '0') {
            $errno = curl_errno($ch);
            $errstr = curl_error($ch);
            
            // parent::error("curl error, errno:$errno; errstr:$errstr");
            
            if($errno == 28) {
                // cURL timeout error
                $this->verificationStatus = self::STATUS_TIMEOUT;
                
                throw new \Exception("cURL timeout error: [$errno] $errstr", 101);
            } else {
                $this->verificationStatus = self::STATUS_ERROR;
                
                throw new \Exception("cURL error: [$errno] $errstr", 100);
            }
        }
    }
    
    private function getPaypalHost()
    {
        if ($this->useSandbox) {
            return self::SANDBOX_HOST;
        } else {
            return self::PAYPAL_HOST;  
        }
    }
}
