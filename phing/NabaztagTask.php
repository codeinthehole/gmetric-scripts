<?php
/**
 * Phing Task object for interacting with a Nabaztag
 *
 * @author David Winterbottom <david.winterbottom@gmail.com> 
 * @license: Creative Commons Attribution-ShareAlike 2.5 <http://creativecommons.org/licenses/by-sa/2.5/>
 */

require_once "phing/Task.php";

/**
 * Sends an update to a Twitter account
 * 
 * See http://doc.nabaztag.com/api/home.html for details of the API
 * 
 * @author David Winterbottom <david@codeinthehole.com> 
 * @package phing.tasks.my
 */
class NabaztagTask extends Task 
{
    const BASE_URL = 'http://api.nabaztag.com/vl/FR/api.jsp'; 
    
    // Twitter response codes 
    const HTTP_RESPONSE_SUCCESS = 200;
    const HTTP_RESPONSE_NOT_MODIFIED = 304;
    const HTTP_RESPONSE_BAD_REQUEST = 400;
    const HTTP_RESPONSE_BAD_CREDENTIALS = 401;
    const HTTP_RESPONSE_FORBIDDEN = 403;
    const HTTP_RESPONSE_BAD_URL = 404;
    const HTTP_RESPONSE_SERVER_ERROR = 500;
    const HTTP_RESPONSE_BAD_GATEWAY = 502;
    const HTTP_RESPONSE_SERVICE_UNAVAILABLE = 503;
    
    /**
     * @var string
     */
    private $serialNum;
    
    /**
     * @var string
     */
    private $token;
    
    /**
     * @var int
     */
    private $leftEarPosition = null;
    
    /**
     * @var int
     */
    private $rightEarPosition = null;
    
    /**
     * @var string
     */
    private $message = null;
    
    /**
     * @var int
     */
    private $messageId = null;
    
    /**
     * @var string
     */
    private $voice = null;
    
    /**
     * @var string
     */
    private $choreographySequence = null;
    
    /**
     * @var string
     */
    private $choreographySequenceTitle = null;
    
    /**
     * @var string
     */
    private $urlList = null;
    
    /**
     * Whether to throw an exception if the update fails
     * 
     * @var boolean
     */
    private $checkReturn = false;
    
    /**
     * @param string $serialNum
     */
    public function setSerialNum($serialNum) 
    {
        $this->serialNum = $serialNum;
    }
    
    /**
     * @param string $token
     */
    public function setToken($token) 
    {
        $this->token = $token;
    }
    
    /**
     * @param string $position An integer from 0 - 16
     */
    public function setLeftEarPosition($position)
    {
        $this->leftEarPosition = (int)$position;
        $this->log("Setting left ear position to ".$this->leftEarPosition);
    }
    
    /**
     * @param string $position An integer from 0 - 16
     */
    public function setRightEarPosition($position)
    {
        $this->rightEarPosition = (int)$position;
        $this->log("Setting right ear position to ".$this->rightEarPosition);
    }
    
    /**
     * @param string $message
     */
    public function setMessage($message) 
    {
        $this->message = trim($message);
    }
    
    /**
     * @param int $message
     */
    public function setMessageId($messageId) 
    {
        $this->messageId = (int)$messageId;
    }
    
    /**
     * Complete list available from calling URL with action=9
     * 
     * Sample voices:
     * UK-Edwin
     * US-Billye
     * UK-Penelope
     * UK-Shirley
     * AU-Jon
     * US-Liberty
     * ...
     * 
     * @var string
     */
    public function setVoice($voice) 
    {
        $this->voice = trim($voice);
    }
    
    /**
     * For ears use
     * <time>,motor,<ear(0,1)>,<angle(0-180)>,0,<direction(0,1)
     * 
     * For LEDs use
     * <time>,led,<led(0,1,2,3,4)>,<rgbcolour>
     * 
     * Note time is measured in units of 100ms
     * 
     * @param string $choreographySequence
     */
    public function setChoreography($choreographySequence)
    {
        $this->choreographySequence = $choreographySequence;
    }
    
    /**
     * @param string $choreographySequence
     */
    public function setChoreographyTitle($choreographySequenceTitle)
    {
        $this->choreographySequenceTitle = $choreographySequenceTitle;
    }
    
    /**
     * @param string $urlList
     */
    public function setUrlList($urlList)
    {
        $this->urlList = $urlList;
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
            throw new BuildException("Cannot communicate with Nabaztag server", "The cURL extension is not installed");
        }
    }

    /**
     * Make API call to the Nabaztag API
     * 
     * This creates a cURL connection and makes a POST request to the Twitter API
     * to update a status message.
     */
    public function main() 
    {
        $this->validateProperties();
        $urlForEvent = $this->getNabaztagUrl();
        $this->log("Sending request to $urlForEvent");
        
        $curlHandle = curl_init();
        curl_setopt($curlHandle, CURLOPT_URL, $urlForEvent);
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array('Expect:'));
        $responseData = curl_exec($curlHandle);
        $responseCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $errorCode = curl_errno($curlHandle);
        curl_close($curlHandle);
        
        if (0 != $errorCode) {
            throw new BuildException("cURL error ($errorCode): $errorMessage");
        }
        if (self::HTTP_RESPONSE_SUCCESS !== $responseCode) {
            throw new BuildException("Received HTTP response code $responseCode");
        }
        $this->handleResponseData($responseData);
    }
    
    /**
     * Validates the set properties
     */
    private function validateProperties()
    {
        if (!$this->serialNum || !$this->token) {
            throw new BuildException("You must specify a Nabaztag serial number and token - these can be found in the preferences section of my.nabaztag.com");
        }
        if ($this->leftEarPosition < 0 || $this->leftEarPosition > 16) {
            throw new BuildException(sprintf("The left ear position must be between 0 and 16 (currently %s)", $this->leftEarPosition));
        }
        if ($this->rightEarPosition < 0 || $this->rightEarPosition > 16) {
            throw new BuildException(sprintf("The right ear position must be between 0 and 16 (currently %s)", $this->rightEarPosition));
        }
    }
    
    /**
     * Returns the URL for sending an event based on the set properties
     * 
     * @return string
     */
    private function getNabaztagUrl()
    {
        $url = self::BASE_URL;
        $queryParams = array(
            'sn' => $this->serialNum,
            'token' => $this->token,
        );
        
        // Add ear position params if set
        $sendLeftEarPosition = !is_null($this->leftEarPosition);
        $sendRightEarPosition = !is_null($this->rightEarPosition);
        if ($sendLeftEarPosition || $sendRightEarPosition) {
            $queryParams['ears'] = 'ok';
            if ($sendLeftEarPosition) $queryParams['posleft'] = $this->leftEarPosition;
            if ($sendLeftEarPosition) $queryParams['posright'] = $this->rightEarPosition;
        }
        
        // Message and voice data
        if (!is_null($this->message)) {
            $queryParams['tts'] = $this->getEncodedMessage($this->message);
        }
        if (!is_null($this->messageId)) {
            $queryParams['idmessage'] = $this->messageId;
        }
        if (!is_null($this->voice)) {
            $queryParams['voice'] = $this->voice;
        }
        
        // Choreography
        if (!is_null($this->choreographySequence)) {
            $queryParams['chor'] = $this->choreographySequence;
        } 
        if (!is_null($this->choreographySequenceTitle)) {
            $queryParams['chortitle'] = $this->choreographySequenceTitle;
        }
        
        // URLs for streaming audio
        if (!is_null($this->urlList)) {
            $queryParams['urlList'] = $this->urlList;
        }
        
        // Construct URL
        $queryPairs = array();
        foreach ($queryParams as $key => $value) {
            $queryPairs[] = sprintf("%s=%s", $key, $value);
        }
        return self::BASE_URL.'?'.implode('&', $queryPairs);
    }
    
    /**
     * Returns a URL-encoded message
     * 
     * @return string
     */
    private function getEncodedMessage($rawMessage)
    {
        return urlencode(stripslashes(urldecode($rawMessage)));
    }
    
    /**
     * Invokes the appopriate behaviour for each Twitter return code
     * 
     * @param int $code
     */
    private function handleResponseData($xmlData)
    {
        $simpleXml = new SimpleXmlElement($xmlData);
        $message = (string)$simpleXml->message;
        $comment = (string)$simpleXml->comment;
        if ($message) {
            $this->log("Nabaztag response: $message: $comment");
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