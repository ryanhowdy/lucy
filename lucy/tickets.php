<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/config.php';
require_once __DIR__.'/lib/Error.php';

$control = new TicketsController();
$control->run();
exit();

/**
 * TicketsController 
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class TicketsController
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

        if (isset($_GET['new']))
        {
            if (isset($_POST['submit']))
            {
                $this->displayNewTicketSubmit();
                return;
            }

            $this->displayNewTicketForm();
            return;
        }
        elseif (isset($_GET['ticket']))
        {
            if (isset($_POST['add-comment']))
            {
                $this->displayAddCommentSubmit();
                return;
            }

            $this->displayTicket();
            return;
        }

        $this->displayTickets();
    }

    /**
     * displayTickets
     * 
     * Displays the main ticket listing page.
     * 
     * @return void
     */
    function displayTickets ()
    {
        $page = new Page('tickets');

        $page->displayHeader();
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        // Get the list of tickets
        try
        {
            $db = ORM::get_db();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get tickets.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            return false;
        }

        $tickets = ORM::forTable(DB_PREFIX.'ticket')
            ->tableAlias('t')
            ->select('t.*')
            ->select('u.name', 'created_by')
            ->join(DB_PREFIX.'user', array('t.created_id', '=', 'u.id'), 'u')
            ->findArray();

        $params = array(
            'new_label' => _('New Ticket'),
            'tickets'   => $tickets,
        );

        $page->displayTemplate('tickets', 'main', $params);
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
     * displayNewTicketForm
     * 
     * Displays the form for creating a new ticket.
     * 
     * @return void
     */
    function displayNewTicketForm ()
    {
        $page = new Page('tickets');

        $page->displayHeader();
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        try
        {
            $db = ORM::get_db();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get users.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            return false;
        }

        // Get any previous form errors
        $formErrors = array();
        if (isset($_SESSION['form_errors']))
        {
            $formErrors['title'] = _('There was a problem with your form.');

            if (isset($_SESSION['form_errors']['errors']))
            {
                $formErrors['errors'] = $_SESSION['form_errors']['errors'];
            }

            if (isset($_SESSION['form_errors']['fields']))
            {
                $formErrors['title'] = _('The following fields are required:');

                foreach ($_SESSION['form_errors']['fields'] as $field)
                {
                    $formErrors['errors'][] = $field;
                }
            }
        }

        $assignees = array();
        $templateName = 'new_not_authed';

        if ($this->user->isLoggedIn())
        {
            $templateName = 'new';

            $params['user_id'] = $this->user->id;

            // Get list of all users for assignee
            $users = ORM::forTable(DB_PREFIX.'user') ->findMany();

            foreach ($users as $user)
            {
                $assignees[] = array(
                    'id'    => $user['id'],
                    'label' => $user['name'],
                );
            }
        }

        $params = array(
            'milestone_label' => _('Milestone'),
            'assignees'       => $assignees,
            'form_errors'     => $formErrors,
        );

        $page->displayTemplate('tickets', $templateName, $params);
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

        if (isset($_SESSION['form_errors']))
        {
            unset($_SESSION['form_errors']);
        }

        return;
    }

    /**
     * displayNewTicketSubmit
     * 
     * Handles the submitting of the new ticket form.
     * 
     * @return void
     */
    function displayNewTicketSubmit ()
    {
        // Check required fields
        $requiredFields  = array('subject', 'description');
        $missingRequired = false;

        foreach ($requiredFields as $field)
        {
            if (empty($_POST[$field]))
            {
                $_SESSION['form_errors']['fields'][$field] = $field;

                $missingRequired = true;
            }
        }

        if ($missingRequired)
        {
            header("Location: tickets.php?new");
            return;
        }

        // Make sure we have a user
        if (empty($_POST['email']) && empty($_POST['user_id']))
        {
            $_SESSION['form_errors']['errors'][] = _('You must provide an email address or login.');

            header("Location: tickets.php?new");
            return;
        }

        try
        {
            $db = ORM::get_db();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get users.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            return false;
        }

        // Get the user info, either from email or logged in user
        $user = $this->getUser();

        // Is this a real user
        if (isset($user['real_user_error']))
        {
            $_SESSION['form_errors']['errors'][] = _('Email address already taken. Please try again or login.');

            header("Location: tickets.php?new");
            return;
        }

        $ticket = ORM::forTable(DB_PREFIX.'ticket')->create();

        $ticket->set(array(
            'subject'     => $_POST['subject'],
            'description' => $_POST['description'],
            'created_id'  => $user['id'],
            'updated_id'  => $user['id'],
        ));
        $ticket->set_expr('updated', 'UTC_TIMESTAMP()');
        $ticket->set_expr('created', 'UTC_TIMESTAMP()');
        $ticket->save();

        if (isset($_SESSION['form_errors']))
        {
            unset($_SESSION['form_errors']);
        }

        header("Location: tickets.php");
    }

    /**
     * displayTicket 
     * 
     * Displays a single ticket.
     * 
     * @return void
     */
    function displayTicket ()
    {
        $page = new Page('tickets');

        $validator = new FormValidator();

        $page->displayHeader(array(
            'js_code' => $validator->getJsValidation($this->getProfile('comment')),
        ));

        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        // Get any previous form errors
        $formErrors = array();
        if (isset($_SESSION['form_errors']))
        {
            $formErrors['title'] = _('There was a problem with your form.');

            if (isset($_SESSION['form_errors']['errors']))
            {
                $formErrors['errors'] = $_SESSION['form_errors']['errors'];
            }
        }

        try
        {
            $db = ORM::get_db();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get users.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            return false;
        }

        // Get ticket info
        $ticket = ORM::forTable(DB_PREFIX.'ticket')
            ->tableAlias('t')
            ->select('t.*')
            ->select('c.name', 'created_by')
//            ->select('u.name', 'updated_by')
            ->join(DB_PREFIX.'user', array('t.created_id', '=', 'c.id'), 'c')
//            ->join(DB_PREFIX.'user', array('t.updated_id', '=', 'u.id'), 'u')
            ->findOne($_GET['ticket']);

        $createdBy = '<a href="user.php?id='.$ticket->created_id.'">'.$ticket->created_by.'</a>';

        $createdHeader = sprintf(_('%s opened this on %s'), $createdBy, $ticket->created);
        $description   = $this->parseComment($ticket->description);

        // Get comments
        $comments = ORM::forTable(DB_PREFIX.'ticket_comment')
            ->tableAlias('c')
            ->select('c.*')
            ->select('u.name', 'updated_by')
            ->join(DB_PREFIX.'user', array('c.updated_id', '=', 'u.id'), 'u')
            ->where('c.ticket_id', $_GET['ticket'])
            ->findArray();

        for ($i = 0; $i < count($comments); $i++)
        {
            $c = $comments[$i];

            $comments[$i]['name']    = '<a href="user.php?id='.$c['updated_id'].'">'.$c['updated_by'].'</a>';
            $comments[$i]['date']    = $c['updated'];
            $comments[$i]['comment'] = $this->parseComment( $c['comment']);
        }

        $params = array(
            'id'             => $ticket->id,
            'subject'        => $ticket->subject,
            'created_header' => $createdHeader,
            'description'    => $description,
            'status'         => $ticket->status,
            'assigned_to'    => $ticket->assigned_id,
            'milestone'      => $ticket->milestone,
            'created'        => $ticket->created,
            'updated'        => $ticket->updated,
            'comments'       => $comments,
            'form_errors'    => $formErrors,
        );

        $templateName = 'ticket_not_authed';
        if ($this->user->isLoggedIn())
        {
            $templateName = 'ticket';

            $params['user_id'] = $this->user->id;
        }

        $page->displayTemplate('tickets', $templateName, $params);
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
    }

    /**
     * displayAddCommentSubmit 
     * 
     * @return void
     */
    function displayAddCommentSubmit ()
    {
        $validator = new FormValidator();

        $errors = $validator->validate($_POST, $this->getProfile('comment'));
        if ($errors !== true)
        {
            header("Location: tickets.php?ticket=".(int)$_GET['ticket']);
            return;
        }

        if (isset($_SESSION['form_errors']))
        {
            unset($_SESSION['form_errors']);
        }

        try
        {
            $db = ORM::get_db();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get users.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            return false;
        }

        // Get the user info, either from email or logged in user
        $user = $this->getUser();

        // Is this a real user
        if (isset($user['real_user_error']))
        {
            $_SESSION['form_errors']['errors'][] = _('Email address already taken. Please try again or login.');

            header("Location: tickets.php?new");
            return;
        }

        $ticket = ORM::forTable(DB_PREFIX.'ticket_comment')->create();

        $ticket->set(array(
            'ticket_id'  => $_GET['ticket'],
            'comment'    => $_POST['comment'],
            'created_id' => $user['id'],
            'updated_id' => $user['id'],
        ));
        $ticket->set_expr('updated', 'UTC_TIMESTAMP()');
        $ticket->set_expr('created', 'UTC_TIMESTAMP()');
        $ticket->save();

        header("Location: tickets.php?ticket=".(int)$_GET['ticket']);
    }

    /**
     * getUser
     * 
     * Returns an array of user info.
     *
     * If you are logged in, will return the logged in info.
     *
     * If you are not logged in, will use the email provided
     * by the form, to lookup the user who matches.  If no
     * match is found, will create one.
     * 
     * @return array
     */
    function getUser ()
    {
        $user = array();

        if ($this->user->isLoggedIn())
        {
            return $this->user->getArray();
        }

        // Check if email exists
        $results = ORM::forTable(DB_PREFIX.'user')
            ->where('email', $_POST['email'])
            ->findOne();

        // We found something
        if ($results !== false)
        {
            $user = $results->asArray();
        }

        // No user found
        if (empty($user))
        {
            // Lets create a new one
            $newUser = ORM::forTable(DB_PREFIX.'user')->create();

            $newUser->set(array(
                'name'                 => $_POST['email'],
                'email'                => $_POST['email'],
                'password'             => 0,
            ));
            $newUser->set_expr('updated', 'UTC_TIMESTAMP()');
            $newUser->set_expr('created', 'UTC_TIMESTAMP()');
            $newUser->save();

            $user = $newUser->asArray();
        }

        // Real user found
        if (!empty($user['password']))
        {
            $user['real_user_error'] = 1;
        }

        return $user;
    }

    /**
     * parseComment 
     * 
     * Cleans up text area comments to be printed to screen.
     * 
     * @param string $comment 
     * 
     * @return string
     */
    function parseComment ($comment)
    {
        $comment = htmlentities($comment, ENT_COMPAT, 'UTF-8');
        $comment = str_replace(array("\r\n", "\r", "\n"), "<br/>", $comment); 

        return $comment;
    }

    /**
     * getProfile 
     * 
     * Returns a form validation profile.
     * 
     * @param string $name 
     * 
     * @return array
     */
    function getProfile ($name)
    {
        $profile = array(
            'comment' => array(
                'constraints' => array(
                    'email' => array(
                        'format' => '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$/',
                    ),
                    'comment' => array(
                        'required' => 1,
                    )
                ),
                'messages' => array(
                    'constraints' => array(
                        'fname' => _('Required'),
                        'lname' => _('Required'),
                    ),
                    'names' => array(
                        'email'   => _('Email Address'),
                        'comment' => _('Comment')
                    )
                )
            )
        );

        return $profile[$name];
    }
}
