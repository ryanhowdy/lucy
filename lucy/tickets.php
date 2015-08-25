<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';

/**
 * TicketsController 
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class TicketsController extends Controller
{
    private $error;
    private $user;

    /**
     * run 
     * 
     * @return void
     */
    public function run ()
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
        elseif (isset($_GET['edit']))
        {
            if (isset($_POST['submit']))
            {
                $this->displayEditTicketSubmit();
                return;
            }

            $this->displayEditTicketForm();
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
    protected function displayTickets ()
    {
        $page = new Page('tickets');

        $page->displayHeader();
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        // Get the open/closed status ids
        $tks = \Ticket\Status::build();

        $statusIds = $tks->GetOpenStatuses();
        if (isset($_GET['closed-only']))
        {
            $statusIds = $tks->GetClosedStatuses();
        }

        // Get tickets
        $tickets = ORM::forTable(DB_PREFIX.'ticket')
            ->tableAlias('t')
            ->select('t.*')
            ->select('u.name', 'created_by')
            ->select('s.name', 'status_name')
            ->select('s.color', 'status_color')
            ->select('m.name', 'milestone_name')
            ->select('ua.name', 'assigned_name')
            ->select_expr('COUNT(tc.id)', 'comments_count')
            ->join(DB_PREFIX.'user', array('t.created_id', '=', 'u.id'), 'u')
            ->left_outer_join(DB_PREFIX.'user', array('t.assigned_id', '=', 'ua.id'), 'ua')
            ->left_outer_join(DB_PREFIX.'ticket_status', array('t.status_id', '=', 's.id'), 's')
            ->left_outer_join(DB_PREFIX.'ticket_milestone', array('t.milestone_id', '=', 'm.id'), 'm')
            ->left_outer_join(DB_PREFIX.'ticket_comment', array('t.id', '=', 'tc.ticket_id'), 'tc')
            ->where_in('status_id', $statusIds)
            ->order_by_asc('id')
            ->group_by('t.id')
            ->findArray();

        // authors
        $authors = array();

        $numberOfTickets = count($tickets);

        // Add zero class to tickets that have no comments
        for ($i = 0; $i < $numberOfTickets; $i++)
        {
            $authors[ $tickets[$i]['created_id'] ] = array(
                'id'    => $tickets[$i]['created_id'],
                'value' => $tickets[$i]['created_by'],
            );

            $tickets[$i]['comments_class'] = '';

            if ($tickets[$i]['comments_count'] == 0)
            {
                $tickets[$i]['comments_class'] = 'zero';
            }
        }

        $params = array(
            'new_label' => _('New Ticket'),
            'authors'   => $authors,
            'tickets'   => $tickets,
        );

        // open/closed only?
        $params['open_class'] = 'active';
        if (isset($_GET['closed-only']))
        {
            unset($params['open_class']);

            $params['closed_class'] = 'active';
        }

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
     * @return null|false
     */
    protected function displayNewTicketForm ()
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
                'message' => _('Could not connect to database.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
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
        }

        $assignees    = array();
        $milestones   = array();
        $templateName = 'new_not_authed';

        $params = array(
            'milestone_label' => _('Milestone'),
            'form_errors'     => $formErrors,
        );

        if ($this->user->isLoggedIn())
        {
            $templateName = 'new';

            $params['user_id'] = $this->user->id;

            // Get list of all users for assignee
            $users = ORM::forTable(DB_PREFIX.'user')->findMany();

            foreach ($users as $user)
            {
                $assignees[] = array(
                    'id'    => $user['id'],
                    'label' => $user['name'],
                );
            }

            // Get list of all open milestones
            $milestoneRecords = ORM::forTable(DB_PREFIX.'ticket_milestone')
                ->where('complete', '0000-00-00')
                ->findMany();

            foreach ($milestoneRecords as $milestone)
            {
                $milestones[] = array(
                    'id'    => $milestone['id'],
                    'label' => $milestone['name'].' ('.$milestone['due'].')',
                );
            }
        }

        $params['assignees']  = $assignees;
        $params['milestones'] = $milestones;

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
     * @return null|false
     */
    protected function displayNewTicketSubmit ()
    {
        $validator = new FormValidator();

        $errors = $validator->validate($_POST, $this->getProfile('NEW_TICKET'));
        if ($errors !== true)
        {
            header("Location: tickets.php?new");
            return;
        }

        // Make sure we have a user
        if (empty($_POST['email']) && !$this->user->isLoggedIn())
        {
            $_SESSION['form_errors']['errors'][] = _('You must provide an email address or login.');

            header("Location: tickets.php?new");
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
                'message' => _('Could not connect to database.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
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

        // Create the ticket
        $ticket = ORM::forTable(DB_PREFIX.'ticket')->create();

        $ticket->set(array(
            'subject'     => $_POST['subject'],
            'description' => $_POST['description'],
            'created_id'  => $user['id'],
            'updated_id'  => $user['id'],
        ));
        $ticket->set_expr('updated', 'UTC_TIMESTAMP()');
        $ticket->set_expr('created', 'UTC_TIMESTAMP()');

        // status
        $ticket->set('status_id', 1);

        // assignee
        if (isset($_POST['assigned_id']) && !empty($_POST['assigned_id']))
        {
            $ticket->set('assigned_id', $_POST['assigned_id']);
        }

        // milestone
        if (isset($_POST['milestone']) && !empty($_POST['milestone']))
        {
            $ticket->set('milestone_id', $_POST['milestone']);
        }

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
     * @return false|null
     */
    protected function displayTicket ()
    {
        $page = new Page('tickets');

        $validator = new FormValidator();

        $message        = array();
        $commentMessage = array();

        // Get any previous form errors (from comments)
        if (isset($_SESSION['form_errors']))
        {
            $commentMessage['title'] = _('There was a problem with your form.');
            $commentMessage['type']  = 'danger';

            if (isset($_SESSION['form_errors']['errors']))
            {
                $commentMessage['messages'] = $_SESSION['form_errors']['errors'];
            }
        }

        // Get any success messages
        if (isset($_SESSION['success']))
        {
            $message['title']    = _('Congratulations');
            $message['type']     = 'success';
            $message['messages'] = array($_SESSION['success']);
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

        // Get ticket info
        try
        {
            $ticket = ORM::forTable(DB_PREFIX.'ticket')
                ->tableAlias('t')
                ->select('t.*')
                ->select('c.name', 'created_by')
                ->select('s.name', 'status_name')
                ->select('s.color', 'status_color')
                ->select('m.name', 'milestone_name')
                ->select('m.due', 'milestone_due')
                ->select('ua.name', 'assigned_name')
                ->join(DB_PREFIX.'user', array('t.created_id', '=', 'c.id'), 'c')
                ->left_outer_join(DB_PREFIX.'user', array('t.assigned_id', '=', 'ua.id'), 'ua')
                ->left_outer_join(DB_PREFIX.'ticket_status', array('t.status_id', '=', 's.id'), 's')
                ->left_outer_join(DB_PREFIX.'ticket_milestone', array('t.milestone_id', '=', 'm.id'), 'm')
                ->findOne($_GET['ticket']);
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not get Ticket.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
            ));

            return false;
        }

        if ($ticket === false)
        {
            header("Location: tickets.php");
            return;
        }

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

        $numberOfComments = count($comments);

        for ($i = 0; $i < $numberOfComments; $i++)
        {
            $c = $comments[$i];

            $comments[$i]['name']    = '<a href="user.php?id='.$c['updated_id'].'">'.$c['updated_by'].'</a>';
            $comments[$i]['date']    = $c['updated'];
            $comments[$i]['comment'] = $this->parseComment($c['comment']);
        }

        // Get Ticket update History
        $historyDetails = $this->getTicketHistory($_GET['ticket']);

        // Combine the comments with the history
        $commentsAndHistory = array_merge($comments, $historyDetails);

        // Sort the comments and history by created
        usort($commentsAndHistory, function($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        $params = array(
            'id'              => $ticket->id,
            'subject'         => $ticket->subject,
            'created_header'  => $createdHeader,
            'description'     => $description,
            'status'          => $ticket->status,
            'assignee_id'     => $ticket->assigned_id,
            'assigned_name'   => $ticket->assigned_name,
            'status_name'     => $ticket->status_name,
            'status_color'    => $ticket->status_color,
            'milestone_id'    => $ticket->milestone_id,
            'milestone_name'  => $ticket->milestone_name,
            'milestone_due'   => $ticket->milestone_due,
            'created'         => $ticket->created,
            'updated'         => $ticket->updated,
            'comment_message' => $commentMessage,
            'comments'        => $commentsAndHistory,
            'message'         => $message,
        );

        if ($this->user->isLoggedIn())
        {
            $params['logged_in'] = 1;
            $params['user_id']   = $this->user->id;
        }

        $page->displayHeader(array(
            'js_code' => $validator->getJsValidation($this->getProfile('COMMENT')),
        ));

        $page->displayTemplate('tickets', 'ticket', $params);
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $page->displayFooter();

        if (isset($_SESSION['success']))
        {
            unset($_SESSION['success']);
        }

        return;
    }

    /**
     * displayAddCommentSubmit 
     * 
     * @return null|false
     */
    protected function displayAddCommentSubmit ()
    {
        $validator = new FormValidator();

        $errors = $validator->validate($_POST, $this->getProfile('COMMENT'));
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
                'message' => _('Could not connect to database.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
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
     * displayEditTicketForm 
     * 
     * Prints the from for editting an existing ticket.
     * 
     * @return null|false
     */
    protected function displayEditTicketForm ()
    {
        $page = new Page('tickets');

        $page->displayHeader();
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        if (!$this->user->isLoggedIn())
        {
            $page->displayMustBeLoggedIn();
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
                'message' => _('Could not connect to database.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
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
        }

        // Get ticket info
        $ticket = ORM::forTable(DB_PREFIX.'ticket')
            ->tableAlias('t')
            ->select('t.*')
            ->select('c.name', 'created_by')
            ->select('s.name', 'status_name')
            ->select('m.name', 'milestone_name')
            ->join(DB_PREFIX.'user', array('t.created_id', '=', 'c.id'), 'c')
            ->left_outer_join(DB_PREFIX.'ticket_status', array('t.status_id', '=', 's.id'), 's')
            ->left_outer_join(DB_PREFIX.'ticket_milestone', array('t.milestone_id', '=', 'm.id'), 'm')
            ->findOne($_GET['edit']);

        // Get list of all users for assignee
        $users = ORM::forTable(DB_PREFIX.'user')->findMany();

        $assignees = array(array('id' => 'NONE', 'label' => ''));
        foreach ($users as $user)
        {
            $assignees[] = array(
                'id'    => $user['id'],
                'label' => $user['name'],
            );
        }

        // Get list of all open milestones
        $milestoneRecords = ORM::forTable(DB_PREFIX.'ticket_milestone')
            ->where('complete', '0000-00-00')
            ->findMany();

        $milestones = array(array('id' => 'NONE', 'label' => ''));
        foreach ($milestoneRecords as $milestone)
        {
            $milestones[] = array(
                'id'    => $milestone['id'],
                'label' => $milestone['name'].' ('.$milestone['due'].')',
            );
        }

        // get the configured ticket status class
        $tks = \Ticket\Status::build();

        // Get statuses
        $statuses = $tks->getNextStatuses($ticket->status_id);

        $params = array(
            'user_id'         => $this->user->id,
            'milestone_label' => _('Milestone'),
            'assignees'       => $assignees,
            'milestones'      => $milestones,
            'statuses'        => $statuses,
            'form_errors'     => $formErrors,
            'values'          => array(
                'ticket_id'   => $ticket->id,
                'subject'     => $ticket->subject,
                'description' => $ticket->description,
                'status'      => $ticket->status_id,
                'assignee'    => $ticket->assigned_id,
                'milestone'   => $ticket->milestone_id,
            ),
        );

        $page->displayTemplate('tickets', 'edit', $params);
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
     * displayEditTicketSubmit 
     * 
     * @return null|false
     */
    protected function displayEditTicketSubmit ()
    {
        $page = new Page('tickets');

        $validator = new FormValidator();

        $errors = $validator->validate($_POST, $this->getProfile('NEW_TICKET'));
        if ($errors !== true)
        {
            header("Location: tickets.php?edit=".$_POST['edit']);
            return;
        }

        if (!$this->user->isLoggedIn())
        {
            $page->displayMustBeLoggedIn();
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
                'message' => _('Could not connect to database.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
            ));

            return false;
        }

        try
        {
            // Get the original ticket info
            $ticket = ORM::forTable(DB_PREFIX.'ticket')->findOne($_GET['edit']);

            $originalTicket = $ticket->asArray();

            // Save the ticket history
            $ticketChanged = false;
            $history = ORM::forTable(DB_PREFIX.'ticket_history')->create();

            // only save the things that changed
            if ($originalTicket['subject'] !== $_POST['subject'])
            {
                $history->subject = $originalTicket['subject'];
                $ticketChanged    = true;
            }
            if ($originalTicket['description'] !== $_POST['description'])
            {
                $history->description = $originalTicket['description'];
                $ticketChanged        = true;
            }
            if ($originalTicket['status_id'] !== $_POST['status_id'])
            {
                $history->status_id = $originalTicket['status_id'];
                $ticketChanged      = true;
            }
            if ($originalTicket['assigned_id'] !== $_POST['assigned_id'])
            {
                // No existing assignee
                if (empty($originalTicket['assigned_id']))
                {
                    if ($_POST['assigned_id'] != 'NONE')
                    {
                        $history->assigned_id = 0;
                        $ticketChanged        = true;
                    }
                }
                // Existing assignee
                else
                {
                    // We are either removing the current assignee or changing it
                    $history->assigned_id = $originalTicket['assigned_id'];
                    $ticketChanged        = true;
                }
            }
            if ($originalTicket['milestone_id'] !== $_POST['milestone'])
            {
                // No existing milestone
                if (empty($originalTicket['milestone_id']))
                {
                    if ($_POST['milestone'] != 'NONE')
                    {
                        $history->milestone_id = 0;
                        $ticketChanged         = true;
                    }
                }
                else
                {
                    $history->milestone_id = $originalTicket['milestone_id'];
                    $ticketChanged         = true;
                }

            }

            if ($ticketChanged) {
                $history->set(array(
                    'ticket_id'    => $originalTicket['id'],
                    'created_id'   => $originalTicket['updated_id'],  // NOTE: the logged in user didn't create the history, the previous updated_id did
                ));
                $history->set_expr('created', 'UTC_TIMESTAMP()');
                $history->save();
            }

            // Save the new ticket updates
            $ticket->subject     = $_POST['subject'];
            $ticket->description = $_POST['description'];
            $ticket->status_id   = $_POST['status_id'];
            $ticket->updated_id  = $_POST['user_id'];

            if (isset($_POST['assigned_id']) && !empty($_POST['assigned_id']))
            {
                if ($_POST['assigned_id'] === 'NONE')
                {
                    $ticket->set_expr('assigned_id', 'NULL');
                }
                else
                {
                    $ticket->assigned_id = $_POST['assigned_id'];
                }
            }
            if (isset($_POST['milestone']) && !empty($_POST['milestone']))
            {
                if ($_POST['milestone'] === 'NONE')
                {
                    $ticket->set_expr('milestone_id', 'NULL');
                }
                else
                {
                    $ticket->milestone_id = $_POST['milestone'];
                }
            }

            $ticket->set_expr('updated', 'UTC_TIMESTAMP()');
            $ticket->save();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not update ticket.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));

            $page->displayHeader();
            $this->error->displayError();
            $page->displayFooter();
            return;
        }

        $userActivity = new UserActivity();
        $userActivity->handleTicketUpdate($originalTicket, $_POST);

        if ($userActivity->currentUserGotPoints)
        {
            $_SESSION['success'] = $userActivity->lastPointsReason;
        }

        if ($this->error->hasError())
        {
            $page->displayHeader();
            $this->error->displayError();
            $page->displayFooter();
            return;
        }

        if (isset($_SESSION['form_errors']))
        {
            unset($_SESSION['form_errors']);
        }

        header("Location: tickets.php?ticket=".$_GET['edit']);
        return;
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
    protected function getUser ()
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
    protected function parseComment ($comment)
    {
        $comment = htmlentities($comment, ENT_COMPAT, 'UTF-8');
        $comment = str_replace(array("\r\n", "\r", "\n"), "<br/>", $comment); 

        return $comment;
    }

    /**
     * getTicketHistory 
     * 
     * Will get the ticket history information details for a given ticket id.
     * 
     * @param int $ticketId 
     * 
     * @return array
     */
    protected function getTicketHistory ($ticketId)
    {
        $historyDetails = array();

        $history = ORM::forTable(DB_PREFIX.'ticket_history')
            ->tableAlias('h')
            ->select('h.*')
            ->select('s.name', 'status_name')
            ->select('s.color', 'status_color')
            ->select('m.name', 'milestone_name')
            ->select('m.due', 'milestone_due')
            ->select('ua.name', 'assigned_name')
            ->where('h.ticket_id', $ticketId)
            ->left_outer_join(DB_PREFIX.'ticket_status', array('h.status_id', '=', 's.id'), 's')
            ->left_outer_join(DB_PREFIX.'ticket_milestone', array('h.milestone_id', '=', 'm.id'), 'm')
            ->left_outer_join(DB_PREFIX.'user', array('h.assigned_id', '=', 'ua.id'), 'ua')
            ->order_by_desc('h.created')
            ->findArray();

        $prevSubject       = $ticket->subject;
        $prevStatusName    = $ticket->status_name;
        $prevStatusColor   = $ticket->status_color;
        $prevAssignedId    = $ticket->assigned_id;
        $prevAssignedName  = $ticket->assigned_name;
        $prevMilestoneId   = $ticket->milestone_id;
        $prevMilestoneName = $ticket->milestone_name;

        $numberOfHistory = count($history);
        for ($i = 0; $i < $numberOfHistory; $i++)
        {
            $h = $history[$i];

            $details = array(
                'type' => 'details',
                'date' => $h['created'],
            );

            if (!is_null($h['subject']))
            {
                $details['comment']  = '<span class="glyphicon glyphicon-book"></span> ';
                $details['comment'] .= sprintf(_("Subject changed from '%s' to '%s'"), '<b>'.$h['subject'].'</b>', '<b>'.$prevSubject.'</b>');
                $details['comment'] .= ' <small>'.$h['created'].'</small>';

                $historyDetails[] = $details;

                $prevSubject = $h['subject'];
            }
            if (!is_null($h['description']))
            {
                $details['comment']  = '<span class="glyphicon glyphicon-comment"></span> ';
                $details['comment'] .= '<a href="#">';
                $details['comment'] .= sprintf(_("Description updated'"), '<b>'.$h['subject'].'</b>', '<b>'.$prevSubject.'</b>');
                $details['comment'] .= '</a>';
                $details['comment'] .= ' <small>'.$h['created'].'</small>';

                $historyDetails[] = $details;

                $prevSubject = $h['subject'];
            }
            if (!is_null($h['status_id']))
            {
                $to = '<span class="label" style="background-color:#'.$prevStatusColor.'">'.$prevStatusName.'</span>';

                $details['comment']  = '<span class="glyphicon glyphicon-tag"></span> ';
                $details['comment'] .= sprintf(_("Status changed to %s"), $to);
                $details['comment'] .= ' <small>'.$h['created'].'</small>';

                $historyDetails[] = $details;

                $prevStatusName  = $h['status_name'];
                $prevStatusColor = $h['status_color'];
            }
            if (!is_null($h['assigned_id']))
            {
                $to = '<a href="user.php?id='.$prevAssignedId.'">'.$prevAssignedName.'</a>';

                $details['comment']  = '<span class="glyphicon glyphicon-user"></span> ';
                $details['comment'] .= sprintf(_("Assigned to %s"), $to);
                $details['comment'] .= ' <small>'.$h['created'].'</small>';

                $historyDetails[] = $details;

                $prevAssignedId   = $h['assigned_id'];
                $prevAssignedName = $h['assigned_name'];
            }
            if (!is_null($h['milestone_id']))
            {
                $to   = '<a href="milestone.php?milestone='.$prevMilestoneId.'">'.$prevMilestoneName.'</a>';
                $desc = sprintf(_("Added to %s milestone"), $to);
                if (is_null($prevMilestoneId))
                {
                    $to   = '<a href="milestone.php?milestone='.$h['milestone_id'].'">'.$h['milestone_name'].'</a>';
                    $desc = sprintf(_("Removed from %s milestone"), $to);
                }

                $details['comment']  = '<span class="glyphicon glyphicon-calendar"></span> ';
                $details['comment'] .= $desc;
                $details['comment'] .= ' <small>'.$h['created'].'</small>';

                $historyDetails[] = $details;

                $prevMilestoneId   = $h['milestone_id'];
                $prevMilestoneName = $h['milestone_name'];
            }
        }

        return $historyDetails;
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
    protected function getProfile ($name)
    {
        $profile = array(
            'NEW_TICKET' => array(
                'constraints' => array(
                    'subject' => array(
                        'required' => 1,
                    ),
                    'description' => array(
                        'required' => 1,
                    ),
                    'email' => array(
                        'format' => '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$/',
                    ),
                    'status_id' => array(
                        'format' => '/^[0-9]+$/',
                    ),
                    'assignee_id' => array(
                        'format' => '/(^[0-9]+$|^NONE$)?/',
                    ),
                    'milestone_id' => array(
                        'format' => '/^([0-9]+$|^NONE$)?/',
                    ),
                ),
            ),
            'COMMENT' => array(
                'constraints' => array(
                    'email' => array(
                        'format' => '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$/',
                    ),
                    'comment' => array(
                        'required' => 1,
                    ),
                ),
                'messages' => array(
                    'constraints' => array(
                        'fname' => _('Required'),
                        'lname' => _('Required'),
                    ),
                    'names' => array(
                        'email'   => _('Email Address'),
                        'comment' => _('Comment')
                    ),
                ),
            ),
        );

        return $profile[$name];
    }
}

$control = new TicketsController();
$control->run();
exit();
