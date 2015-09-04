<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';

/**
 * DashboardPage
 * 
 * @package   Lucy
 * @copyright 2014 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class DashboardPage extends Page
{
    /**
     * run 
     * 
     * @return void
     */
    function run ()
    {
        $this->error = Error::getInstance();
        $this->user  = new User();

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
        $this->displayHeader();

        $this->displayTemplate('home', 'main');
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $this->displayFooter();

        return;
    }
}

$control = new DashboardPage('dashboard');
$control->run();
exit();
