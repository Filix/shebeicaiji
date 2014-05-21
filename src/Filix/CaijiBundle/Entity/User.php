<?php
namespace Filix\CaijiBundle\Entity;

use FOS\UserBundle\Entity\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity
 * @ORM\Table(name="users")
 * @ORM\Entity(repositoryClass="Filix\CaijiBundle\Repository\UserRepository")
 */
class User extends BaseUser
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    
    /**
     *
     * @ORM\Column(type="smallint")
     */
    protected $sex = 0;
    
    /**
     * @ORM\Column(type="date")
     */
    protected $birthday;
    
    /**
     * @ORM\Column(type="float")
     */
    protected $weight;
    
    /**
     * @ORM\Column(type="float")
     */
    protected $height;
    
    /**
     * @ORM\Column(type="integer")
     */
    protected $goal;
    
    /**
     * @ORM\Column(type="string", length=32)
     */
    protected $token;
    
    /**
     * @ORM\Column(type="float")
     */
    protected $step_length;
    
    /**
     * @ORM\Column(type="string")
     */
    protected $avatar;


    /**
     * @ORM\OneToMany(targetEntity="Log", mappedBy="user")
     */
    protected $logs;


    public function __construct()
    {
        parent::__construct();
        $this->logs = new ArrayCollection();
        $this->token = md5(time()+  rand(100000, 999999) + "user_token");
    }
    
    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }
    
    

    /**
     * Add logs
     *
     * @param \Filix\CaijiBundle\Entity\Log $logs
     * @return User
     */
    public function addLog(\Filix\CaijiBundle\Entity\Log $logs)
    {
        $this->logs[] = $logs;

        return $this;
    }

    /**
     * Remove logs
     *
     * @param \Filix\CaijiBundle\Entity\Log $logs
     */
    public function removeLog(\Filix\CaijiBundle\Entity\Log $logs)
    {
        $this->logs->removeElement($logs);
    }

    /**
     * Get logs
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getLogs()
    {
        return $this->logs;
    }
    

    /**
     * Set sex
     *
     * @param integer $sex
     * @return User
     */
    public function setSex($sex)
    {
        $this->sex = $sex;

        return $this;
    }

    /**
     * Get sex
     *
     * @return integer 
     */
    public function getSex()
    {
        return $this->sex;
    }

    /**
     * Set birthday
     *
     * @param \DateTime $birthday
     * @return User
     */
    public function setBirthday(\DateTime $birthday)
    {
        $this->birthday = $birthday;

        return $this;
    }

    /**
     * Get birthday
     *
     * @return \DateTime 
     */
    public function getBirthday()
    {
        return $this->birthday;
    }

    /**
     * Set weight
     *
     * @param float $weight
     * @return User
     */
    public function setWeight($weight)
    {
        $this->weight = $weight;

        return $this;
    }

    /**
     * Get weight
     *
     * @return float 
     */
    public function getWeight()
    {
        return $this->weight;
    }

    /**
     * Set height
     *
     * @param float $height
     * @return User
     */
    public function setHeight($height)
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Get height
     *
     * @return float 
     */
    public function getHeight()
    {
        return $this->height;
    }

    /**
     * Set goal
     *
     * @param integer $goal
     * @return User
     */
    public function setGoal($goal)
    {
        $this->goal = $goal;

        return $this;
    }

    /**
     * Get goal
     *
     * @return integer 
     */
    public function getGoal()
    {
        return $this->goal;
    }

    /**
     * Set token
     *
     * @param string $token
     * @return User
     */
    public function setToken($token)
    {
        $this->token = $token;

        return $this;
    }

    /**
     * Get token
     *
     * @return string 
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set step_length
     *
     * @param float $stepLength
     * @return User
     */
    public function setStepLength($stepLength)
    {
        $this->step_length = $stepLength;

        return $this;
    }

    /**
     * Get step_length
     *
     * @return float 
     */
    public function getStepLength()
    {
        return $this->step_length;
    }

    /**
     * Set avatar
     *
     * @param string $avatar
     * @return User
     */
    public function setAvatar($avatar)
    {
        $this->avatar = $avatar;

        return $this;
    }

    /**
     * Get avatar
     *
     * @return string 
     */
    public function getAvatar()
    {
        return $this->avatar;
    }
}
