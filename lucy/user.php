<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';

/**
 * UserPage
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class UserPage extends Page
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

        $this->displayUser();
    }

    /**
     * displayUser
     * 
     * Displays the main user bage.
     * 
     * @return void
     */
    function displayUser ()
    {
        $this->displayHeader();

        $userId = $this->user->id;

        if (isset($_GET['id']))
        {
            $userId = $_GET['id'];
        }

        $user = ORM::forTable(DB_PREFIX.'user')->findOne($userId);

        $params = $user->asArray();

        $activity = ORM::forTable(DB_PREFIX.'user_activity')
            ->where('user_id', $userId)
            ->findArray();

        $params['joindate']   = $params['created'];
        $params['activities'] = $activity;

        $this->displayTemplate('home', 'user', $params);
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $this->displayFooter();

        return;
    }
}

$control = new UserPage('user');
$control->run();
exit();
