<?php

namespace Filix\CaijiBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Filix\CaijiBundle\Entity\Log;
use Filix\CaijiBundle\Entity\User;

class ApiController extends Controller
{

    const ERROR_CODE = 500;
    const SUCCESS_CODE = 200;

    /**
     * 增加记录
     * @Route("/data/add", name="log_add")
     * @Method({"POST"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="add feed",
     *  filters={
     *      {"name"="uid", "desc"="user id"},
     *      {"name"="data", "desc"="data"}
     * }
     * )
     */
    public function addAction()
    {
        $uid = $this->getRequest()->get("uid");
        $data = $this->getRequest()->get('data');
        if (!$user = $this->getUserRepository()->find($uid)) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        $log = new Log();
        $log->setUser($user);
        $log->setData($data);
        $this->getDoctrineManager()->persist($log);
        $this->getDoctrineManager()->flush();
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '添加成功',
            'data' => $this->formatLog($log)
        ));
    }

    /**
     * 获取记录
     * @Route("/data/list", name="log_list")
     * @Method({"POST"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="data list",
     *  filters={
     *      {"name"="uid", "desc"="user id"},
     *      {"name"="offset", "desc"="offset"},
     *      {"name"="limit", "desc"="limit"}
     * }
     * )
     */
    public function listAction()
    {
        $uid = $this->getRequest()->get("uid");
        $offset = $this->getRequest()->get("offset", 0);
        $limit = $this->getRequest()->get("limit", 10);
        $limit++;
        if (!$user = $this->getUserRepository()->find($uid)) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        $logs = $this->getLogRepository()->getUserLogs($user, $offset, $limit);
        $t = array();
        foreach ($logs as $log) {
            $t[] = $this->formatLog($log);
        }
        $next = false;
        if (count($t) >= $limit) {
            $next = true;
            unset($t[$limit - 1]);
        }
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '获取成功',
            'data' => $t,
            'has_next' => $next
        ));
    }

    /**
     * 登录
     * @Route("/passport/login", name="passport_login")
     * @Method({"POST"})
     * @ApiDoc(
     *  section="Api Passport",
     *  description="login",
     *  filters={
     *      {"name"="email", "desc"="email"},
     *      {"name"="password", "desc"="password"}
     * }
     * )
     */
    public function loginAction()
    {
        $email = $this->getRequest()->get("email");
        $password = $this->getRequest()->get("password");
        $user = $this->get('fos_user.user_manager')
                ->findUserByEmail($email);
        if(is_null($user))
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '账号或密码错误'));
        
        if($this->checkPassword($user, $password)){
            $response = new JsonResponse(array(
                'code' => self::SUCCESS_CODE,
                'msg' => '登录成功',
                'data' => $this->formatUser($user),
            ));
            $this->login($user, $response);

            return $response;
        }

        return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '账号或密码错误'));
    }
    
    private function login($user, $response = null){
        $this->get('fos_user.security.login_manager')
                ->loginUser(
                    $this->container->getParameter('fos_user.firewall_name'),
                    $user,
                    $response
                );
    }

    private function checkPassword($user, $password){
        return $user->getPassword() === $this->container
                ->get('security.encoder_factory')
                ->getEncoder($user)
                ->encodePassword($password, $user->getSalt());
    }
    
    protected function formatLog(Log $log)
    {
        return array(
            'id' => $log->getId(),
            'data' => $log->getData(),
            'time' => $log->getCreatedAt()->format("Y-m-d H:i:s")
        );
    }
    
    protected function formatUser(User $user)
    {
        return array(
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail()
        );
    }

    protected function getUserRepository()
    {
        return $this->getDoctrine()->getRepository('FilixCaijiBundle:User');
    }

    protected function getLogRepository()
    {
        return $this->getDoctrine()->getRepository('FilixCaijiBundle:Log');
    }

    protected function getDoctrineManager()
    {
        return $this->getDoctrine()->getManager();
    }

}
