<?php

namespace Ticket\Status;

/**
 * LucySimple
 * 
 * A simple open or closed status for tickets.
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class LucySimple implements \Ticket\StatusInterface
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
        return array(
            array(
                'id'    => 1,
                'label' => 'Open',
            ),
            array(
                'id'    => 2,
                'label' => 'Closed',
            ),
        );
    }

    /**
     * wasTicketAccepted 
     * 
     * A ticket is considered accepted when it gets assigned.
     * 
     * @param array $ticket 
     * @param array $newData 
     * 
     * @return boolean
     */
    public function wasTicketAccepted ($ticket, $newData)
    {
        // if they gave an assignee
        if (isset($newData['assigned_id']) && !empty($newData['assigned_id']))
        {
            // if that assignee has changed
            if ($ticket['assigned_id'] !== $newData['assigned_id'])
            {
                return true;
            }
        }

        return false;
    }

    /**
     * wasTicketResolvedByUser
     * 
     * A ticket is resolved if it's status_id was changed to Closed (2)
     * 
     * @param array $ticket 
     * @param array $newData 
     * @param int   $userId
     * 
     * @return boolean
     */
    public function wasTicketResolvedByUser ($ticket, $newData, $userId)
    {
        // if we changed from open (1) to closed (2) by $userId
        if ($ticket['status_id'] == 1 && $newData['status_id'] == 2 && $newData['assigned_id'] == $userId)
        {
            return true;
        }

        return false;
    }

    /**
     * wasTicketHandledByUser 
     * 
     * A ticket is handled if it's status has been changed from open to close.
     * 
     * @param array $ticket 
     * @param array $newData 
     * @param int   $userId 
     * 
     * @return boolean
     */
    public function wasTicketHandledByUser ($ticket, $newData, $userId)
    {
        // if we changed from open (1) to closed (2)
        if ($ticket['status_id'] == 1 && $newData['status_id'] == 2)
        {
            return true;
        }

        return false;
    }
}
