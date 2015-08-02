<?php

namespace Ticket\Status;

/**
 * LucyAdvanced
 * 
 * An advanced status workflow for tickets.
 * 
 * New -> Accepted -> Assigned -> Started -> Resolved
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class LucyAdvanced implements \Ticket\StatusInterface
{
    private $error;

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct ()
    {
        $this->error = \Error::getInstance();

        \ORM::configure(DB_CONNECTION);
        \ORM::configure('username', DB_USERNAME);
        \ORM::configure('password', DB_PASSWORD);
        \ORM::configure('logging', true);
    }

    /**
     * getNextStatuses 
     * 
     * @param string  $currentStatus 
     * 
     * @return array
     */
    public function getNextStatuses ($currentStatus = null)
    {
        switch ($currentStatus)
        {
            default:
                return array(
                    array('id' => 3, 'label' => _('New'),     ),
                    array('id' => 4, 'label' => _('Accepted'),),
                    array('id' => 5, 'label' => _('Rejected'),),
                    array('id' => 6, 'label' => _('Assigned'),),
                    array('id' => 7, 'label' => _('Started'), ),
                    array('id' => 8, 'label' => _('Resolved'),),
                    array('id' => 9, 'label' => _('Reopened'),),
                );
                break;

            // New
            case '3':
                return array(
                    array('id' => 4, 'label' => _('Accepted'),),
                    array('id' => 5, 'label' => _('Rejected'),),
                );
                break;

            // Accepted
            case '4':
                return array(
                    array('id' => 5, 'label' => _('Rejected'),),
                    array('id' => 6, 'label' => _('Assigned'),),
                );
                break;

            // Rejected
            case '5':
                return array(
                    array('id' => 9, 'label' => _('Reopened'),),
                );
                break;

            // Assigned
            case '6':
                return array(
                    array('id' => 5, 'label' => _('Rejected'),),
                    array('id' => 7, 'label' => _('Started'), ),
                    array('id' => 8, 'label' => _('Resolved'),),
                );
                break;

            // Started
            case '7':
                return array(
                    array('id' => 5, 'label' => _('Rejected'),),
                    array('id' => 8, 'label' => _('Resolved'),),
                );
                break;

            // Resolved
            case '8':
                return array(
                    array('id' => 9, 'label' => _('Reopened'),),
                );
                break;

            // Reopened
            case '9':
                return array(
                    array('id' => 4, 'label' => _('Accepted'),),
                    array('id' => 5, 'label' => _('Rejected'),),
                    array('id' => 8, 'label' => _('Resolved'),),
                );
                break;
        }
    }

    /**
     * wasTicketAccepted 
     * 
     * A ticket is considered accepted when it's status_id
     * has been changed from New (3) to Accepted (4).
     * 
     * @param array $ticket 
     * @param array $newData 
     * 
     * @return boolean
     */
    public function wasTicketAccepted ($ticket, $newData)
    {
        if ($ticket['status_id'] == 3 && $newData['status_id'] == 4)
        {
            return true;
        }

        return false;
    }

    /**
     * wasTicketResolvedByUser
     * 
     * A ticket is resolved if it's status_id was changed to Resolved (8)
     * 
     * @param array $ticket 
     * @param array $newData 
     * @param int   $userId
     * 
     * @return boolean
     */
    public function wasTicketResolvedByUser ($ticket, $newData, $userId)
    {
        // if we changed to Resolved (8) by $userId
        if ($newData['status_id'] == 8 && $newData['assigned_id'] == $userId)
        {
            return true;
        }

        return false;
    }

    /**
     * wasTicketHandledByUser 
     * 
     * A ticket is handled if it's status has been changed from New (3) to 
     * any other status.
     * 
     * @param array $ticket 
     * @param array $newData 
     * @param int   $userId 
     * 
     * @return boolean
     */
    public function wasTicketHandledByUser ($ticket, $newData, $userId)
    {
        // if we changed from New (3) to something else
        if ($ticket['status_id'] == 3 && $newData['status_id'] != 3)
        {
            return true;
        }

        return false;
    }
}
