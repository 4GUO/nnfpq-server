<?php

namespace GW\Config;

class WsResTypeConfig
{
    const LOGIN_SUCC = 'loginSucc';

    // 房间创建成功
    const ROOM_CREATE_SUCC = 'roomCreateSucc';

    // 退出房间
    const ROOM_LOGOUT = 'roomLogout';

    // 房间加入成功通知
    const ROOM_JOIN_SUCC_INFORM = 'roomJoinSuccInform';


    const ERROR = 'error';

    // 庄家牌
    const DEALER_PAI_DATA = 'dealerPaiData';

    // 玩家牌
    const JOINER_PAI_DATA = 'joinerPaiData';

    const ALL_JOINER_PAI_DATA = 'allJoinerPaiData';

    // 房间所有数据初始化
    const ROOM_DATA_INIT = 'roomDataInit';
    // 房间信息变化
    const ROOM_INFO_CHANGE = 'roomInfoChange';

    // 所有用户状态重置
    const CLIENT_STATUS_RESET = 'clientStatusReset';

    // 用户准备好
    const CLIENT_READIED = 'clientReadied';

    // T 人成功

    const T_SUCC = 'tSucc';

}