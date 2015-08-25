<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';

/**
 * LoginController 
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class LoginController extends Controller
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

        if (isset($_POST['email']))
        {
            $this->handleLogin();
            return;
        }

        $this->displayLogin();
    }

    /**
     * displayLogin
     * 
     * Displays the login screen.
     * 
     * @return void
     */
    function displayLogin ($error = '')
    {
        $page = new Page('login');

        $page->displayHeader();
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $params = array();

        if (!empty($error))
        {
            $params['error'] = $error;
        }

        if ($this->user->isLoggedIn())
        {
            $params['logged_in'] = $this->user->name;
        }

        $page->displayTemplate('home', 'login', $params);
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

    /**
     * handleLogin
     * 
     * Handles submitting the login form.
     * 
     * @return void
     */
    function handleLogin ()
    {
        $remember = false;

        if (isset($_POST['remember']))
        {
            $remember = true;
        }

        // First, look up user by email
        try
        {
            $db = ORM::get_db();

            $user = ORM::forTable(DB_PREFIX.'user')
                ->where('email', $_POST['email'])
                ->findOne();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get user.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            return false;
        }

        // We couldn't find this email
        if (empty($user))
        {
            $this->displayLogin(_('Email and/or password is incorrect.'));
            return;
        }

        $hasher = new Hautelook\Phpass\PasswordHash(8, FALSE);

        // Invalid password
        if (!$hasher->CheckPassword($_POST['password'], $user['password']))
        {
            $this->displayLogin(_('Email and/or password is incorrect.'));
            return;
        }

        // User is not active
        if ($user['activated'] < 1)
        {
            $this->displayLogin(_('This account is not active.'));
            return;
        }

        // Login
        if (!$this->user->login($user['id'], $remember))
        {
            $this->error->displayError();
            return;
        }

        // All good
        header("Location: index.php");
    }
}

$control = new LoginController();
$control->run();
exit();
