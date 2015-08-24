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
     * @Method({"POST", "GET"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="add data",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="data", "desc"="data"},
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
        if (!trim($data)) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => 'data不能为空'));
        }
        $datas = json_decode($data, true);
        $tmp = array();
        foreach ($datas as $d) {
            $minute = $d['time'] - $d['time'] % 60; //每分钟存一条数据，时间取0分0秒
            if (!in_array($minute, $tmp)) {
                $tmp[] = $minute;
                $log = $this->getLogRepository()->getLog($user, date('Y-m-d H:i:s', $minute));
                if (!$log) {
                    $log = new Log();
                    $log->setUser($user);
                    $datetime = new \DateTime();
                    $log->setCreatedAt($datetime->setTimestamp($minute));
                } else {
                    $log = $log[0];
                }
                $log->setData(json_encode($d['data']));
                $this->getDoctrineManager()->persist($log);
            }
        }
        try {
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
     * @Method({"POST"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="data list",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="begin_day", "desc"="begin day", "type"="string, 2014-05-10"},
     *      {"name"="end_day", "desc"="end day", "type"="string, 2014-05-15"}
     * }
     * )
     */
    public function listByDayAction()
    {
        $token = $this->getRequest()->get("token");
        $begin_day = $this->getRequest()->get("begin_day");
        $end_day = $this->getRequest()->get("end_day");
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        if (!preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $begin_day) || !preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $end_day)) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时间格式错误'));
        }
        $logs = $this->getLogRepository()->getUserLogs($user, $begin_day . ' 00:00:00', $end_day . ' 23:59:59');
        $t = array();
        $flags = array();
        foreach ($logs as $log) {
            $d = $this->formatLog($log);
            if (in_array('distance', $d) && $d['distance'] > 0) {
                $key = date('Y-m-d', $log->getCreatedAt()->getTimestamp());
                $flags[$key] = $d['distance'];
            }
        }

        foreach ($logs as $log) {
            $t[] = array('time' => $log->getCreatedAt()->getTimestamp(),
//                'data' => $this->formatLog($log),
                'data' => $this->formatLog2($log, $flags)
            );
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
     *      {"name"="end_day", "desc"="end day", "type"="string, 2014-05-15"}
     * }
     * )
     */
    public function listByWeekAction()
    {
        $token = $this->getRequest()->get("token");
        $begin_day = $this->getRequest()->get("begin_day");
        $end_day = $this->getRequest()->get("end_day");
        if (!$user = $this->getUserRepository()->findOneBy(array('token' => $token))) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '用户不存在'));
        }
        if (!preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $begin_day) || !preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $end_day)) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时间格式错误'));
        }
        $logs = $this->getLogRepository()->getUserLogs($user, $begin_day . ' 00:00:00', $end_day . ' 23:59:59');
        $tmp = array();
        $distance_flags = array();
        foreach ($logs as $log) {
            $w = date('Y-m-d', $log->getCreatedAt()->getTimestamp());
            $d = $this->formatLog($log);
            $distance_flags[$w] = $d['distance'];
            if (!isset($tmp[$w])) {
                $tmp[$w] = $d;
            } else {
                foreach ($tmp[$w] as $key => &$val) {
                    if (is_numeric($val) && $key != 'distance') {
                        $val += $d[$key];
                    }
                }
            }
        }

        foreach ($tmp as $key => &$temp) {
            $temp['distance'] = $distance_flags[$key];
        }

        $t = array();
        foreach ($tmp as $key => $v) {
            $time = strtotime($key . ' 00:00:00');
            $w = date('Y-W', $time);
            if (!isset($t[$w])) {
                $k = date('N', $time);
                $t[$w]['time'] = date('Y-m-d', $time - (date('N', $time) - 1) * 24 * 3600);
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
     * @Method({"POST", "get"})
     * @ApiDoc(
     *  section="Api Data",
     *  description="data list",
     *  filters={
     *      {"name"="token", "desc"="user token"},
     *      {"name"="begin_month", "desc"="begin month", "type"="string, 2014-05"},
     *      {"name"="end_month", "desc"="end month", "type"="string, 2014-12"}
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
        if (!preg_match('/^\d{4}-\d{1,2}$/', $begin_month) || !preg_match('/^\d{4}-\d{1,2}$/', $end_month)) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时间格式错误'));
        }
        $first_day = $begin_month . '-01 00:00:00';
        list($y, $m) = explode('-', $end_month);
        if ($m == 12) {
            $y++;
            $m = 1;
        } else {
            $m++;
        }
        $last_day = date('Y-m-d H:i:s', strtotime($y . '-' . $m . '-01 00:00:00') - 1);
        $logs = $this->getLogRepository()->getUserLogs($user, $first_day, $last_day);
        $tmp = array();
        $distance_flag = array();
        foreach ($logs as $log) {
            $w = date('Y-m-d', $log->getCreatedAt()->getTimestamp());
            $d = $this->formatLog($log);
            $distance_flag[$w] = $d['distance'];
            if (!isset($tmp[$w])) {
                $tmp[$w] = $d;
            } else {
                foreach ($tmp[$w] as $key => &$val) {
                    if (is_numeric($val) && $key != "distance") {
                        $val += $d[$key];
                    }
                }
            }
        }
        
        foreach ($tmp as $key => &$temp) {
            $temp['distance'] = $distance_flag[$key];
        }
        
        $t = array();
        foreach ($tmp as $key => $v) {
            $time = strtotime($key . ' 00:00:00');
            $w = date('Y-m', $time);
            if (!isset($t[$w])) {
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
     *      {"name"="end_year", "desc"="end year", "type"="string, 2014"}
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
        if (!preg_match('/^\d{4}$/', $begin_year) || !preg_match('/^\d{4}$/', $end_year)) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '时间格式错误'));
        }
        $first_day = $begin_year . '-01-01 00:00:00';
        $last_day = $end_year . '-12-31 23:59:59';
        $logs = $this->getLogRepository()->getUserLogs($user, $first_day, $last_day);
        $tmp = array();
        $distance_flags = array();
        foreach ($logs as $log) {
            $w = date('Y-m-d', $log->getCreatedAt()->getTimestamp());
            $d = $this->formatLog($log);
            $distance_flags[$w] = $d['distance'];
        }
        $distance_flags_month = array();
        foreach($distance_flags as $k => $v){
            $m = substr($k, 0, 7);
            if(!isset($distance_flags_month[$m])){
                $distance_flags_month[$m] = $v;
            }else{
                $distance_flags_month[$m] += $v;
            }
        }
//        
//        foreach ($logs as $log) {
//            $w = date('Y-m-d', $log->getCreatedAt()->getTimestamp());
//            $d = $this->formatLog($log);
//            $d['distance'] = $distance_flags[$w];
//            $log->setData(json_encode($d));
//        }
//        
        
        foreach ($logs as $log) {
            $w = date('Y-m', $log->getCreatedAt()->getTimestamp());
            $d = $this->formatLog($log);
            $d['distance'] = $distance_flags_month[$w];
            if (!isset($tmp[$w])) {
                $tmp[$w] = $d;
            } else {
                foreach ($tmp[$w] as $key => &$val) {
                    if (is_numeric($val) && $key != "distance") {
                        $val += $d[$key];
                    }
                }
            }
        }
        $t = array();
        foreach ($tmp as $key => $v) {
            $time = strtotime($key . '-01 00:00:00');
            $w = date('Y', $time);
            if (!isset($t[$w])) {
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
        if (count($date) != 3) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '生日格式错误'));
        }
        if ($avatar != null) {
            $localpath = $this->container->getParameter('image_upload_dir') . 'avatars/';
            $filename = md5($user->getId() . time() . rand(10000, 99999)) .
                    '.' . strtolower(trim(substr(strrchr($avatar->getClientOriginalName(), '.'), 1, 10)));
            try {
                $fs = new \Symfony\Component\Filesystem\Filesystem();
                $fs->mkdir($localpath);
                $avatar->move($localpath, $filename);
                $user->setAvatar($filename);
            } catch (Exception $e) {
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
        try {
            $dm = $this->getDoctrineManager();
            $dm->persist($user);
            $dm->flush();
        } catch (Exception $e) {
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
        if (!$avatar instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '请选择图片'));
        }
        $localpath = $this->container->getParameter('image_upload_dir') . 'avatars/';
        $filename = md5($user->getId() . time() . rand(10000, 99999)) .
                '.' . strtolower(trim(substr(strrchr($avatar->getClientOriginalName(), '.'), 1, 10)));
        try {
            $fs = new \Symfony\Component\Filesystem\Filesystem();
            $fs->mkdir($localpath);
            $avatar->move($localpath, $filename);
            $user->setAvatar($filename);
        } catch (Exception $e) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '上传图片失败'));
        }

        try {
            $dm = $this->getDoctrineManager();
            $dm->persist($user);
            $dm->flush();
        } catch (Exception $e) {
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
    public function registerAction()
    {
        $um = $this->get('fos_user.user_manager');
        $name = trim($this->getRequest()->get("name"));
        $email = trim($this->getRequest()->get("email"));
        $password = trim($this->getRequest()->get("password"));

        if (!$email || !$password) {
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '所有项必填'));
        }

        if ($um->findUserByEmail($email)) {
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
        if (is_null($user))
            return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '账号或密码错误'));

        if ($this->checkPassword($user, $password)) {
            $response = new JsonResponse(array(
                'code' => self::SUCCESS_CODE,
                'msg' => '登录成功',
                'data' => array_merge(
                        $this->formatUser($user), array('statistics' => $this->getLogsBeforeToday($user))
                ),
            ));
            $this->login($user, $response);

            return $response;
        }

        return new JsonResponse(array('code' => self::ERROR_CODE, 'msg' => '账号或密码错误'));
    }

    private function login($user, $response = null)
    {
        $this->get('fos_user.security.login_manager')
                ->loginUser(
                        $this->container->getParameter('fos_user.firewall_name'), $user, $response
        );
    }

    private function checkPassword($user, $password)
    {
        return $user->getPassword() === $this->container
                        ->get('security.encoder_factory')
                        ->getEncoder($user)
                        ->encodePassword($password, $user->getSalt());
    }

    protected function getLogsBeforeToday(User $user)
    {
        $logs = $this->getLogRepository()->getLogsBeforeToday($user);
        $s = array('steps' => 0, 'distance' => 0, 'calorie' => 0);
        if ($logs) {
            foreach ($logs as $log) {
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

    protected function formatLog2(Log $log, Array $flags)
    {
        $day = date('Y-m-d', $log->getCreatedAt()->getTimestamp());
        $d = json_decode($log->getData(), true);
        if (in_array($day, $flags)) {
            $d['distance'] = $flags[$day];
        }
        return $d;
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
