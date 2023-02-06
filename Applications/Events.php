<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

/**
 * 聊天主逻辑
 * 主要是处理 onMessage onClose
 */

use \GatewayWorker\Lib\Gateway;
use GW\Service\PokerService;
use GW\Utils\Response;
use GW\Config\WsResTypeConfig;
use GW\Config\NNEventsConfig;
use GW\Service\NNEventsService;

class Events
{


    /**
     * 有消息时
     *
     * @param int   $client_id
     * @param mixed $message
     */
    public static function onMessage($clientId, $message)
    {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$clientId session:" . json_encode($_SESSION) . " onMessage:" . $message . "\n";

        // 客户端传递的是json数据
        $messageData = json_decode($message, true);
        if (!$messageData) {
            return;
        }

        // 根据类型执行不同的业务
        switch ($messageData['type']) {
            // 客户端回应服务端的心跳
            case 'pong':
                return;
            // 客户端登录 message格式: {type:login, name:xx, room_id:1} ，添加到客户端，广播给所有客户端xx进入聊天室
            case 'login':
                if (!isset($messageData['clientName'])) {
                    return;
                }
                $username               = $messageData['clientName'] ?? NNEventsConfig::ROOM_USERNAME_DEF . '-' . rand(10, 99);
                $_SESSION['clientName'] = $username;
                return Gateway::sendToCurrentClient(Response::succ(['clientId' => $clientId], 'succ ok ~', WsResTypeConfig::LOGIN_SUCC));
            case 'roomCreate':
                /*
                   1 把房间信息存进 redis list中，包括房间名称，密码，做庄类型，房间的创建者名称，
                     clientId，庄家名称 ，创建时候的时间戳，和散列值，反馈给用户成功后，用户跳转页面
                     页面把hash值带进去，并且把hash值缓存到localstorage
                   2 这个地方可能还存在用户多次创建房间的问题，用户退出后client_id会触发on_close
                     所以当房间的所有client_id离线后，房间会自动释放，不会出现资源越积累越多的问题
                   3 这个地方需要解决房间的唯一性问题，对于用户来讲，他只在乎自己创建的房间的名称
                     但是我们不能让房间重复，需要增加其他的元素进去，比如时间戳，随机数，然后通过这
                     些求一个hash值，把这个散列值反馈给用户，存储到房间信息中，作为房间的id，用户
                     每次的动作都要带上这个散列值，因为一个用户可以在多个房间中
                */

                // TODO  这个地方用对象维护更合适，数组的随意性过大，容易出错

                $roomInfo['roomName']       = $messageData['roomName'] ?? NNEventsConfig::ROOMNAME_DEF;
                $roomInfo['roomPass']       = $messageData['roomPass'] ?? NNEventsConfig::ROOMPASS_DEF;
                $roomInfo['dealerMode']     = $messageData['dealerMode'] ?? NNEventsConfig::DEALER_MODE_FIXED; // 默认轮庄
                $roomInfo['dealerName']     = $_SESSION['clientName'];
                $roomInfo['dealerClientId'] = $clientId;
                $roomInfo['time']           = time();
                $roomInfo['rand']           = rand(100000, 999999);
                $roomInfo['status']         = NNEventsConfig::ROOM_STATUS_READY;
                $roomInfo['rounds']         = 1;
                $roomInfo['lastRoundTime']  = 0;
                // 这里基本上不会有并发问题
                $roomId             = NNEventsService::roomHash($roomInfo['roomName'], $roomInfo['time'], $roomInfo['rand']);
                $roomInfo['roomId'] = $roomId;
                // TODO 这里就不维护过期时间了，回头定时任务脚本异步处理
                NNEventsService::setRoomData($roomId, $roomInfo);
                // 初始化房间的round
                NNEventsService::incrRoomRound($roomId);
                // TODO 这个地方前端必须单页，否则的话，clientId会变化，创建好的房间没了
                // 每个用户我们就认为他只会存在一个房间，因为无法多开
                $_SESSION['roomId'] = $roomId;
                // 加入到房间里面，并且创建房间
                Gateway::joinGroup($clientId, $roomId);

                Gateway::sendToCurrentClient(Response::succ(['roomId' => $roomId], '房间创建成功', WsResTypeConfig::ROOM_CREATE_SUCC));


                $simpleData = [
                    'clientId'   => $clientId,
                    'clientName' => $_SESSION['clientName'],
                ];


                $fullData = NNEventsService::buildJoinSuccFullData($roomId, $clientId);

                // 全部信息只放给刚进入房间的朋友
                Gateway::sendToCurrentClient(Response::succ($fullData, '房间信息更新', WsResTypeConfig::ROOM_DATA_INIT));
                // 简要的通知信息广播给每一位 自己除外
                return Gateway::sendToGroup($roomId, Response::succ($simpleData, '加入房间通知', WsResTypeConfig::ROOM_JOIN_SUCC_INFORM), $clientId);

            case 'roomJoin':
                /*
                    1 直接把client_id 加入对应的hash的房间，不用关注用户的姓名，因为其他的各种操作都通过client_id区分
                */

                if (!isset($messageData['roomId']) || !$messageData['roomId']) {
                    return Gateway::sendToCurrentClient(Response::err([], 'can not find the roomId in params !', WsResTypeConfig::ERROR));
                }

                $roomId = $messageData['roomId'];
                // 判断房间是否存在，否则可能通过过去的链接把房间给创建起来了
                $roomInfo = NNEventsService::getRoomData($roomId);
                if (!$roomInfo) {
                    return Gateway::sendToCurrentClient(Response::err([], 'room not exsit !', WsResTypeConfig::ERROR));
                }

                if (Gateway::getClientCountByGroup($roomId) > 10) {
                    return Gateway::sendToCurrentClient(Response::err([], '房间人数已满，加入失败 !', WsResTypeConfig::ERROR));
                }

                Gateway::joinGroup($clientId, $messageData['roomId']);
                // 这里需要发送所有的房间数据
                $simpleData = [
                    'clientId'   => $clientId,
                    'clientName' => $_SESSION['clientName'],
                ];
                // 把session Id 缓存进去 方便退出的时候 通知群内人员
                $_SESSION['roomId'] = $roomId;

                $fullData = NNEventsService::buildJoinSuccFullData($roomId, $clientId);
                // 全部信息只放给刚进入房间的朋友
                Gateway::sendToCurrentClient(Response::succ($fullData, '房间信息更新', WsResTypeConfig::ROOM_DATA_INIT));
                // 简要的通知信息广播给每一位 自己除外
                return Gateway::sendToGroup($roomId, Response::succ($simpleData, '加入房间通知', WsResTypeConfig::ROOM_JOIN_SUCC_INFORM), $clientId);

            case 'ready':
                /*
                1 不管是用户还是庄家，都需要做准备动作，当判断到房间内所有人都准备后，发送客户端，房间
                  状态变成 1 ，游戏的round+1(实际上是推送依次房间信息)，也就是游戏进行状态，这里就不细分发牌，结算，因为结算在线下，无法控制
                  然后进入发牌逻辑
                2 准备的边界不是房间，而是定位到某一局游戏，这个状态怎么存储？直接房间hash + round,存一个
                  set,全部准备根据count来,到时候用户准备的时候，通过广播到客户端已经准备。
                3 看是一次性推过去所有数据，还是只推送单个人的准备数据，因为准备是比较频繁的操作，全部
                  数据量要比单个的数据大房间人数倍，比如进入页面的时候拉全部，广播的时候只给单条*/


                $roomId = $messageData['roomId'];

                if (!$roomId) {
                    return Gateway::sendToCurrentClient(Response::err([], '找不到房间id', WsResTypeConfig::ERROR));
                }

                // 检查clientId是否在房间内
                if (!in_array($clientId, Gateway::getClientIdListByGroup($roomId))) {
                    return Gateway::sendToCurrentClient(Response::err([], '您当前不在此房间内，请刷新', WsResTypeConfig::ERROR));
                }

                $roomInfo = NNEventsService::getRoomData($roomId);

                // 检查庄家是否还在线
                $dealerClientId = $roomInfo['dealerClientId'] ?? 0;

                if ($dealerClientId) {

                    if (!Gateway::isOnline($dealerClientId)) {
                        return Gateway::sendToCurrentClient(Response::err([], '庄家已经离开，无法继续游戏', WsResTypeConfig::ERROR));
                    }
                }


                if (time() - $roomInfo['lastRoundTime'] < 10) {
                    return Gateway::sendToCurrentClient(Response::err([], '您这也太快了，不好吧~', WsResTypeConfig::ERROR));
                }


                if ($roomInfo['status'] != NNEventsConfig::ROOM_STATUS_READY && $clientId != $roomInfo['dealerClientId']) {
                    return Gateway::sendToCurrentClient(Response::err([], '房间处于结算状态，无法准备', WsResTypeConfig::ERROR));
                }

                // 房间不超过1个人，无法开局
                if (Gateway::getClientCountByGroup($roomId) < 2) {
                    return Gateway::sendToCurrentClient(Response::err([], '房间人数未超过一个人，无法开始发牌', WsResTypeConfig::ERROR));
                }


                $round = NNEventsService::getRoomRound($roomId);

                // 检测新一局的第一个准备
                $readyCount = NNEventsService::getRoomReadyCount($roomId, $round);

                // 这里增加一个限制，第一个准备的必须是庄家的

                // 如果之前没有一个准备的则，这是第一个
                if ($readyCount == 0) {
                    $roomInfo['status'] = NNEventsConfig::ROOM_STATUS_READY;
                    // 这个地方应该是更换庄家信息的最好时机，实际上是fullReady发完牌之后，改通知的通知完，在把庄家信息需要不需要换写进redis，只是不做通知而已，分两次写入
                    // 重置round round实际上也是从fullready就完成了
                    // 需要更新房间状态 更新局数信息
                    Gateway::sendToGroup($roomId, Response::succ($roomInfo, '房间信息更新', WsResTypeConfig::ROOM_INFO_CHANGE));
                    // 所有人的状态重置
                    Gateway::sendToGroup($roomId, Response::succ(NNEventsService::getRoomAllClientStatus($roomId, $round), '玩家状态全部重置', WsResTypeConfig::CLIENT_STATUS_RESET));

                    NNEventsService::setRoomData($roomId, $roomInfo);
                }


                NNEventsService::setReady($roomId, $round, $clientId);

                // 广播某个人准备 状态
                Gateway::sendToGroup($roomId, Response::succ([
                    'clientId'   => $clientId,
                    'clientName' => $_SESSION['clientName'],
                ], '用户准备', WsResTypeConfig::CLIENT_READIED));

                // 判断是不是已经全部准备完毕
                $fullReady = NNEventsService::checkRoomUserReady($roomId, $round);


                if ($fullReady) {
                    // 局数迭代
                    NNEventsService::incrRoomRound($roomId);
                    // 修改房间状态
                    $roomInfo['status'] = NNEventsConfig::ROOM_STATUS_ING;

                    // 这里房间信息只刷新状态，其他的等第二局准备再开始刷新
                    Gateway::sendToGroup($roomId, Response::succ($roomInfo, '房间信息更新', WsResTypeConfig::ROOM_INFO_CHANGE));

                    // 发牌操作
                    $clientIdList = Gateway::getClientIdListByGroup($roomId);
                    $poker        = PokerService::deal($clientIdList);

                    Gateway::sendToGroup($roomId, Response::succ(['pai' => $poker], '发牌', WsResTypeConfig::ALL_JOINER_PAI_DATA));

                    /*
                                        // 广播庄家的牌
                                        $dealerClientId = $roomInfo['dealerClientId'];

                                        $dealerPai = $poker[$dealerClientId];
                                        Gateway::sendToGroup($roomId, Response::succ(['pai' => $dealerPai], '庄家牌', WsResTypeConfig::DEALER_PAI_DATA));

                                        // 给每个玩家发牌
                                        foreach ($poker as $clientId => $paiData) {
                                            // 缓存牌的数据 数据初始化要用
                                            Gateway::sendToClient($clientId, Response::succ(['pai' => $paiData], '玩家牌', WsResTypeConfig::JOINER_PAI_DATA));
                                        }*/

                    // TODO 如何处理轮庄问题  发完牌 出一个轮换的通知
                    if ($roomInfo['dealerMode'] == NNEventsConfig::DEALER_MODE_ROUND) {
                        // 检查最大的牛牛是不是当前的庄家
                        $cowCowMax = PokerService::getCowCowMax($poker);
                        if ($cowCowMax && $cowCowMax['clientId'] != $roomInfo['dealerClientId']) {
                            // 更新房间信息
                            $roomInfo['dealerName']     = NNEventsService::getClientName($roomId, $cowCowMax['clientId']);
                            $roomInfo['dealerClientId'] = $cowCowMax['clientId'];
                        }
                    }

                    // 更新局数
                    $roomInfo['rounds'] = NNEventsService::getRoomRound($roomId);
                    // 更新上一局的结束时间
                    $roomInfo['lastRoundTime'] = time();

                    NNEventsService::setRoomData($roomId, $roomInfo);

                }
                break;
            case 'T':
                // T 人
                $roomId   = $messageData['roomId'] ?? 0;
                $targetId = $messageData['targetId'] ?? 0;
                if (!$roomId) {
                    return Gateway::sendToCurrentClient(Response::err([], '找不到房间id', WsResTypeConfig::ERROR));
                }

                if (!$targetId) {
                    return Gateway::sendToCurrentClient(Response::err([], '找不到要请离的目标id', WsResTypeConfig::ERROR));
                }

                $roomInfo = NNEventsService::getRoomData($roomId);

                if ($roomInfo['dealerClientId'] == $targetId) {
                    return Gateway::sendToCurrentClient(Response::err([], '不允许请离庄家', WsResTypeConfig::ERROR));
                }

                $data = ['clientId' => $targetId, 'clientName' => '', 'time' => date('Y-m-d H:i:s')];
                Gateway::sendToGroup($roomId, Response::succ($data, '玩家被请离', WsResTypeConfig::ROOM_LOGOUT));

                Gateway::leaveGroup($targetId, $roomId);
                break;

            default:
                break;

        }
    }

    /**
     * 当客户端断开连接时
     *
     * @param integer $clientId 客户端id
     */
    public static function onClose($clientId)
    {
        // debug
        echo "client:{$_SERVER['REMOTE_ADDR']}:{$_SERVER['REMOTE_PORT']} gateway:{$_SERVER['GATEWAY_ADDR']}:{$_SERVER['GATEWAY_PORT']}  client_id:$clientId onClose:''\n";

        // 从房间的客户端列表中删除
        $roomId = $_SESSION['roomId'] ?? 0;
        $data   = ['clientId' => $clientId, 'clientName' => $_SESSION['clientName'], 'time' => date('Y-m-d H:i:s')];

        $roomInfo = NNEventsService::getRoomData($roomId);

        if ($roomInfo && $roomInfo['dealerClientId'] == $clientId) {
            // 解散房间
            NNEventsService::delRoomData($roomId);
        }

        Gateway::sendToGroup($roomId, Response::succ($data, '玩家退出', WsResTypeConfig::ROOM_LOGOUT));

    }


}
