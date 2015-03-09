<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/config.php';
require_once __DIR__.'/lib/Error.php';

$lucyError = Lucy_Error::getInstance();

$control = new DashboardController();
$control->run();
exit();

/**
 * DashboardController 
 * 
 * @package   Lucy
 * @copyright 2014 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class DashboardController
{
    /**
     * run 
     * 
     * @return void
     */
    function run ()
    {
        $this->displayDashboard();
    }

    /**
     * displayDashboard
     * 
     * Displays the main dashboard page.
     * 
     * @return void
     */
    function displayDashboard ()
    {
        $lucyError = Lucy_Error::getInstance();

        $page = new Page('dashboard');

        $page->displayHeader();
        if ($lucyError->hasError())
        {
            $lucyError->displayError();
            return;
        }

        $page->displayTemplate('home', 'main');
        if ($lucyError->hasError())
        {
            $lucyError->displayError();
            return;
        }

        return;
    }
}
