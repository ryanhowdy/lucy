<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/config.php';
require_once __DIR__.'/lib/Error.php';

$control = new UserController();
$control->run();
exit();

/**
 * UserController 
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class UserController
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
        $page = new Page('user');

        $page->displayHeader();
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $userId = $this->user->id;

        if (isset($_GET['id']))
        {
            $userId = $_GET['id'];
        }

        try
        {
            $db = ORM::get_db();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not connect to database.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
            ));

            return false;
        }

        $user = ORM::forTable(DB_PREFIX.'user')->findOne($userId);

        $params = $user->asArray();

        $activity = ORM::forTable(DB_PREFIX.'user_activity')
            ->where('user_id', $userId)
            ->findArray();

        $params['joindate']   = $params['created'];
        $params['activities'] = $activity;

        $page->displayTemplate('home', 'user', $params);
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
