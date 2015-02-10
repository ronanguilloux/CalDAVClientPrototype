<?php

namespace LesPolypodes\AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sabre\VObject;
use Faker;
use LesPolypodes\AppBundle\Services\CalDAV\SimpleCalDAVClient;
use Sabre\DAV;
use LesPolypodes\AppBundle\Entity\FormCal;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\Request;
use LesPolypodes\AppBundle\Services\CalDAVConnection;

class EventsController extends BaseController
{
    protected function createVCal($event)
    {
        $faker = Faker\Factory::create('fr_FR');

        $vcal = new VObject\Component\VCalendar();
        $vcal->PRODID = '-//ODE Dev//Form FR';

        $vtimezone = $vcal->add('VTIMEZONE');
        $vtimezone->add('TZID', 'Europe/London');

        $vtimezone->add('BEGIN', 'DAYLIGHT');
        $vtimezone->add('TZOFFSETFROM', '+0000');
        $vtimezone->add('TZOFFSETTO', '+0000');
        $vtimezone->add('DTSTART', (new \DateTime())->format('Ymd\THis'));
        $vtimezone->add('END', 'DAYLIGHT');

        $vevent = $vcal->add('VEVENT');

        $uid = $faker->numerify('ODE-####-####-####-####');

        $vevent->add('ORGANIZER', $event->getOrganizer());
        $vevent->add('CREATED', (new \DateTime())->format('Ymd\THis\Z'));
        $vevent->add('DTSTAMP', (new \DateTime())->format('Ymd\THis\Z'));
        $vevent->add('UID', $uid);
        // $vevent->add('TRANSP', array('OPAQUE', 'TRANSPARENT')[rand(0,1)]);
        $vevent->add('SUMMARY', $event->getName());
        $vevent->add('LOCATION', $event->getLocation());
        $vevent->add('DTSTART', $event->getStartDate()->format('Ymd\THis'));
        $vevent->add('DTEND', $event->getEndDate()->format('Ymd\THis'));
        $vevent->add('X-ODE-PRICE', sprintf('%d€', $event->getPrice()));
        $vevent->add('DESCRIPTION', $event->getDescription());

        return $vcal;
    }

    protected function createFakeVCal()
    {
        // see: https://github.com/fzaninotto/Faker
        $faker = Faker\Factory::create('fr_FR');

        $vcal = new VObject\Component\VCalendar();
        $vcal->PRODID = '-//ODE Dev//Faker FR';

        $vtimezone = $vcal->add('VTIMEZONE');
        $vtimezone->add('TZID', 'Europe/London');

        $vtimezone->add('BEGIN', 'DAYLIGHT');
        $vtimezone->add('TZOFFSETFROM', '+0000');
        $vtimezone->add('TZOFFSETTO', '+0000');
        $vtimezone->add('DTSTART', (new \DateTime())->format('Ymd\THis'));
        $vtimezone->add('END', 'DAYLIGHT');

        $vevent = $vcal->add('VEVENT');

        $uid = $faker->numerify('ODE-####-####-####-####');

        $datevent = $faker->dateTimeBetween('now', '+1 day');
        $transparencies = array('OPAQUE', 'TRANSPARENT');
        $transparency = array_rand($transparencies);
        $vevent->add('ORGANIZER', $faker->companyEmail);
        $vevent->add('CREATED', (new \DateTime())->format('Ymd\THis\Z'));
        $vevent->add('DTSTAMP', (new \DateTime())->format('Ymd\THis\Z'));
        $vevent->add('UID', $uid);
        $vevent->add('TRANSP', $trasparency);
        $vevent->add('SUMMARY', $faker->sentence(2));
        $vevent->add('LOCATION', $faker->streetAddress);
        $vevent->add('DTSTART', $datevent->format('Ymd\THis'));
        $vevent->add('DTEND', $datevent->add(new \DateInterval('PT1H'))->format('Ymd\THis'));
        $vevent->add('X-ODE-PRICE', sprintf('%d€', $faker->randomFloat(2, 0, 100)));
        $vevent->add('DESCRIPTION', $faker->paragraph(3));

        return $vcal;
    }

    protected function persistEvent($calName, $vcal)
    {   
        $this->setCalendarSCDC($calName);

        $rawcal = $vcal->serialize();

        if ($this->scdClient->create($rawcal) == null)
            throw new \Exception('Can\'t persist the event named "'.$vcal->VEVENT->SUMMARY.'".');

        return $rawcal;
    }

    public function scdcListAction($serv)
    {
        $this->getSimplecalDavClient($serv);
        $calendars = $this->scdClient->findCalendars();

        $result = array();
        foreach($calendars as $i=>$calendar) {
            $this->setCalendarSCDC($calendar->getDisplayName());
            $events = $this->scdClient->getEvents();
            $result[$i] = array(
                "calendar" => $calendar,
                "length" => count($events)
            );
        }

        return $this->render('LesPolypodesAppBundle:Events:scdcList.html.twig', array(
            'result' => $result
        ));
    }


    public function scdcListEventAction($name, $serv, $start, $end)
    {
        $this->getSimplecalDavClient($serv);

        $this->setCalendarSCDC($name);
        $events = $this->scdClient->getEvents($start, $end);
        
        $parser = new VObject\Reader();

        $dataContainer = new \stdClass();
        $dataContainer->vcal = null;
        $dataContainer->dateStart = null;
        $dataContainer->dateEnd = null;

        foreach ($events as $event) {
            $vcal = $parser->read($event->getData());
            $dataContainer->vcal = $vcal;
            $dataContainer->dateStart = (new \datetime($vcal->VEVENT->DTSTART))->format('Y-m-d H:i');
            $dataContainer->dateEnd = (new \datetime($vcal->VEVENT->DTEND))->format('Y-m-d H:i');

            $datas[] = clone $dataContainer;
        }

        return $this->render('LesPolypodesAppBundle:Events:scdcListEvent.html.twig', array(
            'name' => $name,
            'datas' => $datas,
        ));
    }

     public function scdcListEventRawAction($name, $serv, $start, $end)
    {
        $this->getSimplecalDavClient($serv);

        $this->setCalendarSCDC($name);
        $events = $this->scdClient->getEvents($start, $end);

        return $this->render('LesPolypodesAppBundle:Events:scdcListEventRaw.html.twig', array(
            'events' => $events,
        ));
    }

    public function createAction($serv)
    {
        $this->getSimplecalDavClient($serv);

        $vcal = $this->createFakeVCal();

        $this->persistEvent($this->caldav_maincal_name, $vcal);

        return $this->render('LesPolypodesAppBundle:Events:create.html.twig', array(
            'vcal' => $vcal->serialize(),
            'name' => $this->caldav_maincal_name,
        ));
    }

    public function indexAction($serv)
    {
        // $this->container->

        return $this->render('LesPolypodesAppBundle:Events:index.html.twig');
    }

    public function updateAction($serv)
    {
        // TODO: update 1 event
        // TODO: all events between 2 datetimes
        // ! Think about rollback
        return $this->render('LesPolypodesAppBundle:Events:update.html.twig');
    }

    public function deleteAction($name, $id, $serv)
    {
        $this->getSimplecalDavClient($serv);

        $this->setCalendarSCDC($name);
        $events = $this->scdClient->getEvents();

        $parser = new VObject\Reader();

        foreach ($events as $event) {

            $vcal = $parser->read($event->getData());

            if ($vcal->VEVENT->UID == $id)
            {
                break;
            }
        }

        $this->scdClient->delete($event->getHref(), $event->getEtag());

        return $this->render('LesPolypodesAppBundle:Events:delete.html.twig', array(
            'name' => $name,
            'datas' => $datas,
            ));
    }

    public function deleteAllAction($name, $serv)
    {
        $this->getSimplecalDavClient($serv);

        $this->setCalendarSCDC($name);
        $events = $this->scdClient->getEvents();


        while($events[0] != null)
        {
            $this->scdClient->delete($events[0]->getHref(), $events[0]->getEtag());

            $this->getSimplecalDavClient($serv);

            $this->setCalendarSCDC($name);
            $events = $this->scdClient->getEvents();
        }

        return $this->render('LesPolypodesAppBundle:Events:deleteAll.html.twig', array(
            'name' => $name,
            ));
    }

    public function formAction(Request $request, $serv)
    {
        $this->getSimplecalDavClient($serv);

        $event = new FormCal();
        // Valeurs par défaut
        $event->setName('Nom de l\'évènement');
        $event->setStartDate(new \DateTime());
        $event->setEndDate((new \DateTime())->add(new \DateInterval('PT1H')));
        $event->setLocation('Adresse de l\'évènement');
        $event->setDescription('Décrivez votre évènement');
        $event->setPrice('0');
        $event->setOrganizer('organisateur@exemple.com');
        
        $form = $this->createFormBuilder($event)
            ->add('name', 'text')
            ->add('startDate', 'datetime')
            ->add('endDate', 'datetime')
            ->add('location', 'text')
            ->add('description', 'textarea')
            ->add('price', 'money')
            ->add('organizer','email')
            ->add('Valider', 'submit')
            ->getForm();
        
        $form->handleRequest($request);

        if($form->isValid())
        {
            $vcal = $this->createVCal($event);

            $this->persistEvent($this->caldav_maincal_name, $vcal);

            return $this->redirect($this->generateUrl('les_polypodes_app_list_event_raw', array(
                'name' => $this->caldav_maincal_name,
                'serv' => $serv,
            )));
        }
        
        return $this->render('LesPolypodesAppBundle:Events:form.html.twig', array(
            'form' => $form->createView(),
            ));       
    }

    public function devInsertAction($name, $n, $type, $serv)
    {
        $this->getSimplecalDavClient($serv);

        switch ($type){
            case 'standard' :
                for ($i = 0; $i < $n; $i++)
                {
                    $vcal = $this->createFakeVCal();

                    $this->persistEvent($name, $vcal);
                }
                break;
            case 'compressed' :
            // Not Working. Send 400 html code when VCAL contains multiple VEVENT with differents UID
                $vcal = new VObject\Component\VCalendar();
                $vcal->PRODID = '-//ODE Dev//Faker//FR';

                for ($i = 0; $i < $n; $i++)
                {
                    $vcal->add($this->createFakeVCal()->VEVENT);
                }

                $this->persistEvent($name, $vcal);
                break;
        }

        return $this->forward('LesPolypodesAppBundle:Events:scdcListEvent', array(
                'name' => $name,
                'serv' => $serv,
            ));
    }
}