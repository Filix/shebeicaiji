<?php

namespace Filix\CaijiBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Filix\CaijiBundle\Entity\Log;
use Filix\CaijiBundle\Entity\User;
use Filix\CaijiBundle\Util\DateTimeUtil;

class ApiController extends Controller
{

    const ERROR_CODE = 500;
    const SUCCESS_CODE = 200;

     /**
     * 登录
     * @Route("/t", name="t")
     * @Method({"GET"})
     * @ApiDoc(
     *  section="Api Passport",
     *  description="login11",
     *  filters={
     *      {"name"="email", "desc"="email"},
     *      {"name"="password", "desc"="password"}
     * }
     * )
     */
    public function test()
    {
       
        exit();
    }
    
    /**
     * 增加记录
     * @Route("/data/add", name="log_add")
     * @Method({"POST", "GET"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="add data",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="data", "desc"="data"}
     * }
     * )
     */
    public function addAction()
    {
        $token = $this->getRequest()->get("token");
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        $data = $this->getRequest()->get('data');
        if(!trim($data)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => 'data不能为空'));
        }
        $datas = json_decode($data, true);
        $tmp = array();
        foreach ($datas as $d) {
            $hour = $d['time'] - $d['time'] % 3600; //每小时存一条数据，时间取0分0秒
            if(!in_array($hour, $tmp)){
                $tmp[] = $hour;
		$log = $this->getLogRepository()->getLog($user, date('Y-m-d H:i:s', $hour));
                if(!$log){
                    $log = new Log();
                    $log->setUser($user);
                    $datetime = new \DateTime();
                    $log->setCreatedAt($datetime->setTimestamp($hour));
                }else{
		   $log = $log[0];
		}
                $log->setData(json_encode($d['data']));
                $this->getDoctrineManager()->persist($log);
            }
            
        }
        try{
            $this->getDoctrineManager()->flush();
        } catch (Exception $ex) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '添加失败'));
        }
        
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '添加成功',
            'data' => array('statistics' => $this->getLogsBeforeToday($user))
//            'data' => $this->formatLog($log)
        ));
    }

    /**
     * 根据天获取记录
     * @Route("/data/list/day", name="log_list_day")
     * @Method({"POST", "GET"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="data list",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="begin_day", "desc"="begin day", "type"="string, 2014-05-10"},
     *      {"name"="end_day", "desc"="end day", "type"="string, 2014-05-15"},
     *      {"name"="zone", "desc"="timezone, GMT+8"},
     * }
     * )
     */
    public function listByDayAction()
    {
        $token = $this->getRequest()->get("token");
        $begin_day = $this->getRequest()->get("begin_day");
        $end_day = $this->getRequest()->get("end_day");
        $zone = $this->getRequest()->get("zone", "GMT+8");
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        if(!preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $begin_day) || !preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $end_day)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时间格式错误'));
        }
        $zone = strtoupper($zone);
        if(!preg_match('/^GMT[+-]\d{1,2}$/', $zone)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时区格式错误: GMT+8'));
        }

        $timezone = new \DateTimeZone($zone);
        $begin_day = $begin_day . ' 00:00:00';
        $gmt8 = DateTimeUtil::toMGT8($begin_day, $timezone);
        $begin_day = $gmt8->format("Y-m-d H:i:s");
        $end_day = $end_day . ' 23:59:59';
        $gmt8 = DateTimeUtil::toMGT8($end_day, $timezone);
        $end_day = $gmt8->format("Y-m-d H:i:s");

        $logs = $this->getLogRepository()->getUserLogs($user, $begin_day , $end_day);
        $t = array();
        foreach ($logs as $log) {
            $t[] = array('time' => $log->getCreatedAt()->getTimestamp(),
                'data' => $this->formatLog($log));
        }
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '获取成功',
            'data' => $t,
        ));
    }
    
    /**
     * 根据week获取记录
     * @Route("/data/list/week", name="log_list_week")
     * @Method({"POST", "GET"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="data list",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="begin_day", "desc"="begin day", "type"="string, 2014-05-10"},
     *      {"name"="end_day", "desc"="end day", "type"="string, 2014-05-15"},
     *      {"name"="zone", "desc"="timezone, GMT+8"}
     * }
     * )
     */
    public function listByWeekAction()
    {
        $token = $this->getRequest()->get("token");
        $begin_day = $this->getRequest()->get("begin_day");
        $end_day = $this->getRequest()->get("end_day");
        $zone = $this->getRequest()->get("zone", "GMT+8");
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        if(!preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $begin_day) || !preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $end_day)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时间格式错误: 2015-01-01'));
        }
        $zone = strtoupper($zone);
        if(!preg_match('/^GMT[+-]\d{1,2}$/', $zone)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时区格式错误: GMT+8'));
        }

        $timezone = new \DateTimeZone($zone);
        $begin_day = $begin_day . ' 00:00:00';
        $gmt8 = DateTimeUtil::toMGT8($begin_day, $timezone);
        $begin_day = $gmt8->format("Y-m-d H:i:s");
        $end_day = $end_day . ' 23:59:59';
        $gmt8 = DateTimeUtil::toMGT8($end_day, $timezone);
        $end_day = $gmt8->format("Y-m-d H:i:s");
        
        $logs = $this->getLogRepository()->getUserLogs($user, $begin_day, $end_day);
        $tmp = array();
        foreach ($logs as $log) {
            $w = $log->getCreatedAt()->setTimezone($timezone)->format('Y-m-d');
            $d = $this->formatLog($log);
            if(!isset($tmp[$w])){
                $tmp[$w] = $d;
            }else{
                foreach($tmp[$w] as $key => &$val){
                   if(is_numeric($val)){
                       $val += $d[$key];
                   }
                }
            }
        }
        $t = array();
        $d = new \DateTime();
        $d->setTimezone($timezone);
        foreach($tmp as $key => $v){
            $arr = explode('-', $key);
            $d->setDate($arr[0], $arr[1], $arr[2]);
            $d->setTime(0, 0, 0);
            $time = $d->getTimestamp();
            $w = $d->format('Y-W');
            if(!isset($t[$w])){
                $t[$w]['time'] = $d->setTimestamp($time - ($d->format('N') - 1) * 24 * 3600)->format('Y-m-d');
            }
            $t[$w]['data'][] = array('time' => $key, 'data' => $v);
        }
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '获取成功',
            'data' => array_values($t),
        ));
    }

    /**
     * 根据month获取记录
     * @Route("/data/list/month", name="log_list_month")
     * @Method({"POST", "GET"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="data list",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="begin_month", "desc"="begin month", "type"="string, 2014-05"},
     *      {"name"="end_month", "desc"="end month", "type"="string, 2014-12"},
     *      {"name"="zone", "desc"="timezone, GMT+8"}
     * }
     * )
     */
    public function listByMonthAction()
    {
        $token = $this->getRequest()->get("token");
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        $begin_month = $this->getRequest()->get("begin_month");
        $end_month = $this->getRequest()->get("end_month");
        if(!preg_match('/^\d{4}-\d{1,2}$/', $begin_month) || !preg_match('/^\d{4}-\d{1,2}$/', $end_month)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时间格式错误'));
        }
        $zone = $this->getRequest()->get("zone", "GMT+8");
        $zone = strtoupper($zone);
        if(!preg_match('/^GMT[+-]\d{1,2}$/', $zone)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时区格式错误: GMT+8'));
        }
        
        $first_day = $begin_month . '-01 00:00:00';
        $timezone = new \DateTimeZone($zone);
        $gmt8 = DateTimeUtil::toMGT8($first_day, $timezone);
        $first_day = $gmt8->format("Y-m-d H:i:s");
        list($y, $m) = explode('-', $end_month);
        if($m == 12){
            $y++;
            $m = 1;
        }else{
            $m++;
        }
        $last_day = $y.'-'.$m.'-01 00:00:00';
        $gmt8 = DateTimeUtil::toMGT8($last_day, $timezone);
        $last_day = $gmt8->format("Y-m-d H:i:s");
        $last_day = date('Y-m-d H:i:s', strtotime($last_day) - 1);
        $logs = $this->getLogRepository()->getUserLogs($user, $first_day, $last_day);
        $tmp = array();
        foreach ($logs as $log) {
            $w = $log->getCreatedAt()->setTimezone($timezone)->format('Y-m-d');
            $d = $this->formatLog($log);
            if(!isset($tmp[$w])){
                $tmp[$w] = $d;
            }else{
                foreach($tmp[$w] as $key => &$val){
                   if(is_numeric($val)){
                       $val += $d[$key];
                   }
                }
            }
        }
        foreach($tmp as $key => $v){
            $arr = explode('-', $key);
            $w = $arr[0] . '-' . $arr[1];
            if(!isset($t[$w])){
                $t[$w]['time'] = $w;
            }
            $t[$w]['data'][] = array('time' => $key, 'data' => $v);
        }
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '获取成功',
            'data' => array_values($t),
        ));
    }
    
    /**
     * 根据year获取记录
     * @Route("/data/list/year", name="log_list_year")
     * @Method({"POST", "GET"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="data list",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="begin_year", "desc"="begin year", "type"="string, 2010"},
     *      {"name"="end_year", "desc"="end year", "type"="string, 2014"},
     *      {"name"="zone", "desc"="timezone, GMT+8"}
     * }
     * )
     */
    public function listByYearAction()
    {
        $token = $this->getRequest()->get("token");
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        $begin_year = $this->getRequest()->get("begin_year");
        $end_year = $this->getRequest()->get("end_year");
        if(!preg_match('/^\d{4}$/', $begin_year) || !preg_match('/^\d{4}$/', $end_year)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时间格式错误'));
        }
        $zone = $this->getRequest()->get("zone", "GMT+8");
        $zone = strtoupper($zone);
        if(!preg_match('/^GMT[+-]\d{1,2}$/', $zone)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时区格式错误: GMT+8'));
        }
        $timezone = new \DateTimeZone($zone);
        $first_day = $begin_year . '-01-01 00:00:00';
        $gmt8 = DateTimeUtil::toMGT8($first_day, $timezone);
        $first_day = $gmt8->format("Y-m-d H:i:s");
        $last_day = $end_year.'-12-31 23:59:59';
        $gmt8 = DateTimeUtil::toMGT8($last_day, $timezone);
        $last_day = $gmt8->format("Y-m-d H:i:s");
        
        $logs = $this->getLogRepository()->getUserLogs($user, $first_day, $last_day);
        $tmp = array();
        foreach ($logs as $log) {
            $w = $log->getCreatedAt()->setTimezone($timezone)->format('Y-m');
            $d = $this->formatLog($log);
            if(!isset($tmp[$w])){
                $tmp[$w] = $d;
            }else{
                foreach($tmp[$w] as $key => &$val){
                   if(is_numeric($val)){
                       $val += $d[$key];
                   }
                }
            }
        }
        $t = array();
        foreach($tmp as $key => $v){
            $time = strtotime($key . '-01 00:00:00');
            $arr = explode('-', $key);
            $w = $arr[0];
            if(!isset($t[$w])){
                $t[$w]['time'] = $w;
            }
            $t[$w]['data'][] = array('time' => $key, 'data' => $v);
        }
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '获取成功',
            'data' => array_values($t),
        ));
    }
    
    /**
     * 更新用户信息
     * @Route("/user/profile", name="user_profile")
     * @Method({"POST"})
     * @ApiDoc(
     *  section="Api User",
     *  description="user info",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="name", "desc"="name", "type"="string"},
     *      {"name"="avatar", "desc"="avatar image", "type"="file", "required"="false"},
     *      {"name"="sex", "desc"="sex", "type"="int, 1male 2female"},
     *      {"name"="birthday", "desc"="birthday", "type"="string, 1988-01-20"},
     *      {"name"="weight", "desc"="weight", "type"="double, 120.0"},
     *      {"name"="height", "desc"="height", "type"="double, 180.0"},
     *      {"name"="goal", "desc"="goal", "type"="int"},
     *      {"name"="step_length", "desc"="step_length", "type"="double, 0.5"}
     * }
     * )
     */
    public function profileAction()
    {
        $token = $this->getRequest()->get("token");
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        $name = trim($this->getRequest()->get("name"));
        $sex = trim($this->getRequest()->get("sex"));
        $birthday = trim($this->getRequest()->get("birthday"));
        $weight = trim($this->getRequest()->get("weight"));
        $height = trim($this->getRequest()->get("height"));
        $goal = trim($this->getRequest()->get("goal"));
        $step_length = trim($this->getRequest()->get("step_length"));
        $avatar = $this->getRequest()->files->get('avatar');
        $date = array_filter(explode("-", $birthday));
        if(count($date) != 3){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '生日格式错误'));
        }
        if($avatar != null){
            $localpath = $this->container->getParameter('image_upload_dir').'avatars/';
            $filename = md5($user->getId() . time() . rand(10000, 99999)) . 
                    '.' . strtolower(trim(substr(strrchr($avatar->getClientOriginalName(), '.'), 1, 10)));
            try{
                $fs = new \Symfony\Component\Filesystem\Filesystem();
                $fs->mkdir($localpath);
                $avatar->move($localpath, $filename);
                $user->setAvatar($filename);
            }catch(Exception $e){
                return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '上传图片失败'));
            }
        }
        $user->setNickname($name);
        $user->setSex($sex);
        $datetime = new \DateTime();
        $user->setBirthday($datetime->setDate($date[0], $date[1], $date[2]));
        $user->setWeight($weight);
        $user->setHeight($height);
        $user->setGoal($goal);
        $user->setStepLength($step_length);
        try{
            $dm = $this->getDoctrineManager();
            $dm->persist($user);
            $dm->flush();
        }catch(Exception $e){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '更新数据失败'));
        }
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '更新数据成功',
            'data' => $this->formatUser($user)
        ));
    }
    
    /**
     * 更新用户头像
     * @Route("/user/avatar", name="user_avatar")
     * @Method({"POST"})
     * @ApiDoc(
     *  section="Api User",
     *  description="user info",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="avatar", "desc"="avatar image", "type"="file"}
     *  }
     * )
     */
    public function avatarAction()
    {
        $token = $this->getRequest()->get("token");
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        $avatar = $this->getRequest()->files->get('avatar');
        if(!$avatar instanceof \Symfony\Component\HttpFoundation\File\UploadedFile){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '请选择图片'));
        }
        $localpath = $this->container->getParameter('image_upload_dir').'avatars/';
        $filename = md5($user->getId() . time() . rand(10000, 99999)) . 
                    '.' . strtolower(trim(substr(strrchr($avatar->getClientOriginalName(), '.'), 1, 10)));
        try{
            $fs = new \Symfony\Component\Filesystem\Filesystem();
            $fs->mkdir($localpath);
            $avatar->move($localpath, $filename);
            $user->setAvatar($filename);
        }catch(Exception $e){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '上传图片失败'));
        }
        
        try{
            $dm = $this->getDoctrineManager();
            $dm->persist($user);
            $dm->flush();
        }catch(Exception $e){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '更新数据失败'));
        }
        return new JsonResponse(array(
            'code' => self::SUCCESS_CODE,
            'msg' => '更新数据成功',
            'data' => $this->formatUser($user)
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
     *      {"name"="email", "desc"="email", "type"="string"},
     *      {"name"="password", "desc"="password", "type"="string"}
     * }
     * )
     */
    public function registerAction(){
        $um = $this->get('fos_user.user_manager');
        $name = trim($this->getRequest()->get("name"));
        $email = trim($this->getRequest()->get("email"));
        $password = trim($this->getRequest()->get("password"));
        
        if(!$email || !$password){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '所有项必填'));
        }

        if($um->findUserByEmail($email)){
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => 'email已存在'));
        }
      
        $user = $um->createUser();
        $user->setEmail($email);
        $user->setPlainPassword($password);
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
                'data' => array_merge(
                            $this->formatUser($user), 
                            array('statistics' => $this->getLogsBeforeToday($user))
                        ),
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
    
    protected function getLogsBeforeToday(User $user){
        $logs = $this->getLogRepository()->getLogsBeforeToday($user);
        $s = array('steps' => 0, 'distance' => 0, 'calorie' => 0);
        if($logs){
            foreach($logs as $log){
                $t = json_decode($log->getData(), true);
                $s['steps'] += $t['steps'];
                $s['distance'] += $t['distance'];
                $s['calorie'] += $t['calorie'];
            }
        }
        return $s;
    }


    protected function formatLog(Log $log)
    {
        return json_decode($log->getData(), true);
    }
    
    protected function formatUser(User $user)
    {
        return array(
            'id' => $user->getId(),
            'username' => $user->getNickname(),
            'email' => $user->getEmail(),
            'sex' => $user->getSex(),
            'birthday' => $user->getBirthday() ? $user->getBirthday()->format('Y-m-d') : '',
            'weight' => $user->getWeight(),
            'height' => $user->getHeight(),
            'goal' => $user->getGoal(),
            'token' => $user->getToken(),
            'step_length' => $user->getStepLength(),
            'avatar' => $user->getAvatar() ? 'http://' . $this->getRequest()->server->get('HTTP_HOST') . '/uploads/avatars/' . $user->getAvatar() : '',
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
