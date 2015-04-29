<?php

/**
 * User
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class User
{
    public $id    = 0;
    public $token = 0;

    public $name;
    public $source_code_username;
    public $email;
    public $birthday;
    public $timezone;
    public $language;
    public $activated;
    public $login_attempts;
    public $locked;
    public $created;
    public $updated;

    private $error;
    private $password;
    private $activate_code;

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct ()
    {
        $this->error = Error::getInstance();

        ORM::configure(DB_CONNECTION);
        ORM::configure('username', DB_USERNAME);
        ORM::configure('password', DB_PASSWORD);
        ORM::configure('logging', true);

        if (isset($_SESSION['lucy_id']) && isset($_SESSION['lucy_token']))
        {
            $this->saveUserInfo();
        }
        elseif (isset($_COOKIE['lucy_id']) && isset($_COOKIE['lucy_token']))
        {
            $_SESSION['lucy_id']    = (int)$_COOKIE['lucy_id'];
            $_SESSION['lucy_token'] = $_COOKIE['lucy_token'];

            $this->saveUserInfo();
        }
    }

    /**
     * saveUserInfo 
     * 
     * @return boolean
     */
    private function saveUserInfo ()
    {
        try
        {
            $db = ORM::get_db();

            $user = ORM::forTable(DB_PREFIX.'user')
                ->where(array(
                    'id'    => $_SESSION['lucy_id'],
                    'token' => $_SESSION['lucy_token'],
                ))
                ->findOne();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get user information.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            return false;
        }

        $this->id                   = $user['id'];
        $this->token                = $user['token'];
        $this->name                 = $user['name'];
        $this->source_code_username = $user['source_code_username'];
        $this->email                = $user['email'];
        $this->birthday             = $user['birthday'];
        $this->timezone             = $user['timezone'];
        $this->language             = $user['language'];
        $this->activated            = $user['activate'];
        $this->login_attempts       = $user['login_attempts'];
        $this->locked               = $user['locked'];
        $this->created              = $user['created'];
        $this->updated              = $user['updated'];
        $this->password             = $user['password'];
        $this->activate_code        = $user['activate_code'];

        return true;
    }

    /**
     * login 
     * 
     * Generates a token and saves to the db 
     * and session/cookie. Also resets the 
     * login_attempts.
     * 
     * @param int     $id 
     * @param boolean $remember 
     * 
     * @return boolean
     */
    public function login ($id, $remember = false)
    {
        $token = uniqid('');

        try
        {
            $db = ORM::get_db();

            $user = ORM::forTable(DB_PREFIX.'user')->findOne($id);

            $user->set(array(
                'token'          => $token,
                'login_attempts' => 0,
            ));
            $user->save();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not save login information.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            return false;
        }

        $this->id    = $id;
        $this->token = $token;

        // Save cookie
        if ($remember)
        {
            setcookie('lucy_id',    $this->id,    time() + (30*(24*3600)), '/'); // 30 days
            setcookie('lucy_token', $this->token, time() + (30*(24*3600)), '/'); // 30 days
        }

        $_SESSION['lucy_id']    = $this->id;
        $_SESSION['lucy_token'] = $this->token;

        // Reload all the new/good user data
        $this->saveUserInfo();

        return true;
    }

    /**
     * isLoggedIn 
     * 
     * @return boolean
     */
    public function isLoggedIn ()
    {
        // No session info
        if (empty($_SESSION['lucy_id']) || empty($_SESSION['lucy_token']))
        {
            return false;
        }

        // No login id or token
        if (empty($this->id) || empty($this->token))
        {
            return false;
        }

        // Session id does not match id in db
        if ($_SESSION['lucy_id'] !== $this->id)
        {
            return false;
        }

        // Session token does not match token in db
        if ($_SESSION['lucy_token'] !== $this->token)
        {
            return false;
        }

        return true;
    }

    /**
     * getArray 
     * 
     * Will return the logged in user data as an array.
     * 
     * @return array
     */
    public function getArray ()
    {
        return array(
            'id'                    => $this->id,
            'name'                  => $this->name,
            'source_code_username'  => $this->source_code_username,
            'email'                 => $this->email,
            'birthday'              => $this->birthday,
            'timezone'              => $this->timezone,
            'language'              => $this->language,
            'activated'             => $this->activated,
            'login_attempts'        => $this->login_attempts,
            'locked'                => $this->locked,
            'created'               => $this->created,
            'updated'               => $this->updated,
        );
    }
}
