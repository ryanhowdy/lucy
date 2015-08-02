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
     * 
     * @return void
     */
    public function displayError ()
    {
        if (!$this->hasError())
        {
            return;
        }

        echo '<style>.alert > pre { height: 300px; overflow: scroll; }</style>';
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
