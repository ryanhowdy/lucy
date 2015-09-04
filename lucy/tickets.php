<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';

/**
 * TicketsPage
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class TicketsPage extends Page
{
    /**
     * run 
     * 
     * @return void
     */
    public function run ()
    {
        if (isset($_GET['new']))
        {
            if (isset($_POST['submit']))
            {
                $this->displayTicketCreateSubmit();
                return;
            }

            $this->displayTicketCreate();
            return;
        }
        elseif (isset($_GET['edit']))
        {
            if (isset($_POST['submit']))
            {
                $this->displayTicketEditSubmit();
                return;
            }

            $this->displayTicketEdit();
            return;
        }
        elseif (isset($_GET['ticket']))
        {
            if (isset($_POST['add-comment']))
            {
                $this->displayCommentCreateSubmit();
                return;
            }

            $this->displayTicket();
            return;
        }
        elseif (isset($_POST['ajax']))
        {
            $ajax = $_POST['ajax'];

            if ($ajax === 'edit')
            {
                return $this->displayTicketEditAjax();
            }

            header('HTTP/1.1 500 Internal Server Error');
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
        $this->displayHeader();

        // Get the open/closed status ids
        $tks = \Ticket\Status::build();

        $statusIds = $tks->GetOpenStatuses();
        if (isset($_GET['closed-only']))
        {
            $statusIds = $tks->GetClosedStatuses();
        }

        // Get tickets
        try
        {
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
        }
        catch (Exception $e)
        {
            $this->error->displayError(array(
                'title'   => _('Could not get Tickets.'),
                'message' => $e->getMessage(),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));
            $this->displayFooter();
            return;
        }

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

        $this->displayTemplate('tickets', 'main', $params);
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $this->displayFooter();

        return;
    }

    /**
     * displayTicketCreate
     * 
     * Displays the form for creating a new ticket.
     * 
     * @return null
     */
    protected function displayTicketCreate ()
    {
        $this->displayHeader();

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

            $params['updated_id'] = $this->user->id;

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

        $this->displayTemplate('tickets', $templateName, $params);
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $this->displayFooter();

        if (isset($_SESSION['form_errors']))
        {
            unset($_SESSION['form_errors']);
        }

        return;
    }

    /**
     * displayTicketCreateSubmit
     * 
     * Handles the submitting of the new ticket form.
     * 
     * @return null
     */
    protected function displayTicketCreateSubmit ()
    {
        $validator = new FormValidator();

        $errors = $validator->validate($_POST, $this->getProfile('CREATE'));
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
     * @return null
     */
    protected function displayTicket ()
    {
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
            $this->displayHeader();
            $this->error->displayError(array(
                'title'   => _('Could not get Ticket.'),
                'message' => $e->getMessage(),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));
            $this->displayFooter();
            return;
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
        try
        {
            $comments = ORM::forTable(DB_PREFIX.'ticket_comment')
                ->tableAlias('c')
                ->select('c.*')
                ->select('u.name', 'updated_by')
                ->join(DB_PREFIX.'user', array('c.updated_id', '=', 'u.id'), 'u')
                ->where('c.ticket_id', $_GET['ticket'])
                ->findArray();
        }
        catch (Exception $e)
        {
            $this->displayHeader();
            $this->error->displayError(array(
                'title'   => _('Could not get Ticket Comments.'), 
                'message' => $e->getMessage(),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));
            $this->displayFooter();
            return;
        }

        $numberOfComments = count($comments);

        for ($i = 0; $i < $numberOfComments; $i++)
        {
            $c = $comments[$i];

            $comments[$i]['name']    = '<a href="user.php?id='.$c['updated_id'].'">'.$c['updated_by'].'</a>';
            $comments[$i]['date']    = $c['updated'];
            $comments[$i]['comment'] = $this->parseComment($c['comment']);
        }

        // Get Ticket update History
        $historyDetails = $this->getTicketHistory($_GET['ticket'], $ticket);

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
            $params['logged_in']  = 1;
            $params['updated_id'] = $this->user->id;

            // Get quick edit info
            $lists = $this->getAssigneeMilestoneStatusLists($ticket, false);

            $params['assignees']  = $lists['assignees'];
            $params['milestones'] = $lists['milestones'];
            $params['statuses']   = $lists['statuses'];
        }

        $this->displayHeader(array(
            'js_code' => $validator->getJsValidation($this->getProfile('COMMENT')),
        ));

        $this->displayTemplate('tickets', 'ticket', $params);
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $this->displayFooter();

        if (isset($_SESSION['success']))
        {
            unset($_SESSION['success']);
        }

        return;
    }

    /**
     * displayCommentCreateSubmit 
     * 
     * @return null
     */
    protected function displayCommentCreateSubmit ()
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
     * displayTicketEdit 
     * 
     * Prints the from for editting an existing ticket.
     * 
     * @return null
     */
    protected function displayTicketEdit ()
    {
        $this->displayHeader();

        if (!$this->user->isLoggedIn())
        {
            $this->displayMustBeLoggedIn();
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

        // Get ticket info
        try
        {
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
        }
        catch (Exception $e)
        {
            $this->error->displayError(array(
                'title'   => _('Could not get Ticket.'),
                'message' => $e->getMessage(),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));
            $this->displayFooter();
            return;
        }

        // Get assignee milestone and status lists
        $lists = $this->getAssigneeMilestoneStatusLists($ticket);

        $params = array(
            'updated_id'      => $this->user->id,
            'milestone_label' => _('Milestone'),
            'assignees'       => $lists['assignees'],
            'milestones'      => $lists['milestones'],
            'statuses'        => $lists['statuses'],
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

        $this->displayTemplate('tickets', 'edit', $params);
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $this->displayFooter();

        if (isset($_SESSION['form_errors']))
        {
            unset($_SESSION['form_errors']);
        }

        return;
    }

    /**
     * displayTicketEditSubmit 
     * 
     * @return null
     */
    protected function displayTicketEditSubmit ()
    {
        $validator = new FormValidator();

        $errors = $validator->validate($_POST, $this->getProfile('CREATE'));
        if ($errors !== true)
        {
            header("Location: tickets.php?edit=".$_POST['edit']);
            return;
        }

        if (!$this->user->isLoggedIn())
        {
            $this->displayMustBeLoggedIn();
            return;
        }

        if (isset($_SESSION['form_errors']))
        {
            unset($_SESSION['form_errors']);
        }

        // Update the ticket and history
        if (!$this->updateTicketAndHistory($_GET['edit'], $_POST))
        {
            $this->displayHeader();
            $this->error->displayError();
            $this->displayFooter();
            return;
        }

        header("Location: tickets.php?ticket=".$_GET['edit']);
        return;
    }

    /**
     * displayTicketEditAjax 
     * 
     * @return null
     */
    protected function displayTicketEditAjax ()
    {
        if (!$this->user->isLoggedIn())
        {
            header('Content-type: application/json');
            echo json_encode(array(
                'status'  => 'error',
                'message' => _('Not authorized!'),
            ));
            return;
        }

        $validator = new FormValidator();

        $_POST['updated_id'] = $this->user->id;

        $errors = $validator->validate($_POST, $this->getProfile('EDIT_AJAX'));
        if ($errors !== true)
        {
            header('Content-type: application/json');
            echo json_encode(array(
                'status' => 'fail',
                'data'   => array(
                    'title'  => _('There was a problem with your request.'),
                    'errors' => $errors,
                ),
            ));
            return;
        }

        // Update the ticket and history
        if (!$this->updateTicketAndHistory($_POST['id'], $_POST))
        {
            header('Content-type: application/json');
            $this->error->displayJsonError();
            return;
        }

        if (isset($_SESSION['success']))
        {
            unset($_SESSION['success']);
        }

        // Get additional info for ids
        if (isset($_SESSION['ticket_edit_ajax_data']['changed']['status_id']))
        {
            try
            {
                $status = ORM::forTable(DB_PREFIX.'ticket_status')->findOne($_SESSION['ticket_edit_ajax_data']['changed']['status_id']);
            }
            catch (Exception $e)
            {
                $this->error->displayJsonError(array(
                    'title'   => _('Could not get Status.'),
                    'message' => $e->getMessage(),
                    'object'  => $e,
                    'file'    => __FILE__,
                    'line'    => __LINE__,
                    'sql'     => ORM::getLastQuery(),
                ));
                return;
            }

            $_SESSION['ticket_edit_ajax_data']['changed']['status_name']  = $status->name;
            $_SESSION['ticket_edit_ajax_data']['changed']['status_color'] = $status->color;
        }
        if (isset($_SESSION['ticket_edit_ajax_data']['changed']['assigned_id']))
        {
            if ($_SESSION['ticket_edit_ajax_data']['changed']['assigned_id'] === 'NONE')
            {
                $_SESSION['ticket_edit_ajax_data']['changed']['assigned_name'] = _('no one');
            }
            else
            {
                try
                {
                    $assignee = ORM::forTable(DB_PREFIX.'user')->findOne($_SESSION['ticket_edit_ajax_data']['changed']['assigned_id']);
                }
                catch (Exception $e)
                {
                    $this->error->displayJsonError(array(
                        'title'   => _('Could not get Assignee.'),
                        'message' => $e->getMessage(),
                        'object'  => $e,
                        'file'    => __FILE__,
                        'line'    => __LINE__,
                        'sql'     => ORM::getLastQuery(),
                    ));
                    return;
                }

                $_SESSION['ticket_edit_ajax_data']['changed']['assigned_name']  = $assignee->name;
            }
        }
        if (isset($_SESSION['ticket_edit_ajax_data']['changed']['milestone_id']))
        {
            if ($_SESSION['ticket_edit_ajax_data']['changed']['milestone_id'] === 'NONE')
            {
                $_SESSION['ticket_edit_ajax_data']['changed']['milestone_name'] = _('none');
            }
            else
            {
                try
                {
                    $milestone = ORM::forTable(DB_PREFIX.'ticket_milestone')->findOne($_SESSION['ticket_edit_ajax_data']['changed']['milestone_id']);
                }
                catch (Exception $e)
                {
                    $this->error->displayJsonError(array(
                        'title'   => _('Could not get Milestone.'),
                        'message' => $e->getMessage(),
                        'object'  => $e,
                        'file'    => __FILE__,
                        'line'    => __LINE__,
                        'sql'     => ORM::getLastQuery(),
                    ));
                    return;
                }

                $_SESSION['ticket_edit_ajax_data']['changed']['milestone_name']  = $milestone->name;
                $_SESSION['ticket_edit_ajax_data']['changed']['milestone_due']   = $milestone->due;
            }
        }
        
        // Return everything worked, and whether or not user got points
        $json = array(
            'status' => 'success',
            'data'   => $_SESSION['ticket_edit_ajax_data'],
        );

        unset($_SESSION['ticket_edit_ajax_data']);

        echo json_encode($json);

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
    private function getUser ()
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
    private function parseComment ($comment)
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
     * @param int    $ticketId 
     * @param object $ticket   an ORM object representing the current ticket
     * 
     * @return array
     */
    private function getTicketHistory ($ticketId, $ticket)
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
                $to   = '<a href="user.php?id='.$prevAssignedId.'">'.$prevAssignedName.'</a>';
                $desc = sprintf(_("Assigned to %s"), $to);

                if (is_null($prevAssignedId))
                {
                    $to   = '<a href="user.php?id='.$h['assigned_id'].'">'.$h['assigned_name'].'</a>';
                    $desc = sprintf(_("Unassigned %s"), $to);
                }

                $details['comment']  = '<span class="glyphicon glyphicon-user"></span> ';
                $details['comment'] .= $desc;
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
     * getAssigneeMilestoneStatusLists 
     * 
     * Returns a list of all available assignees,
     * milestones and statuses for the given ticket.
     * 
     * @param object  $ticket 
     * @param boolean $edit 
     * 
     * @return array
     */
    private function getAssigneeMilestoneStatusLists ($ticket, $edit = true)
    {
        $noAssigneeClass  = 'ok';
        $noMilestoneClass = 'ok';

        if ($edit)
        {
            $noAssigneeLabel  = empty($ticket->assigned_id)  ? '' : _('Remove Assignee');
            $noMilestoneLabel = empty($ticket->milestone_id) ? '' : _('Remove Milestone');
        }
        else
        {
            $noAssigneeClass  = 'remove';
            $noAssigneeLabel  = _('Remove Assignee');
            $noMilestoneClass = 'remove';
            $noMilestoneLabel = _('Remove Milestone');
        }

        $assignees  = array(array('id' => 'NONE', 'label' => $noAssigneeLabel,  'class' => $noAssigneeClass));
        $milestones = array(array('id' => 'NONE', 'label' => $noMilestoneLabel, 'class' => $noMilestoneClass));

        // Get list of all users for assignee
        $users = ORM::forTable(DB_PREFIX.'user')->findMany();
        foreach ($users as $user)
        {
            $assignees[] = array(
                'id'    => $user['id'],
                'label' => $user['name'],
                'class' => 'ok',
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
                'class' => 'ok',
            );
        }

        // get the configured ticket status class
        $tks = \Ticket\Status::build();

        // Get statuses
        $statuses = $tks->getNextStatuses($ticket->status_id);

        return array(
            'assignees'  => $assignees,
            'milestones' => $milestones,
            'statuses'   => $statuses,
        );
    }

    /**
     * updateTicketAndHistory 
     * 
     * @param int   $ticketId 
     * @param array $newTicketData 
     * 
     * @return boolean
     */
    private function updateTicketAndHistory ($ticketId, $newTicketData)
    {
        // Get the original ticket info
        try
        {
            $ticket = ORM::forTable(DB_PREFIX.'ticket')->findOne($ticketId);
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Could not get Ticket.'),
                'message' => $e->getMessage(),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));
            return false;
        }

        if ($ticket === false)
        {
            $this->error->add(array(
                'title'   => _('Could not get Ticket.'),
                'message' => _('Invalid Ticket Id.'),
                'file'    => __FILE__,
                'line'    => __LINE__,
            ));
            return false;
        }

        $originalTicket = $ticket->asArray();

        $ticketChanged = false;

        // Save the ticket history
        try
        {
            $history = ORM::forTable(DB_PREFIX.'ticket_history')->create();

            // only save the things that changed
            if (isset($newTicketData['subject']) && $originalTicket['subject'] !== $newTicketData['subject'])
            {
                $history->subject = $originalTicket['subject'];
                $ticketChanged    = true;
            }
            if (isset($newTicketData['description']) && $originalTicket['description'] !== $newTicketData['description'])
            {
                $history->description = $originalTicket['description'];
                $ticketChanged        = true;
            }
            if (isset($newTicketData['status_id']) && $originalTicket['status_id'] !== $newTicketData['status_id'])
            {
                $history->status_id = $originalTicket['status_id'];
                $ticketChanged      = true;
            }
            if (isset($newTicketData['assigned_id']) && $originalTicket['assigned_id'] !== $newTicketData['assigned_id'])
            {
                // No existing assignee
                if (empty($originalTicket['assigned_id']))
                {
                    if ($newTicketData['assigned_id'] != 'NONE')
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
            if (isset($newTicketData['milestone_id']) && $originalTicket['milestone_id'] !== $newTicketData['milestone_id'])
            {
                // No existing milestone
                if (empty($originalTicket['milestone_id']))
                {
                    if ($newTicketData['milestone_id'] != 'NONE')
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
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Could not update Ticket History.'),
                'message' => $e->getMessage(),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));
            return false;
        }

        $ticketChangedDetails = array();

        // Save the new ticket updates
        try
        {
            if (isset($newTicketData['subject']))
            {
                $ticket->subject = $newTicketData['subject'];
            }
            if (isset($newTicketData['description']))
            {
                $ticket->description = $newTicketData['description'];
            }
            if (isset($newTicketData['status_id']))
            {
                $ticket->status_id = $newTicketData['status_id'];

                $ticketChangedDetails['status_id'] = $newTicketData['status_id'];
            }

            if (isset($newTicketData['assigned_id']) && !empty($newTicketData['assigned_id']))
            {
                if ($newTicketData['assigned_id'] === 'NONE')
                {
                    $ticket->set_expr('assigned_id', 'NULL');
                }
                else
                {
                    $ticket->assigned_id = $newTicketData['assigned_id'];
                }

                $ticketChangedDetails['assigned_id'] = $newTicketData['assigned_id'];
            }
            if (isset($newTicketData['milestone_id']) && !empty($newTicketData['milestone_id']))
            {
                if ($newTicketData['milestone_id'] === 'NONE')
                {
                    $ticket->set_expr('milestone_id', 'NULL');
                }
                else
                {
                    $ticket->milestone_id = $newTicketData['milestone_id'];
                }

                $ticketChangedDetails['milestone_id'] = $newTicketData['milestone_id'];
            }

            $ticket->updated_id  = $newTicketData['updated_id'];

            $ticket->set_expr('updated', 'UTC_TIMESTAMP()');
            $ticket->save();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Could not update ticket.'),
                'message' => $e->getMessage(),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));
            return false;
        }

        $_SESSION['ticket_edit_ajax_data']['changed'] = $ticketChangedDetails;

        $userActivity = new UserActivity();

        if (!$userActivity->handleTicketUpdate($originalTicket, $newTicketData))
        {
            return false;
        }

        if ($userActivity->currentUserGotPoints)
        {
            $_SESSION['success'] = $userActivity->lastPointsReason;

            $_SESSION['ticket_edit_ajax_data']['current_user_got_points'] = array(
                'title' => _('Congratulations'),
                'body'  => $userActivity->lastPointsReason,
            );
        }

        return true;
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
    private function getProfile ($name)
    {
        $profile = array(
            'CREATE' => array(
                'constraints' => array(
                    'subject' => array(
                        'required' => 1,
                    ),
                    'description' => array(
                        'required' => 1,
                    ),
                    'updated_id' => array(
                        'required' => 1,
                        'format'   => '/^[0-9]+$/',
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
            'EDIT_AJAX' => array(
                'constraints' => array(
                    'id' => array(
                        'required' => 1,
                        'format'   => '/^[0-9]+$/',
                    ),
                    'updated_id' => array(
                        'required' => 1,
                        'format'   => '/^[0-9]+$/',
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
        );

        return $profile[$name];
    }
}

$control = new TicketsPage('tickets');
$control->run();
exit();
