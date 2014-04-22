<?php

namespace Filix\CaijiBundle\Controller;

use FOS\UserBundle\Controller\SecurityController as BaseController;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SecurityController extends BaseController
{

    public function loginAction()
    {
        if($this->container->get('security.context')->isGranted('ROLE_USER')){
            $url = $this->container->get('router')->generate('index');
            return new RedirectResponse($url);
        }
        return parent::loginAction();
    }
    
    

}
