<?php

namespace Filix\CaijiBundle\Util;

/**
 * Description of DateTimeUtil
 *
 * @author filix
 */
class DateTimeUtil
{
    const GMT8DateTimeZone = "Asia/Shanghai";


    /*
     * @param $datetime String: 2015-01-01 00:00:00
     * @param $timezone String,\DateTimeZone: GMT+9
     * 
     * @return \DateTime
     */
    public static function toMGT8($datetime_string, $timezone){
        $datetime = new \DateTime();
        if(!$timezone instanceof \DateTimeZone){
            $timezone = new \DateTimeZone($timezone);
        }
        $datetime->setTimezone($timezone);
        list($date, $time) = explode(' ', $datetime_string);
        list($year, $month, $day) = explode('-', $date);
        list($hour, $minute, $second) = explode(':', $time);
        $datetime->setDate($year, $month, $day);
        $datetime->setTime($hour, $minute, $second);
        $gmt8 = new \DateTime();
        $gmt8->setTimezone(new \DateTimeZone(self::GMT8DateTimeZone));
        $gmt8->setTimestamp($datetime->getTimestamp());
        return $gmt8;
    }
    
   
}
