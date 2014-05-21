<?php

namespace Filix\CaijiBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="logs")
 * @ORM\Entity(repositoryClass="Filix\CaijiBundle\Repository\LogRepository")
 */
class Log
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    
    /**
     *
     * @ORM\Column(type="text", nullable=true)
     */
    protected $data;
    
    /**
     * 设备采集时间，客户端上传
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $created_at;
    
    /**
     * 数据上传时间
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $uploaded_at;


    /**
     * @ORM\ManyToOne(targetEntity="User", inversedBy="logs")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id")
     */
    protected $user;
    
    public function __construct()
    {
        $this->uploaded_at = new \DateTime();
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
     * Set user
     *
     * @param \Filix\CaijiBundle\Entity\User $user
     * @return Log
     */
    public function setUser(\Filix\CaijiBundle\Entity\User $user = null)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get user
     *
     * @return \Filix\CaijiBundle\Entity\User 
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set data
     *
     * @param string $data
     * @return Log
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * Get data
     *
     * @return string 
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Set created_at
     *
     * @param \DateTime $createdAt
     * @return Log
     */
    public function setCreatedAt($createdAt)
    {
        $this->created_at = $createdAt;

        return $this;
    }

    /**
     * Get created_at
     *
     * @return \DateTime 
     */
    public function getCreatedAt()
    {
        return $this->created_at;
    }

    /**
     * Set uploaded_at
     *
     * @param \DateTime $uploadedAt
     * @return Log
     */
    public function setUploadedAt($uploadedAt)
    {
        $this->uploaded_at = $uploadedAt;

        return $this;
    }

    /**
     * Get uploaded_at
     *
     * @return \DateTime 
     */
    public function getUploadedAt()
    {
        return $this->uploaded_at;
    }
}
