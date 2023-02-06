<?php

namespace GW\Service;

use GatewayWorker\Lib\Gateway;
use GW\Utils\Cache;
use GW\Utils\Debug;
use GW\Utils\Redis;


class NNEventsService
{

    // 生成 房间id hash的方法
    public static function roomHash($roomName, $timestamp, $rand)
    {
        return hash("md5", sprintf('%s,%s,%s', $roomName, $timestamp, $rand));
    }


    public static function getRoomeCacheKey($roomId)
    {
        return Cache::key('room', $roomId);
    }


    public static function delRoomData($roomId)
    {
        Redis::go()->del(self::getRoomeCacheKey($roomId));
    }

    public static function setRoomData($roomId, $data)
    {
        $redis    = Redis::go();
        $cacheKey = self::getRoomeCacheKey($roomId);
        $redis->set($cacheKey, $data);
        $redis->expire($cacheKey, 600);
    }

    public static function getRoomData($roomId)
    {
        return Redis::go()->get(self::getRoomeCacheKey($roomId));
    }


    public static function getReadyCacheKey($roomId, $round)
    {
        return Cache::key('roomReady', $roomId, $round);
    }


    public static function getRoomReadyCount($roomId, $round)
    {
        $key = self::getReadyCacheKey($roomId, $round);
        return count(Redis::go()->sMembers($key));
    }


    /**
     * 检查房间的用户是否都已经准备
     *
     * @param $roomId 房间id
     * @param $round  游戏局数
     */
    public static function checkRoomUserReady($roomId, $round)
    {
        Debug::info($roomId, $round, '检查准备状态');
        $clientCount = Gateway::getClientIdCountByGroup($roomId);
        $readyCount  = self::getRoomReadyCount($roomId, $round);
        return $clientCount == $readyCount;
    }


    /**
     *
     * 获取房间内所有成员的当局准备状态
     *
     * @param $roomId
     * @param $round
     *
     * @return array
     */
    public static function getRoomAllClientStatus($roomId, $round)
    {
        // 获取房间的所有client
        $client = Gateway::getClientIdListByGroup($roomId);

        if (!$client) {
            return [];
        }

        $res = [];

        foreach ($client as $item) {
            $res[$item] = self::getRoomClientStatus($roomId, $round, $item);
        }

        return $res;

    }

    public static function getRoomClientStatus($roomId, $round, $clientId)
    {
        $key = self::getReadyCacheKey($roomId, $round);
        return Redis::go()->sIsMember($key, $clientId);
    }

    /**
     * 标记某个用户准备完毕 这里用set
     *
     * @param $roomId
     * @param $round
     * @param $clientId
     */
    public static function setReady($roomId, $round, $clientId)
    {
        $key   = self::getReadyCacheKey($roomId, $round);
        $redis = Redis::go();
        $redis->sAdd($key, $clientId);
        $redis->expire($key, 600);
    }


    public static function getRoomRoundCacheKey($roomId)
    {
        return Cache::key("roomeRound", $roomId);
    }

    // 房间round维护

    public static function incrRoomRound($roomId)
    {
        Debug::info("come in", $roomId);
        $redis = Redis::go();
        $key   = self::getRoomRoundCacheKey($roomId);
        $redis->incr($key);
        $redis->expire($key, 600);
    }

    public static function getRoomRound($roomId)
    {
        $round = Redis::go()->get(self::getRoomRoundCacheKey($roomId));
        Debug::info('getRoomRound', $round, self::getRoomRoundCacheKey($roomId));
        if (!$round) {
            return 1;
        }
        return (int)$round;
    }

    public static function getClientName($roomId, $clientId)
    {
        $session = Gateway::getClientSessionsByGroup($roomId);
        return $session[$clientId]['clientName'] ?? '';
    }


    /**
     *
     * 构建用户加入成功需要反馈给客户端的全部数据
     *
     * @param $roomId
     * @param $clientId
     *
     * @return array
     */
    public static function buildJoinSuccFullData($roomId, $clientId)
    {
        $roomInfo     = NNEventsService::getRoomData($roomId);
        $clientKvList = Gateway::getClientSessionsByGroup($roomId);

        $clientList = [];
        if ($clientKvList) {
            foreach ($clientKvList as $key => $item) {
                $item['clientId'] = $key;
                $clientList[]     = $item;
            }
        }
        $roomInfo['round'] = NNEventsService::getRoomRound($roomId);

        $fullData = [
            'clientId'          => $clientId,
            'clientName'        => $_SESSION['clientName'],
            'roomInfo'          => $roomInfo,
            'roomJoiner'        => $clientList,
            'joinerRoundStatus' => NNEventsService::getRoomAllClientStatus($roomId, NNEventsService::getRoomRound($roomId)), //  当前局的用户状态
            // 这里不考虑庄家的牌，自己的牌，因为clientId变化了 每次新进来，就是一个新人，这里没有用户的概念
        ];
        return $fullData;
    }


}