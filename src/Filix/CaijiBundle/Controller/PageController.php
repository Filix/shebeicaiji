<?php

namespace Filix\CaijiBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;


class PageController extends Controller
{
    public function indexAction()
    {
        return $this->render('FilixCaijiBundle:Page:index.html.twig');
    }

}
