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
     * @Route("/passport/register", name="passport_register")
     * @Method({"POST"})
     * @ApiDoc(
     *  section="Api Passport",
     *  description="register",
     *  filters={
     *      {"name"="name", "desc"="name", "type"="string"},
     *      {"name"="email", "desc"="email", "type"="string"},
     *      {"name"="password", "desc"="password", "type"="string"},
     *      {"name"="password2", "desc"="password2", "type"="string"},
     *      {"name"="sex", "desc"="sex", "type"="int, 1male 2female"},
     *      {"name"="birthday", "desc"="birthday", "type"="string, 1988-01-20"},
     *      {"name"="weight", "desc"="weight", "type"="double, 120.0"},
     *      {"name"="height", "desc"="height", "type"="double, 180.0"},
     *      {"name"="goal", "desc"="goal", "type"="int"},
     *      {"name"="step_length", "desc"="step_length", "type"="double, 0.5"}, 
     * }
     * )
     */
    public function registerAction(){
        $um = $this->get('fos_user.user_manager');
        $name = trim($this->getRequest()->get("name"));
        $email = trim($this->getRequest()->get("email"));
        $password = trim($this->getRequest()->get("password"));
        $password2 = trim($this->getRequest()->get("password2"));
        $sex = trim($this->getRequest()->get("sex"));
        $birthday = trim($this->getRequest()->get("birthday"));
        $weight = trim($this->getRequest()->get("weight"));
        $height = trim($this->getRequest()->get("height"));
        $goal = trim($this->getRequest()->get("goal"));
        $step_length = trim($this->getRequest()->get("step_length"));
        
        if(!$name || !$email || !$password || !$password2 || !$birthday || !$weight || !$height || !$goal || !$step_length){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '所有项必填'));
        }
        if($um->findUserByUsername($name)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户名已存在'));
        }
        if($um->findUserByEmail($email)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => 'email已存在'));
        }
        if($password != $password2){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '两次密码不一致'));
        }
        $user = $um->createUser();
        $user->setUsername($name);
        $user->setEmail($email);
        $user->setPlainPassword($password);
        $user->setSex($sex);
        $date = explode("-", $birthday);
        $datetime = new \DateTime();
        $user->setBirthday($datetime->setDate($date[0], $date[1], $date[2]));
        $user->setWeight($weight);
        $user->setHeight($height);
        $user->setGoal($goal);
        $user->setStepLength($step_length);
        $user->setEnabled(true);
        $um->updateUser($user);
        return new JsonResponse(array(
                'code' => self::SUCCESS_CODE,
                'msg' => '注册成功',
                'data' => $this->formatUser($user),
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
            'email' => $user->getEmail(),
            'sex' => $user->getSex(),
            'birthday' => $user->getBirthday()->format('Y-m-d'),
            'weight' => $user->getWeight(),
            'height' => $user->getHeight(),
            'goal' => $user->getGoal(),
            'token' => $user->getToken(),
            'step_length' => $user->getStepLength()
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
