<?php
/**
 * Phing Task object for interacting with a Nabaztag
 *
 * @author David Winterbottom <david.winterbottom@gmail.com> 
 * @license: Creative Commons Attribution-ShareAlike 2.5 <http://creativecommons.org/licenses/by-sa/2.5/>
 */

require_once "phing/task/my/NabaztagTask.php";

/**
 * Standardised build notifications
 * 
 * @author David Winterbottom <david@codeinthehole.com> 
 * @package phing.tasks.my
 */
class NabaztagBuildStatusTask extends NabaztagTask
{
    const SUCCESS = 'success';
    const RECOVERY = 'recovery';
    const FAILURE = 'failure';
    
    /**
     * Should be one of "success", "failure", "recovery"
     * 
     * @var string
     */
    private $status;

    /**
     * @param string $status
     */
    public function setStatus($status) 
    {
        if (!in_array($status, array(self::SUCCESS, self::RECOVERY, self::FAILURE))) {
            throw new BuildException("Invalid status specified ($status)");
        }
        $this->status = $status;
    }

    /**
     * Set the event attributes depending on the build status then call parent main() method
     */
    public function main() 
    {
        switch ($this->status) {
            case self::SUCCESS:
                $this->message = "The build was successful";
                break;
            case self::FAILURE:
                $this->message = "The build was failure";
                break;
            case self::RECOVERY:
                $this->message = "The build has been recovered";
                break;    
            default:
        }
        parent::main();
    }
}