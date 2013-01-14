<?php
namespace OpenSkedge\AppBundle\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

use OpenSkedge\AppBundle\Entity\User;
use OpenSkedge\AppBundle\Entity\Clock;

/**
 * Clock controller.
 *
 */
class ClockController extends Controller
{
    public function checklateAction(Request $request)
    {
        if($request->getMethod() == 'POST') {
            $token = $this->container->getParameter('kernel.secret');
            if($request->request->get('clockToken')===$token) {
                // TODO: Check for users who have not clocked in and are 15 mins late or more.
            }
        }
    }

    public function clockInAction(Request $request)
    {
        if (false === $this->get('security.context')->isGranted('ROLE_USER')) {
            throw new AccessDeniedException();
        }

        if(!in_array($request->getClientIp(), array('127.0.0.1', '::1'))) {
            throw new AccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();

        $user = $this->getUser();
        $clock = $user->getClock();
        if(strtotime("last sunday", $clock->getLastClock()) != strtotime("last sunday", time())) {
            static::backupClock(date('m-d-y', strtotime("last sunday", $clock->getLastClock())), $clock);
            static::clearClock($clock);
        }
        $clock->setStatus(true);
        $clock->setLastClock(time());

        $em->persist($clock);
        $em->flush();
        /*$return = array("responseCode" => 200);
        $return = json_encode($return);
        return new Response($return, 200, array('Content-Type'=>'application/json'));*/
        return $this->redirect($this->generateUrl('dashboard'));
    }

    // TODO: Add late checking
    // TODO: Add shift change checking
    public function clockOutAction(Request $request)
    {
        if (false === $this->get('security.context')->isGranted('ROLE_USER')) {
            throw new AccessDeniedException();
        }

        if(!in_array($request->getClientIp(), array('127.0.0.1', '::1'))) {
            throw new AccessDeniedException();
        }

        $em = $this->getDoctrine()->getManager();

        $cur_time = time();

        $user = $this->getUser();
        $clock = $user->getClock();

        $last_clock = $clock->getLastClock();
        $start_time = $last_clock;

        // If the date of last_clock is the previous day, we need to update two timerecords.
        if(date('w', $last_clock) == date('w', $cur_time)-1) {
            $prev_day_end = mktime(23, 59, 59, date('n', $last_clock), date('j', $last_clock), date('Y', $last_clock));
            $getDay = "get".date('D', $last_clock);
            $yesterday_timerecord = static::updateTimeRecord($clock->$getDay(), $last_clock, $prev_day_end);
            $setDay = "set".date('D', $last_clock);
            $clock->$setDay($yesterday_timerecord);
            // The the final timerecord will be a continuation of midnight today until the current time.
            $start_time = mktime(0, 0, 0);
        }

        $getDay = "get".date('D');
        $setDay = "set".date('D');
        $cur_timerecord = $clock->$getDay();
        $timerecord = self::updateTimeRecord($cur_timerecord, $start_time, $cur_time);
        $clock->$setDay($timerecord);
        $clock->setStatus(false);

        $em->persist($clock);
        $em->flush();

        /*$return = array("responseCode" => 200);
        $return = json_encode($return);
        return new Response($return, 200, array('Content-Type'=>'application/json'));*/
        return $this->redirect($this->generateUrl('dashboard'));
    }

    private static function backupClock($week, $clock)
    {
        // TODO: Make this configurable.
    }

    private static function clearClock($clock)
    {

    }

    private static function updateTimeRecord($cur_timerecord, $start, $end)
    {
        $timerecord_array = str_split($cur_timerecord);
        // Get the UNIX timestamp for midnight.
        $daystart = mktime(0, 0, 0);
        // Time record index of the start time (inclusive)
        $startInd = round((($start - $daystart) / 60) / 15)-1;
        // Time record index of the end time (exclusive)
        $endInd = round((($end - $daystart) / 60) / 15);
        for($i=$startInd; $i < $endInd; $i++)
        {
            $timerecord_array[$i] = '1';
        }
        $timerecord = implode('', $timerecord_array);
        return $timerecord;
    }
}