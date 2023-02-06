<?php

namespace GW\Service;

use GW\Utils\Cache;
use GW\Utils\Redis;

class PokerService
{

    const POKER = [
        // 黑红梅方
        'a1', 'a2', 'a3', 'a4', 'a5', 'a6', 'a7', 'a8', 'a9', 'a10', 'a11', 'a12', 'a13',
        'b1', 'b2', 'b3', 'b4', 'b5', 'b6', 'b7', 'b8', 'b9', 'b10', 'b11', 'b12', 'b13',
        'c1', 'c2', 'c3', 'c4', 'c5', 'c6', 'c7', 'c8', 'c9', 'c10', 'c11', 'c12', 'c13',
        'd1', 'd2', 'd3', 'd4', 'd5', 'd6', 'd7', 'd8', 'd9', 'd10', 'd11', 'd12', 'd13',
    ];


    /**
     *
     * poker 存储key
     *
     * @param $clientId
     * @param $roomId
     * @param $round
     *
     * @return string
     */
    private static function getPokerCacheKey($clientId, $roomId, $round)
    {
        return Cache::key($clientId, $roomId, $round);
    }

    /**
     * 设置牌数据
     *
     * @param $clientId
     * @param $roomId
     * @param $round
     * @param $pokerData
     *
     * @return void
     * @throws \RedisException
     */
    // 这个地方保险起见还是吧roomid带上
    public static function setPokerData($clientId, $roomId, $round, $pokerData)
    {
        Redis::go()->set(self::getPokerCacheKey($clientId, $roomId, $round), $pokerData);
    }


    /**
     * 获取牌数据
     *
     * @param $clientId
     * @param $roomId
     * @param $round
     *
     * @return false|mixed|\Redis|string
     * @throws \RedisException
     */
    public static function getPokerData($clientId, $roomId, $round)
    {
        return Redis::go()->get(self::getPokerCacheKey($clientId, $roomId, $round));
    }

    /**
     * 发牌 实际上这里不会出现两副牌的情况
     *
     * @param $userClientIdList
     *
     * @return array
     */
    public static function deal($userClientIdList)
    {

        $poker    = [];
        $pokerRes = [];
        foreach ($userClientIdList as $clientId) {
            for ($i = 0; $i <= 4; $i++) {
                if (!$poker) {
                    $poker = self::POKER;
                    shuffle($poker);
                }

                $pokerRes[$clientId][] = array_pop($poker);
            }
        }
        return $pokerRes;
    }

    /**
     *
     *获取最大的一张牌  本方法由辉哥倾情贡献
     *
     * @param $originData
     * @param $maxPaiNum
     *
     * @return false|string
     */
    public static function maxPaiStr($originPaiList, $maxPaiNum)
    {
        if (!$originPaiList) {
            return false;
        }
        // 获取最大的一张牌 因为牌不会重复，这么做是很好比较大小
        $config = ['a', 'b', 'c', 'd'];
        foreach ($config as $decor) {
            $checkStr = $decor . $maxPaiNum;
            if (in_array($checkStr, $originPaiList)) {
                return $checkStr;
            }
        }
        return false;
    }

    /**
     * 排列组合
     *
     * @param       $arr
     * @param       $i
     * @param       $res
     * @param       $num
     * @param array $finalRes
     */
    public static function combine($arr, $i, $res, $num, &$finalRes = [])
    {

        if (count($res) == $num) {
            $finalRes[] = $res;
            return;
        }
        if ($i == count($arr)) {
            return;
        }
        $res_  = $res;
        $res[] = $arr[$i];
        self::combine($arr, $i + 1, $res, $num, $finalRes);
        self::combine($arr, $i + 1, $res_, $num, $finalRes);
    }

    /**
     * 计算点数
     *
     * @param $paiNumItem
     *
     * @return int|mixed
     */
    public static function calcuPoint($paiNumItem)
    {
        $sum = 0;
        foreach ($paiNumItem as $pai) {
            if ($pai >= 10) {
                $pai = 10;
            }
            $sum += $pai;
        }
        return $sum;
    }

    /*
     * 二维数组按照字段排序 默认降序
     */
    public static function sort($list, $field, $mode = 0)
    {
        uasort($list, function ($a, $b) use ($field, $mode) {

            $valA = $a[$field];
            $valB = $b[$field];
            if ($valA == $valB) {
                return 0;
            } else {
                // 1 代表升序 0 代表降序
                if ($mode) {
                    return ($valA < $valB) ? -1 : 1;
                } else {
                    return ($valA > $valB) ? -1 : 1;
                }

            }
        });

        return $list;
    }

    /**
     *
     * 是否为5小
     *
     * @param $data
     *
     * @return array|bool|mixed|void
     */
    public static function isCowCow5Min($paiNumListItem)
    {
        $sum = self::calcuPoint($paiNumListItem);
        if ($sum > 10) {
            return false;
        }
        return true;
    }

    /**
     * 是否是炸弹
     *
     * @param $paiNumListItem
     *
     * @return bool
     */
    public static function isCowCowBoom($paiNumListItem)
    {
        return max(array_count_values($paiNumListItem)) === 4;

    }

    /**
     * 是否是5花
     *
     * @param $paiNumListItem
     *
     * @return bool
     */
    public static function isCowCow5Flower($paiNumListItem)
    {
        return min($paiNumListItem) > 10;
    }

    /**
     * 是否是4花
     *
     * @param $paiNumListItem
     *
     * @return bool
     */
    public static function isCowCow4Flower($paiNumListItem)
    {

        if (min($paiNumListItem) != 10) {
            return false;
        }

        $valList = array_count_values($paiNumListItem);

        if ($valList[10] != 1) {
            return false;
        }

        return true;
    }

    /**
     * 是否是普通牛牛
     *
     * @param $paiNumListItem
     *
     * @return bool
     */
    public static function isCowCowNormal($paiNumListItem)
    {
        $combineList = [];
        self::combine($paiNumListItem, 0, [], 3, $combineList);

        foreach ($combineList as $combineItem) {

            // 必须有三张牌加起来等于 10 的倍数
            $sum = self::calcuPoint($paiNumListItem);;
            if ($sum % 10 != 0) {
                continue;
            }
            $diff = array_diff($paiNumListItem, $combineItem);
            $sum  += self::calcuPoint($diff);

            if ($sum % 10 != 0) {
                continue;
            }
            return true;
        }

        return false;
    }

    /**
     * 分析牛牛的类型
     *
     * @param $paiNumListItem
     *
     * @return false|string
     */
    public static function analysisCowCowCate($paiNumListItem)
    {
        if (self::isCowCow5Min($paiNumListItem)) {
            return '5min';
        } elseif (self::isCowCowBoom($paiNumListItem)) {
            return 'boom';
        } elseif (self::isCowCow5Flower($paiNumListItem)) {
            return '5flower';
        } elseif (self::isCowCow4Flower($paiNumListItem)) {
            return '4flower';
        } elseif (self::isCowCowNormal($paiNumListItem)) {
            return 'normal';
        } else {
            return false;
        }
    }

    /**
     * 获取牌数据中最大的牛
     *
     * @param $dataStrList
     *
     * @return array|false|mixed
     */
    public static function getCowCowMax($dataStrList)
    {
        // 字符串化处理
        $keyList   = array_keys($dataStrList);
        $valueList = array_values($dataStrList);
        $valueJson = json_encode($valueList);

        // 把字母去掉 这里只对value进行处理，以免key出现问题
        $stringNoAz = preg_replace("/[a|b|c|d]/", "", $valueJson);

        // 还原回来纯数字的数组
        $dataNumList = array_combine($keyList, json_decode($stringNoAz, true));


        // 再过滤一遍 把假牛干掉
        $finalCowCowList = [];

        foreach ($dataNumList as $index => $item) {

            $temp = $item;

            // 倒序 方便比大小
            arsort($temp);
            $maxPaiNum  = current($temp);
            $paiStrList = $dataStrList[$index] ?? [];

            //先算有没有小小牛 这个是特殊情况 为什么要继续，因为还可能有小小牛 这个地方虽然有重复计算的嫌疑，但是因为概率极小，所以可以忽略

            $finalCowCowItem = ['clientId' => $index, 'paiNumList' => $temp, 'maxPaiNum' => $maxPaiNum, 'maxPaiStr' => self::maxPaiStr($paiStrList, $maxPaiNum), 'paiStrList' => $paiStrList];

            $cate = self::analysisCowCowCate($temp);
            if ($cate === false) {
                continue;
            }
            $finalCowCowItem['cate']  = $cate;
            $finalCowCowList[$cate][] = $finalCowCowItem;
        }

        if (!$finalCowCowList) {
            return [];
        }

        $cateConfig = [
            '5min', 'boom', '5flower', '4flower', 'normal'
        ];


        $res = [];

        foreach ($cateConfig as $cate) {

            $group = $finalCowCowList[$cate] ?? [];


            if (!$group) {
                continue;
            }

            $group = array_values(self::sort($group, 'maxPaiNum'));
            if (count($group) == 1) {
                $res = current($group);
                break;
            }

            $a = $group[0];
            $b = $group[1];

            // 要么a 比 b 大 要么 a = b

            if ($a['maxPaiNum'] > $b['maxPaiNum']) {
                $res = $a;
                break;
            }

            // 比花色
            $res = $a['maxPaiStr'] > $b['maxPaiStr'] ? $b : $a;
            break;
        }

        return $res;

    }

}