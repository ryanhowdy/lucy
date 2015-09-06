<?php

session_start();

require_once __DIR__.'/vendor/autoload.php';

/**
 * MilestonesPage
 * 
 * @package   Lucy
 * @copyright 2015 Haudenschilt LLC
 * @author    Ryan Haudenschilt <r.haudenschilt@gmail.com> 
 * @license   http://www.gnu.org/licenses/gpl-2.0.html
 */
class MilestonesPage extends Page
{
    /**
     * run 
     * 
     * @return void
     */
    public function run ()
    {
        $this->displayMilestones();
    }

    /**
     * displayMilestones
     * 
     * Displays the list of milestones
     * 
     * @return void
     */
    protected function displayMilestones ()
    {
        $this->displayHeader();

        // Get Milestones
        $milestones = array();
        try
        {
            foreach(ORM::forTable(DB_PREFIX.'ticket_milestone')
                ->where('complete', '0000-00-00 00:00:00')
                ->order_by_asc('due')
                ->findResultSet()
                as $data)
            {
                $milestoneIds[] = $data['id'];

                $milestones[ $data['id'] ] = $data->asArray();
            }
        }
        catch (Exception $e)
        {
            $this->error->displayError(array(
                'title'   => _('Could not get Milestones.'),
                'message' => $e->getMessage(),
                'object'  => $e,
                'file'    => __FILE__,
                'line'    => __LINE__,
                'sql'     => ORM::getLastQuery(),
            ));
            $this->displayFooter();
            return;
        }

        // Get the closed status ids
        $tks = \Ticket\Status::build();

        $closedStatusIds = $tks->GetClosedStatuses();

        // Get Tickets for each Milestone
        try
        {
            foreach(ORM::forTable(DB_PREFIX.'ticket')->where_in('milestone_id', $milestoneIds)->findResultSet() as $data)
            {
                $milestones[ $data['milestone_id'] ]['total_tickets']++;

                if (in_array($data->status_id, $closedStatusIds))
                {
                    $milestones[ $data['milestone_id'] ]['closed_tickets']++;
                }
                else
                {
                    $milestones[ $data['milestone_id'] ]['opened_tickets']++;
                }

                $percent = 0;
                $opened  = 0;
                $closed  = 0;
                $total   = $milestones[ $data['milestone_id'] ]['total_tickets'];

                if (isset($milestones[ $data['milestone_id'] ]['opened_tickets']))
                {
                    $opened  = $milestones[ $data['milestone_id'] ]['opened_tickets'];
                    $percent = round($opened / $total * 100);
                }
                if (isset($milestones[ $data['milestone_id'] ]['closed_tickets']))
                {
                    $closed = $milestones[ $data['milestone_id'] ]['closed_tickets'];
                }

                $milestones[ $data['milestone_id'] ]['percent_complete']    = $percent;
                $milestones[ $data['milestone_id'] ]['total_tickets_link']  = _('Total').': '.$total;
                $milestones[ $data['milestone_id'] ]['opened_tickets_link'] = _('Open').': '.$opened;
                $milestones[ $data['milestone_id'] ]['closed_tickets_link'] = _('Closed').': '.$closed;
            }
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

        $params = array(
            'navigation' => array(
                'new_ticket_link' => _('New Ticket'),
                'ticket_link'     => _('Tickets'),
                'milestone_link'  => _('Milestones'),
                'milestone_class' => 'active',
            ),
            'milestones' => $milestones,
        );

        $this->displayTemplate('tickets', 'milestones', $params);
        if ($this->error->hasError())
        {
            $this->error->displayError();
            return;
        }

        $this->displayFooter();

        return;
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
        );

        return $profile[$name];
    }
}

$control = new MilestonesPage('milestones');
$control->run();
exit();
