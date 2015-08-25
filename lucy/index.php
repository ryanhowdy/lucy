<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';

/**
 * DashboardController 
 * 
 * @package   Lucy
 * @copyright 2014 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class DashboardController extends Controller
{
    private $error;
    private $user;

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
        $page = new Page('dashboard');

        $page->displayHeader();
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $page->displayTemplate('home', 'main');
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $page->displayFooter();
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        return;
    }
}

$control = new DashboardController();
$control->run();
exit();
