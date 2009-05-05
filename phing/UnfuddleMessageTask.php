<?php
/**
 * Phing Task object for sending an Unfuddle message
 *
 * @author David Winterbottom <david.winterbottom@gmail.com> 
 * @license: Creative Commons Attribution-ShareAlike 2.5 <http://creativecommons.org/licenses/by-sa/2.5/>
 */

require_once "phing/Task.php";

/**
 * Sends a new message to an Unfuddle.com project
 * 
 * @author David Winterbottom <david@codeinthehole.com> 
 * @version $Id$
 * @package phing.tasks.ext
 */
class UnfuddleMessageTask extends Task 
{
    const URL_TEMPLATE_UPDATE = 'http://%s.unfuddle.com/api/v1/projects/%d/messages'; 
    
    // Twitter response codes 
    const HTTP_RESPONSE_OK                  = 200;
    const HTTP_RESPONSE_CREATED             = 201;
    const HTTP_RESPONSE_BAD_REQUEST         = 400;
    const HTTP_RESPONSE_BAD_CREDENTIALS     = 401;
    const HTTP_RESPONSE_BAD_URL             = 404;
    const HTTP_RESPONSE_METHOD_NOT_ALLOWED  = 405;
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
        self::HTTP_RESPONSE_BAD_REQUEST         => 'Bad request - you may have exceeded the rate limit',
        self::HTTP_RESPONSE_BAD_CREDENTIALS     => 'Your username and password did not authenticate',
        self::HTTP_RESPONSE_BAD_URL             => 'The Unfuddle URL is invalid',
        self::HTTP_RESPONSE_METHOD_NOT_ALLOWED  => 'The specified HTTP verb is not allowed',
        self::HTTP_RESPONSE_SERVER_ERROR        => 'There is a problem with the Unfuddle server',
        self::HTTP_RESPONSE_BAD_GATEWAY         => 'Unfuddle is either down or being upgraded',
        self::HTTP_RESPONSE_SERVICE_UNAVAILABLE => 'Unfuddle servers are refusing request',
    );
    
    /**
     * @var string
     */
    private $subdomain;
    
    /**
     * @var int
     */
    private $projectId;
    
    /**
     * @var string
     */
    private $username;
    
    /**
     * @var string
     */
    private $password;
    
    /**
     * @var string
     */
    private $title;
    
    /**
     * @var string
     */
    private $body;
    
    /**
     * Category ids to use for message
     * 
     * @var array
     */
    private $categoryIds;
    
    /**
     * Whether to throw an exception if the update fails
     * 
     * @var boolean
     */
    private $checkReturn = false;
    
    /**
     * Account subdomain setter
     * 
     * @param string $subdomain
     */
    public function setSubdomain($subdomain) 
    {
        $this->subdomain = $subdomain;
    }
    
    /**
     * Project id setter
     * 
     * @param string $username
     */
    public function setProjectId($projectId) 
    {
        $this->projectId = (int)$projectId;
    }
    
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
     * Title setter
     * 
     * @param string $message
     */
    public function setTitle($title) 
    {
        $this->title = $title;
    }
    
    /**
     * Message setter
     * 
     * @param string $message
     */
    public function setBody($body) 
    {
        $this->body = $body;
    }
    
    /**
     * Category setter for a single category
     * 
     * @param string $categories
     */
    public function setCategoryId($categoryId) 
    {
        $this->categoryIds = array((int)$categoryId);
    }
    
    /**
     * Category setter for a list of categories
     * 
     * @param string $categories A comma-separated list of category ids
     */
    public function setCategoryIds($categoryIdList) 
    {
        $this->categoryIds = explode(",", $categoryIdList);
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
            throw new BuildException("Cannot update Unfuddle", "The cURL extension is not installed");
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
        curl_setopt($curlHandle, CURLOPT_URL, $this->getUpdateUrl());
        curl_setopt($curlHandle, CURLOPT_USERPWD, "$this->username:$this->password");
        curl_setopt($curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curlHandle, CURLOPT_HTTPHEADER, array('Accept: application/xml', 'Content-type: application/xml'));
        curl_setopt($curlHandle, CURLOPT_POST, true);
        curl_setopt($curlHandle, CURLOPT_POSTFIELDS, $this->getRequestBodyXml());
        $responseData = curl_exec($curlHandle);
        $responseCode = curl_getinfo($curlHandle, CURLINFO_HTTP_CODE);
        $errorCode    = curl_errno($curlHandle);
        $errorMessage = curl_error($curlHandle);
        curl_close($curlHandle);
        
        if (0 != $errorCode) {
            throw new BuildException("cURL error ($errorCode): $errorMessage");
        }
        $this->handleResponseCode((int)$responseCode);
    }
    
    /**
     * Validates the set properties
     */
    private function validateProperties()
    {
        if (!$this->subdomain) {
            throw new BuildException("You must specify a subdomain");
        }
        if (!$this->projectId) {
            throw new BuildException("You must specify a project id");
        }
        if (!$this->username || !$this->password) {
            throw new BuildException("You must specify an Unfuddle username and password");
        }
        if (!$this->title) {
            throw new BuildException("You must specify a message title");
        }
    }
    
    /**
     * Returns the URL for a new message
     * 
     * @return string
     */
    private function getUpdateUrl()
    {
        return sprintf(self::URL_TEMPLATE_UPDATE, $this->subdomain, $this->projectId);
    }
    
    /**
     * Returns the XMl for the POST request
     * 
     * See http://unfuddle.com/docs/api/data_models#message for API
     * information about creating new messages.
     * 
     * @return string
     */
    private function getRequestBodyXml()
    {
        $xmlWriter = new XMLWriter();
        $xmlWriter->openMemory();
        $xmlWriter->startElement('message');
        $xmlWriter->writeElement('title', $this->title);
        $xmlWriter->writeElement('body', $this->body);
        
        if ($this->categoryIds) {
            $xmlWriter->startElement('categories');
            foreach ($this->categoryIds as $categoryId) {
                $xmlWriter->startElement('category');
                $xmlWriter->writeAttribute('id', "$categoryId");
                $xmlWriter->endElement();
            }
            $xmlWriter->endElement();
        }
        $xmlWriter->endElement();
        return $xmlWriter->flush();
    }
    
    /**
     * Invokes the appopriate behaviour for each Twitter return code
     * 
     * @param int $code
     */
    private function handleResponseCode($code)
    {
        if ($code == self::HTTP_RESPONSE_CREATED) {
            $this->log("New Unfuddle message posted: '$this->title'", Project::MSG_INFO);
            return;
        }
        if (array_key_exists($code, self::$responseMessages)) {
            $this->handleFailedUpdate(self::$responseMessages[$code]);
        } else {
            $this->handleFailedUpdate("Unrecognised HTTP response code '$code' from Unfuddle");
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
        $this->log("New Unfuddle message unsuccessful: $failureMessage", Project::MSG_WARN);   
    }
}