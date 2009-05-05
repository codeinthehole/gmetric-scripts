<?php
/**
 * Phing Task object for updating a Twitter status
 *
 * @author David Winterbottom <david.winterbottom@gmail.com> 
 * @license: Creative Commons Attribution-ShareAlike 2.5 <http://creativecommons.org/licenses/by-sa/2.5/>
 */

require_once "phing/Task.php";

/**
 * Sends an update to a Twitter account
 * 
 * @author David Winterbottom <david@codeinthehole.com> 
 * @version $Id$
 * @package phing.tasks.ext
 */
class TwitterUpdateTask extends Task 
{
    const URL_TEMPLATE_UPDATE    = 'http://twitter.com/statuses/update.xml?status=%s'; 
    const MAXIMUM_MESSAGE_LENGTH = 140;
    
    // Twitter response codes 
    const HTTP_RESPONSE_SUCCESS             = 200;
    const HTTP_RESPONSE_NOT_MODIFIED        = 304;
    const HTTP_RESPONSE_BAD_REQUEST         = 400;
    const HTTP_RESPONSE_BAD_CREDENTIALS     = 401;
    const HTTP_RESPONSE_FORBIDDEN           = 403;
    const HTTP_RESPONSE_BAD_URL             = 404;
    const HTTP_RESPONSE_SERVER_ERROR        = 500;
    const HTTP_RESPONSE_BAD_GATEWAY         = 502;
    const HTTP_RESPONSE_SERVICE_UNAVAILABLE = 503;
    
    /**
     * Friendly translations of the unsuccessful Twitter response codes
     * 
     * See http://apiwiki.twitter.com/REST+API+Documentation#HTTPStatusCodes for more details
     * 
     * @var array
     */
    private static $responseMessages = array(
        self::HTTP_RESPONSE_NOT_MODIFIED        => 'Status hasn\'t changed since last update',
        self::HTTP_RESPONSE_BAD_REQUEST         => 'Bad request - you may have exceeded the rate limit',
        self::HTTP_RESPONSE_BAD_CREDENTIALS     => 'Your username and password did not authenticate',
        self::HTTP_RESPONSE_FORBIDDEN           => 'Forbidden request - Twitter are refusing to honour the request',
        self::HTTP_RESPONSE_BAD_URL             => 'The Twitter URL is invalid',
        self::HTTP_RESPONSE_SERVER_ERROR        => 'There is a problem with the Twitter server',
        self::HTTP_RESPONSE_BAD_GATEWAY         => 'Twitter is either down or being upgraded',
        self::HTTP_RESPONSE_SERVICE_UNAVAILABLE => 'Twitter servers are overloaded and refusing request',
    );
    
    /**
     * @var string
     */
    private $username;
    
    /**
     * @var string
     */
    private $password;
    
    /**
     * Tweet message
     * 
     * @var string
     */
    private $message;
    
    /**
     * Whether to throw an exception if the update fails
     * 
     * @var boolean
     */
    private $checkReturn = false;
    
    /**
     * Username setter
     * 
     * @param string $username
     */
    public function setUsername($username) 
    {
        $this->username = $username;
    }
    
    /**
     * Password setter
     * 
     * @param string $password
     */
    public function setPassword($password) 
    {
        $this->password = $password;
    }
    
    /**
     * Tweet message setter
     * 
     * @param string $message
     */
    public function setMessage($message) 
    {
        $this->message = trim($message);
    }
    
    /**
     * Whether to continue build process if update fails
     * 
     * @param boolean $checkReturn
     */
    public function setCheckReturn($checkReturn)
    {
        $this->checkReturn = (boolean)$checkReturn;
    }
    
    /**
     * Checks that the cURL extension is loaded
     */
    public function init() 
    {
        if (!extension_loaded('curl')) {
            throw new BuildException("Cannot update Twitter", "The cURL extension is not installed");
        }
    }

    /**
     * Make API call to the Twitter API
     * 
     * This creates a cURL connection and makes a POST request to the Twitter API
     * to update a status message.
     */
    public function main() 
    {
        $this->validateProperties();
        
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, array());
        curl_setopt($curlHandle, CURLOPT_URL, $this->getUpdateUrl());
        curl_setopt($curlHandle, CURLOPT_USERPWD, "$this->username:$this->password");
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array('Expect:'));
        $twitterData  = curl_exec($curlHandle);
        $responseCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $errorCode    = curl_errno($curlHandle);
        $errorMessage = curl_error($curlHandle);
        curl_close($curlHandle);
        
        if (0 != $errorCode) {
            throw new BuildException("cURL error ($errorCode): $errorMessage");
        }
        $this->handleTwitterResponseCode((int)$responseCode);
    }
    
    /**
     * Validates the set properties
     */
    private function validateProperties()
    {
        if (!$this->username || !$this->password) {
            throw new BuildException("You must specify a Twitter username and password");
        }
        if (!$this->message) {
            throw new BuildException("You must specify a message");
        } elseif (strlen($this->message) > self::MAXIMUM_MESSAGE_LENGTH) {
            $this->message = substr($this->message, 0, self::MAXIMUM_MESSAGE_LENGTH);
            $this->log("Message is greater than the maximum message length - truncating...", Project::MSG_WARN);
        }
    }
    
    /**
     * Returns the URL for a status update (including the URL-encoded message)
     * 
     * @return string
     */
    private function getUpdateUrl()
    {
        return sprintf(self::URL_TEMPLATE_UPDATE, $this->getEncodedMessage());
    }
    
    /**
     * Returns a URL-encoded message
     * 
     * @return string
     */
    private function getEncodedMessage()
    {
        return urlencode(stripslashes(urldecode($this->message)));
    }
    
    /**
     * Invokes the appopriate behaviour for each Twitter return code
     * 
     * @param int $code
     */
    private function handleTwitterResponseCode($code)
    {
        if ($code == self::HTTP_RESPONSE_SUCCESS) {
            $this->log("Twitter status updated to: '$this->message'", Project::MSG_INFO);
            return;
        }
        if (array_key_exists($code, self::$responseMessages)) {
            $this->handleFailedUpdate(self::$responseMessages[$code]);
        } else {
            $this->handleFailedUpdate("Unrecognised HTTP response code '$code' from Twitter");
        }
    }
    
    /**
     * Invokes appropriate behaviour for a failed update
     * 
     * @param $failureMessage
     */
    private function handleFailedUpdate($failureMessage)
    {
        if (true === $this->checkReturn) {
            throw new BuildException($failureMessage);
        }
        $this->log("Update unsuccessful: $failureMessage", Project::MSG_WARN);   
    }
}