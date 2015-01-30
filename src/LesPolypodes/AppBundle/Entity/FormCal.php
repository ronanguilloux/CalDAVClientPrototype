<?php

    namespace LesPolypodes\AppBundle\Entity;

    class FormCal
    {
        protected $name;
        protected $startDate;
        protected $endDate;
        protected $location;
        protected $description;

        public function getName()
        {
            return $this->name;
        }
        public function setName($name)
        {
            $this->name = $name;
        }

        public function getStartDate()
        {
            return $this->startDate;
        }
        
        public function setStartDate(\DateTime $startDate = null)
        {
            $this->startDate = $startDate;
        }

       public function getEndDate()
       {
            return $this->endDate;
       }

       public function setEndDate(\DateTime $endDate = null)
       {
            $this->endDate = $endDate;
       }

       public function getLocation()
       {
            return $this->location;
       }

       public function setLocation($location)
       {
            $this->location = $location;
       }

       public function getDescription()
       {
            return $this->description;
       }

       public function setDescription($description)
       {
            $this->description = $description;
       }

    }