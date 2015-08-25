<?php

/**
 * Controller 
 * 
 * @package Family Connections
 * @version 
 * @copyright 2015 Haudenschilt LLC
 * @author Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license http://www.gnu.org/licenses/gpl-2.0.html
 */
abstract class Controller
{
    abstract public function run();

    /**
     * __construct 
     * 
     * @return void
     */
    public function __construct()
    {
        // Ensure that the site has been installed first
        if (!file_exists('config.php'))
        {
            $_SESSION['no-config-redirect'] = 1;
            header("Location: install.php");
            return;
        }

        require_once 'config.php';

        ORM::configure(DB_CONNECTION);
        ORM::configure('username', DB_USERNAME);
        ORM::configure('password', DB_PASSWORD);
        ORM::configure('logging', true);
    }
}
