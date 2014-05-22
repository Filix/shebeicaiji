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
     *      {"name"="token", "desc"="user token"},
     *      {"name"="data", "desc"="data"},
     *      {"name"="created_at", "desc"="created time"},
     * }
     * )
     */
    public function addAction()
    {
        $token = $this->getRequest()->get("token");
        $data = $this->getRequest()->get('data');
        $created_at = $this->getRequest()->get('created_at');
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        $log = new Log();
        $log->setUser($user);
        $log->setData($data);
        $datetime = new \DateTime();
        $log->setCreatedAt($datetime->setTimestamp(strtotime($created_at)));
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
     *      {"name"="token", "desc"="user token"},
     *      {"name"="date", "desc"="begin date", "type"="string, 2014-05-10", "default"="today"},
     *      {"name"="days", "desc"="days", "type"="int, 1", "default"="1"}
     * }
     * )
     */
    public function listAction()
    {
        $token = $this->getRequest()->get("token");
        $date = $this->getRequest()->get("date", date('Y-m-d'));
        $days = $this->getRequest()->get("days", 1);
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        $logs = $this->getLogRepository()->getUserLogs($user, $date, $days);
        $t = array();
        foreach ($logs as $log) {
            $t[] = $this->formatLog($log);
        }
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '获取成功',
            'data' => $t,
        ));
    }
    
    /**
     * 获取记录
     * @Route("/user/avatar", name="user_avatar")
     * @Method({"POST"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="data list",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="avatar", "desc"="avatar image", "type"="file"}
     * }
     * )
     */
    public function avatarAction()
    {
        $token = $this->getRequest()->get("token");
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        $avatar = $this->getRequest()->files->get('avatar');
        if($avatar == null){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '未选择图片'));
        }
        $localpath = $this->container->getParameter('image_upload_dir').'avatars/';
        $filename = md5($user->getId() . time() . rand(10000, 99999)) . 
                '.' . strtolower(trim(substr(strrchr($avatar->getClientOriginalName(), '.'), 1, 10)));
        try{
            $fs = new \Symfony\Component\Filesystem\Filesystem();
            $fs->mkdir($localpath);
            $avatar->move($localpath, $filename);
            $user->setAvatar($filename);
            $dm = $this->getDoctrineManager();
            $dm->persist($user);
            $dm->flush();
        }catch(Exception $e){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '上传图片失败'));
        }
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '上传成功成功',
            'data' => 'http://' . $this->getRequest()->server->get('HTTP_HOST') . '/uploads/avatars/' . $filename
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
        $sex = trim($this->getRequest()->get("sex"));
        $birthday = trim($this->getRequest()->get("birthday"));
        $weight = trim($this->getRequest()->get("weight"));
        $height = trim($this->getRequest()->get("height"));
        $goal = trim($this->getRequest()->get("goal"));
        $step_length = trim($this->getRequest()->get("step_length"));
        
        if(!$name || !$email || !$password || !$birthday || !$weight || !$height || !$goal || !$step_length){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '所有项必填'));
        }
        if($um->findUserByUsername($name)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户名已存在'));
        }
        if($um->findUserByEmail($email)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => 'email已存在'));
        }
        $date = array_filter(explode("-", $birthday));
        if(count($date) != 3){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '生日格式错误'));
        }
        
        $user = $um->createUser();
        $user->setUsername($name);
        $user->setEmail($email);
        $user->setPlainPassword($password);
        $user->setSex($sex);
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
            'created_at' => $log->getCreatedAt()->format("Y-m-d H:i:s")
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
            'step_length' => $user->getStepLength(),
            'avatar' => 'http://' . $this->getRequest()->server->get('HTTP_HOST') . '/uploads/avatars/' . $user->getAvatar(),
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
