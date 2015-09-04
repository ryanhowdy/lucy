<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';

/**
 * HelpPage
 * 
 * @package   Lucy
 * @copyright 2014 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class HelpPage extends Page
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

        $this->displayHelp();
    }

    /**
     * displayHelp
     * 
     * Displays the main dashboard page.
     * 
     * @return void
     */
    function displayHelp ()
    {
        $this->displayHeader();

        $this->displayTemplate('home', 'help');
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $this->displayFooter();

        return;
    }
}

$control = new HelpPage('help');
$control->run();
exit();
