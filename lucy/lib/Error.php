<?php

/**
 * Error
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class Error
{
    private $errorList = array();

    public static $instance = null;

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct () { }

    /**
     * getInstance 
     * 
     * @return object
     */
    public static function getInstance ()
    {
        if (!isset(self::$instance))
        {
            self::$instance = new Error();
        }

        return self::$instance;
    }

    /**
     * hasError
     * 
     * Checks whether any errors have occurred.
     * 
     * @return boolean
     */
    public function hasError ()
    {
        if (!empty($this->errorList))
        {
            return true;
        }

        return false;
    }

    /**
     * displayError 
     * 
     * Prints out the error(s).
     * Also allows you to add a new error, then print errors.
     * 
     * @param null|array $params
     * 
     * @return null
     */
    public function displayError ($params = null)
    {
        if (is_array($params))
        {
            $this->add($params);
        }

        if (!$this->hasError())
        {
            return;
        }

        echo '<div class="alert alert-danger" role="alert">';

        foreach ($this->errorList as $error)
        {
            echo '<h2>'.$error['title'].'</h2>';
            echo '<p><b>'.$error['message'].'</b></p>';

            if (defined('DEBUG') && DEBUG)
            {
                if (isset($error['object']))
                {
                    echo '<pre>';
                    print_r($error['object']);
                    echo '</pre>';
                }

                echo '<p><b>'._('File').'</b>: '.$error['file'].'</p>';
                echo '<p><b>'._('Line').'</b>: '.$error['line'].'</p>';

                if (isset($error['sql']))
                {
                    echo '<p><b>'._('SQL').'</b>:<br/>'.$error['sql'].'</p>';
                }

            }
        }

        if (defined('DEBUG') && DEBUG)
        {
            echo '<p><b>'._('PHP').'</b>: '.PHP_VERSION.' ('.PHP_OS.')</p>';
        }

        echo '</div>';
    }

    /**
     * displayJsonError 
     * 
     * Prints out the error(s) in JSON.
     * Also allows you to add a new error, then print errors.
     * 
     * @param null|array $params
     * 
     * @return null
     */
    public function displayJsonError ($params = null)
    {
        if (is_array($params))
        {
            $this->add($params);
        }

        if (!$this->hasError())
        {
            return;
        }

        $return = array(
            'status' => 'error',
            'data'   => array(),
        );

        foreach ($this->errorList as $error)
        {
            $errorsToDisplay = array(
                'title'   => $error['title'],
                'message' => $error['message'],
            );

            if (defined('DEBUG') && DEBUG)
            {
                if (isset($error['object']))
                {
                    $errorsToDisplay['object'] = print_r($error['object']);
                }

                $errorsToDisplay['file'] = $error['file'];
                $errorsToDisplay['line'] = $error['line'];

                if (isset($error['sql']))
                {
                    $errorsToDisplay['sql'] = $error['sql'];
                }

                $errorsToDisplay['php_version'] = PHP_VERSION;
                $errorsToDisplay['php_os']      = PHP_OS;
            }

            $return['data'] = $errorsToDisplay;
        }

        echo json_encode($return);
        return;
    }

    /**
     * add
     * 
     * Adds the error to the error list.
     * 
     * @param array $params 
     * 
     * @return void
     */
    public function add ($params)
    {
        $newError = array();

        if (isset($params['title']))   { $newError['title']   = $params['title'];   }
        if (isset($params['message'])) { $newError['message'] = $params['message']; }
        if (isset($params['object']))  { $newError['object']  = $params['object'];  }
        if (isset($params['file']))    { $newError['file']    = $params['file'];    }
        if (isset($params['line']))    { $newError['line']    = $params['line'];    }
        if (isset($params['sql']))     { $newError['sql']     = $params['sql'];     }

        $this->errorList[] = $newError;
    }
}
