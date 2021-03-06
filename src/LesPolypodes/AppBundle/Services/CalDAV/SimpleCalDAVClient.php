<?php

/**
 * SimpleCalDAVClient
 *
 * Copyright 2014 Michael Palm <palm.michael@gmx.de>
 *
 * simpleCalDAV is a php library that allows you to connect to a calDAV-server to get event-, todo-
 * and free/busy-calendar resources from the server, to change them, to delete them, to create new ones, etc.
 * simpleCalDAV was made and tested for connections to the CalDAV-server Baikal 0.2.7. But it should work
 * with any other CalDAV-server too.
 *
 * It contains the following functions:
 *   - connect()
 *   - findCalendars()
 *   - setCalendar()
 *   - create()
 *   - change()
 *   - delete()
 *   - getEvents()
 *   - getTODOs()
 *   - getCustomReport()
 *
 * All of those functions - except the last one - are realy easy to use, self-explanatory and are
 * deliverd with a big innitial comment, which explains all needed arguments and the return values.
 *
 * This library is heavily based on AgenDAV caldav-client-v2.php by Jorge L�pez P�rez <jorge@adobo.org> which
 * again is heavily based on DAViCal caldav-client-v2.php by Andrew McMillan <andrew@mcmillan.net.nz>.
 * Actually, I hardly added any features. The main point of my work is to make everything straight
 * forward and easy to use. You can use simpleCalDAV whithout a deeper understanding of the
 * calDAV-protocol.
 *
 * Requirements of this library are
 *   - The php extension cURL ( http://www.php.net/manual/en/book.curl.php )
 *   - From Andrew�s Web Libraries: ( https://github.com/andrews-web-libraries/awl )
 *      - XMLDocument.php
 *      - XMLElement.php
 *      - AWLUtilities.php
 *
 * @package simpleCalDAV
 */

namespace LesPolypodes\AppBundle\Services\CalDAV;

/**
 * Class SimpleCalDAVClient
 * @package LesPolypodes\AppBundle\Services\CalDAV
 */
class SimpleCalDAVClient
{

    /**
     * @var CalDAVClient $client
     */
    protected $client;

    /**
     * function connect()
     * Connects to a CalDAV-Server.
     *
     * Arguments:
     * @param string $url  - url to the CalDAV-server. E.g. http://exam.pl/baikal/cal.php/username/calendername/
     * @param string $user - username to login with
     * @param string $pass - password to login with
     *
     * Debugging:
     * @throws CalDAVException
     *                         For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); }
     */
    public function connect($url, $user, $pass)
    {

     //  Connect to CalDAV-Server and log in
        $client = new CalDAVClient($url, $user, $pass);
        if (! $client->isValidCalDAVServer()) {
// die(var_dump($client->GetHttpResultCode()));

            if ($client->GetHttpResultCode() == '401') {
                throw new CalDAVException(sprintf("Login failed at %s", $url), $client);
            } elseif ($client->GetHttpResultCode() == '') {
                throw new CalDAVException(sprintf("Can't reach server at %s, %s HTTP code caught", $url, $client->GetHttpResultCode()), $client);
            } else {
                throw new CalDAVException(sprintf("Couldn't find a CalDAV-collection under the url %s", $url), $client);
            }
        }

     // Check for errors
        if ($client->GetHttpResultCode() != '200') {
            if ($client->GetHttpResultCode() == '401') {
                throw new CalDAVException('Login failed', $client);
            } elseif ($client->GetHttpResultCode() == '') {
                throw new CalDAVException('Can\'t reach server', $client);
            } else { // Unknown status
                throw new CalDAVException(sprintf("Recieved unhandled %d HTTP status while checking the connection after establishing it", $client->GetHttpResultCode()), $client);
            }
        }

        $this->setClient($client);
    }

    /**
     * setCalendar()
     *
     * Sets the actual calendar to work with
     *
     * Debugging:
     * @throws CalDAVException
     *                         For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
     *
     * @param CalDAVCalendar $calendar
     *
     * @throws \Exception
     */
    public function setCalendar(CalDAVCalendar $calendar)
    {
        $this->getClient()->SetCalendar($this->getClient()->first_url_part.$calendar->getURL());
    }

    /**
     * @return CalDAVClient
     * @throws \Exception
     */
    public function getClient()
    {
        if (!isset($this->client)) {
            throw new \Exception('Client undefined ; see self::connect().');
        }

        return $this->client;
    }

    /**
     * @param  CalDAVClient $client
     * @return $this
     * @throws \Exception
     */
    public function setClient(CalDAVClient $client)
    {
        if (!isset($client)) {
            throw new \InvalidArgumentException('$client paramter undefined');
        }

        $this->client = $client;

        return $this;
    }

    /**
     * Creates a new calendar resource on the CalDAV-Server (event, todo, etc.).
     *
     * @param string raw ICS iCalendar-data of the resource you want to create.
     *                    Notice: The iCalendar-data contains the unique ID which specifies where the event is being saved.
     *
     * @return CalDAVObject    - An CalDAVObject-representation (see CalDAVObject.php) of your created resource
     * @throws CalDAVException
     * @throws \Exception
     */
    public function create($cal)
    {
        $this->checkCalendar();

        // Parse $cal for UID
        if (! preg_match('#^UID:(.*?)\r?\n?$#m', $cal, $matches)) {
            throw new \Exception('Can\'t find UID in $cal');
        } else {
            $uid = $matches[1];
        }

        // Is there a '/' at the end of the calendar_url?
        if (! preg_match('#^.*?/$#', $this->getClient()->calendar_url, $matches)) {
            $url = $this->getClient()->calendar_url.'/';
        } else {
            $url = $this->getClient()->calendar_url;
        }
        //$url .= '/';

        // Looking for $url.$uid.'.ics'
        $result = $this->getClient()->GetEntryByHref($url.$uid.'.ics');
        if ($this->getClient()->GetHttpResultCode() == '200') {
            throw new CalDAVException($url.$uid.'.ics already exists. UID not unique?', $this->getClient());
        } elseif ($this->getClient()->GetHttpResultCode() == '404') {
        } else {
            throw new CalDAVException(sprintf("Received unhandled %d HTTP status", $this->getClient()->GetHttpResultCode()), $this->getClient());
        }

        $newEtag = $this->getClient()->DoPUTRequest($url.$uid.'.ics', $cal);

        if ($this->getClient()->GetHttpResultCode() != '201') {
            if ($this->getClient()->GetHttpResultCode() == '204') {
            // $url.$uid.'.ics' already existed on server
                throw new CalDAVException($url.$uid.'.ics already existed. Entry has been overwritten.', $this->getClient());
            } else {
                throw new CalDAVException(sprintf("Using %s, received unhandled %d HTTP status", $url, $this->getClient()->GetHttpResultCode()), $this->getClient());
            }
        }

        return new CalDAVObject($url.$uid.'.ics', $cal, $newEtag);
    }

    /**
     * @return boolean    calendar exist
     * @throws \Exception
     */
    public function checkCalendar()
    {
        if (!isset($this->getClient()->calendar_url)) {
            throw new \Exception('No calendar selected. Try findCalendars() and setCalendar().');
        }

        return true;
    }

    /**
     * Changes a calendar resource (event, todo, etc.) on the CalDAV-Server.
     *
     * @param string $href     see CalDAVObject.php
     * @param object $new_data the new iCalendar-data that should be used to overwrite the old one.
     * @param string $etag     see CalDAVObject.php
     *
     * @return CalDAVObject    a CalDAVObject-representation (see CalDAVObject.php) of your changed resource
     * @throws CalDAVException
     * @throws \Exception
     */
    public function change($href, $new_data, $etag)
    {
        $this->checkCalendar();

     // Is there a '/' at the end of the url?
        if (! preg_match('#^.*?/$#', $this->getClient()->calendar_url, $matches)) {
            $url = $this->getClient()->calendar_url.'/';
        } else {
            $url = $this->getClient()->calendar_url;
        }

     // Does $href exist?
        $result = $this->getClient()->GetEntryByHref($href);
        if ($this->getClient()->GetHttpResultCode() == '200') {
        } elseif ($this->getClient()->GetHttpResultCode() == '404') {
            throw new CalDAVException('Can\'t find '.$href.' on the server', $this->getClient());
        } else {
            throw new CalDAVException('Recieved unknown HTTP status', $this->getClient());
        }

     // $etag correct?
        if ($result[0]['etag'] != $etag) {
            throw new CalDAVException('Wrong entity tag. The entity seems to have changed.', $this->getClient());
        }

     // Put it!
        $newEtag = $this->getClient()->DoPUTRequest($href, $new_data, $etag);

     // PUT-request successfull?
        if ($this->getClient()->GetHttpResultCode() != '204' && $this->getClient()->GetHttpResultCode() != '200') {
            throw new CalDAVException('Recieved unknown HTTP status', $this->getClient());
        }

        return new CalDAVObject($href, $new_data, $etag);
    }

    /**
     * function delete()
     * Delets an event or a TODO from the CalDAV-Server.
     *
     * Arguments:
     * @param $href See CalDAVObject.php
     * @param $etag See CalDAVObject.php
     *
     * Debugging:
     * @throws CalDAVException
     *                         For debugging purposes, just sorround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
     */
    public function delete($href, $etag)
    {
        $this->checkCalendar();
        
    // Is there a '/' at the end of the url?
        if (! preg_match('#^.*?/$#', $this->getClient()->calendar_url, $matches)) {
            $url = $this->getClient()->calendar_url.'/';
        } else {
            $url = $this->getClient()->calendar_url;
        }

     // Does $href exist?
        $result = $this->getClient()->GetEntryByHref($href);

        if (count($result) == 0) {
            throw new CalDAVException('Can\'t find '.$href.'on server', $this->getClient());
        }

     // $etag correct?
        if ($result[0]['etag'] != $etag) {
            throw new CalDAVException('Wrong entity tag. The entity seems to have changed.', $this->getClient());
        }

     // Do the deletion
        $this->getClient()->DoDELETERequest($href, $etag);


     // Deletion successfull?
        if ($this->getClient()->GetHttpResultCode() != '200' and $this->getClient()->GetHttpResultCode() != '204') {
            throw new CalDAVException('Recieved unknown HTTP status', $this->getClient());
        }
    }


    /**
     * Create a calendar.
     *
     * @author https://github.com/Peshmelba
     *
     * @param string $calendarName          The name of the calendar to create.
     * @param string $calendarDescription   The Description of the new calendar.
     * @param string $datas                 An VCALENDAR with TIMEZONE datas.
     */
    public function makeCal($calendarName, $calendarDescription)
    {
        $EmptyVCAL = <<<VCALENDAR
BEGIN:VCALENDAR
PRODID:-//ODE Dev//CalDAV Client//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:Europe/London
BEGIN:DAYLIGHT
DTSTART:20150101T000000
TZOFFSETFROM:+0000
TZOFFSETTO:+0000
END:DAYLIGHT
END:VTIMEZONE
END:VCALENDAR
VCALENDAR;

        $st = $this->getClient()->DoMKCALENDARRequest($calendarName, $calendarDescription, $EmptyVCAL);

        if ($st == '201')
        {
            return true;
        }
        else
        {
            throw new CalDAVException(sprintf("Can\'t create a Calendar Collection %s at %s.
                %d HTTP status",
                $calendarName,
                $this->getClient()->GetFullUrl(),
                $this->getClient()->GetHttpResultCode()
                ), $this->getClient());
        }
    }


    /**
     * Delete the given calendar from the server
     *
     * @author https://github.com/Peshmelba
     * @param string $calendarName
     *
     * @throws \Exception
     */
    function deleteCal($calendarName)
    {
        $calendarID = $this->findCalendarIDByName($calendarName);
        // $calendarID = $this->findCalendarIDByName($calendarName);

        if ($calendarID == null) {
            throw new \Exception('No calendar found with the name "'.$calendarName.'".');
        }

        $this->getClient()->DoRMCALRequest($calendarID);
    }


    /**
     * Gets a all events from the CalDAV-Server which lie in a defined time interval.
     *
     * @param null $start  the starting point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
     *                     GMT. If omitted the value is set to -infinity.
     * @param null $finish the end point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
     *                     GMT. If omitted the value is set to +infinity.
     *
     * @return CalDAVObject[]           of CalDAVObjects (See CalDAVObject.php), representing the found events.
     * @throws CalDAVException
     * @throws \Exception
     */
    public function getEvents($start = null, $finish = null)
    {
        $this->checkCalendar();

        if (( isset($start) and ! preg_match('#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $start, $matches) )
            or ( isset($finish) and ! preg_match('#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $finish, $matches) ) ) {
            trigger_error('$start or $finish are in the wrong format. They must have the format yyyymmddThhmmssZ and should be in GMT', E_USER_ERROR);
        }

        $results = $this->getClient()->GetEvents($start, $finish);

        if ($this->getClient()->GetHttpResultCode() != '207') {
            throw new CalDAVException(sprintf("Unhandled %d HTTP status", $this->getClient()->GetHttpResultCode()), $this->getClient());
        }

        $report = array();
        foreach ($results as $event) {
            $report[] = new CalDAVObject($event['href'], $event['data'], $event['etag']);
        }

        return $report;
    }

    /**
     *
     * Gets a all TODOs from the CalDAV-Server which lie in a defined time interval and match the
     * given criteria.
     *
     * @param null $start     the starting point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
     *                        GMT. If omitted the value is set to -infinity.
     * @param null $finish    the end point of the time interval. Must be in the format yyyymmddThhmmssZ and should be in
     *                        GMT. If omitted the value is set to +infinity.
     * @param null $completed a filter for completed tasks (true) or for uncompleted tasks (false). If omitted, the function will return both.
     * @param null $cancelled a filter for cancelled tasks (true) or for uncancelled tasks (false). If omitted, the function will return both.
     *
     * @return array           of CalDAVObjects (See CalDAVObject.php), representing the found TODOs.
     * @throws CalDAVException
     * @throws \Exception
     */
    public function getTODOs($start = null, $finish = null, $completed = null, $cancelled = null)
    {
        $this->checkCalendar();

        if (( isset($start) and ! preg_match('#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $start, $matches) )
        or ( isset($finish) and ! preg_match('#^\d\d\d\d\d\d\d\dT\d\d\d\d\d\dZ$#', $finish, $matches) ) ) {
            trigger_error('$start or $finish are in the wrong format. They must have the format yyyymmddThhmmssZ and should be in GMT', E_USER_ERROR);
        }

        $results = $this->getClient()->GetTodos($start, $finish, $completed, $cancelled);
        //throw new CalDAVException('', $this->getClient()); // WTF is that non-sense Exception doing here?
        if ($this->getClient()->GetHttpResultCode() != '207') {
            throw new CalDAVException('Recieved unknown HTTP status', $this->getClient());
        }

        $report = array();
        foreach ($results as $event) {
            $report[] = new CalDAVObject($event['href'], $event['data'], $event['etag']);
        }

        return $report;
    }

    /**
     * Sends a REPORT-request with a custom <C:filter>-tag.
     * @see http://www.rfcreader.com/#rfc4791_line1524 for more information about how to write filters.
     * @param string $filter the stuff you want to send encapsuGetEntryByHrefted in the <C:filter>-tag.
     *
     * @return array           of CalDAVObjects (See CalDAVObject.php), representing the found calendar resources.
     * @throws CalDAVException
     */
    public function getCustomReport($filter)
    {
        $this->checkCalendar();

        $this->getClient()->SetDepth('1');

        $results = $this->getClient()->DoCalendarQuery('<C:filter>'.$filter.'</C:filter>');
        //throw new CalDAVException('', $this->getClient()); // WTF is this line for?
        if ($this->getClient()->GetHttpResultCode() != '207') {
            throw new CalDAVException('Recieved unknown HTTP status', $this->getClient());
        }

        $report = array();
        foreach ($results as $event) {
            $report[] = new CalDAVObject($event['href'], $event['data'], $event['etag']);
        }

        return $report;
    }


    /**
     * identifie l'id d'un calendrier grâce à son nom.
     *
     * @author https://github.com/Peshmelba
     *
     * @param string $name nom du calendrier cherché.
     *
     * @return int|null id du calendrier cherché, ou null si aucun résultat.
     */
    public function findCalendarIDByName($name)
    {
        $calendarID = null;

        foreach ($this->findCalendars() as $key => $value) {
            if ($value->getDisplayName() === $name) {
                $calendarID = $key;
                break;
            }
        }
        return $calendarID;
    }


    /**
     * findCalendars()
     *
     * Requests a list of all accessible calendars on the server
     *
     * Return value:
     * @return an array of CalDAVCalendar-Objects (see CalDAVCalendar.php), representing all calendars accessible by the current principal (user).
     *
     * Debugging:
     * @throws CalDAVException
     *                         For debugging purposes, just surround everything with try { ... } catch (Exception $e) { echo $e->__toString(); exit(-1); }
     *
     * @return array
     * @throws \Exception
     */
    public function findCalendars()
    {
        return $this->getClient()->FindCalendars(true);
    }


    /**
     * Set the current calendar with his display name.
     * 
     * @author https://github.com/Peshmelba
     * @param string $calendarName
     *
     * @throws \Exception
     *
     * @return void
     */
    public function setCalendarByName($calendarName)
    {
        $calendarID = $this->findCalendarIDByName($calendarName);

        if ($calendarID == null) {
            throw new \Exception('No calendar found with the name "'.$calendarName.'".');
        }

        $this->setCalendar($this->findCalendars()[$calendarID]);
    }
}
