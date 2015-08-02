<?php

// XXX: I'd like these to be class constants, but you can't use gettext to define them

// Tickets
define('POINTS_TICKET_ACCEPTED', 10);
define('REASON_TICKET_ACCEPTED', _('A ticket you created was accepted'));

define('POINTS_TICKET_RESOLVE', 25);
define('REASON_TICKET_RESOLVE', _('You resolved a ticket that was assigned to you.'));

define('POINTS_TICKET_HANDLE', 5); // You change a ticket status from new to anything
define('REASON_TICKET_HANDLE', _('You helped with handling of a new ticket.'));

define('POINTS_TICKET_ASSIGNED', 2);
define('REASON_TICKET_ASSIGNED', _('You were assigned a new ticket.'));

// Comments
//define('POINTS_COMMENT_UPVOTE      =  1; // You upvote someone elses comment
//define('REASON_COMMENT_UPVOTE      = _("You upvoted another user's comment.");
//
//define('POINTS_COMMENT_UPVOTED     = 10;
//define('REASON_COMMENT_UPVOTED     = _('A comment you created was upvoted.');
//
//// Discussions
//define('POINTS_ANSWER_ACCEPTED     = 50; // A discussion board answer gets accepted
//
//// Suggestions
//define('POINTS_SUGGESTION_ACCEPTED = 25; // A suggestion you created gets accepted as a ticket
//define('POINTS_SUGGESTION_UPVOTED  = 10; // A suggestion you created gets upvoted
//
//// Translations
//define('POINTS_TRANSLATE_STRING    = 10; // You translate a string (word/phrase) into another language
//
//// Code
//define('POINTS_CODE_COMMIT         =  1; // You commit a line of code

/**
 * UserActivity
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class UserActivity
{
    private $error;
    private $user;

    public $currentUserGotPoints;
    public $lastPointsReason;

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct ()
    {
        $this->error = Error::getInstance();
        $this->user  = new User();

        $this->currentUserGotPoints = false;
    }

    /**
     * handleTicketUpdate 
     * 
     * @param array $ticket 
     * @param array $newData 
     * 
     * @return boolean
     */
    public function handleTicketUpdate ($ticket, $newData)
    {
        $tks = \Ticket\Status::build();

        $link = "tickets.php?ticket=".$ticket['id'];

        // 1. accepted
        if ($tks->wasTicketAccepted($ticket, $newData))
        {
            $this->lastPointsReason = REASON_TICKET_ACCEPTED;

            if (!$this->addPoints(POINTS_TICKET_ACCEPTED, $ticket['created_id'], $link, 'TICKET', REASON_TICKET_ACCEPTED))
            {
                return false;
            }
        }

        // 2. resolve
        if ($tks->wasTicketResolvedByUser($ticket, $newData, $this->user->id))
        {
            $this->currentUserGotPoints = true;
            $this->lastPointsReason     = REASON_TICKET_RESOLVE;

            if (!$this->addPoints(POINTS_TICKET_RESOLVE, $this->user->id, $link, 'TICKET', REASON_TICKET_RESOLVE))
            {
                return false;
            }
        }

        // 3. handle
        if ($tks->wasTicketHandledByUser($ticket, $newData, $this->user->id))
        {
            $this->currentUserGotPoints = true;
            $this->lastPointsReason     = REASON_TICKET_HANDLE;

            if (!$this->addPoints(POINTS_TICKET_HANDLE, $this->user->id, $link, 'TICKET', REASON_TICKET_HANDLE))
            {
                return false;
            }
        }

        // 4. assigned

        return true;
    }

    /**
     * addPoints 
     * 
     * @param int    $points 
     * @param int    $userId 
     * @param string $link
     * @param string $category 
     * @param string $reason 
     * 
     * @return boolean
     */
    private function addPoints ($points, $userId, $link, $category, $reason)
    {
        try
        {
            $db = \ORM::get_db();
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
            $activity = \ORM::forTable(DB_PREFIX.'user_activity')->create();

            $activity->set(array(
                'user_id'  => $userId,
                'points'   => $points,
                'link'     => $link,
                'category' => $category,
                'reason'   => $reason,
            ));
            $activity->set_expr('created', 'UTC_TIMESTAMP()');
            $activity->save();
        }
        catch (Exception $e)
        {
            $this->error->add(array(
                'title'   => _('Database Error.'),
                'message' => _('Could not add activity points.'),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
            ));

            return false;
        }

        return true;
    }
}
