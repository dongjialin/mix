<?php

namespace Tcp\Handlers;

use Mix\Core\Bean\AbstractObject;
use Mix\Helper\JsonHelper;
use Mix\Tcp\Handler\TcpHandlerInterface;
use Mix\Tcp\TcpConnection;

/**
 * Class TcpHandler
 * @package Tcp\Handlers
 * @author liu,jian <coder.keda@gmail.com>
 */
class TcpHandler extends AbstractObject implements TcpHandlerInterface
{

    /**
     * @var string
     */
    public $eof = "\r\n";

    /**
     * 初始化事件
     */
    public function onInitialize()
    {
        parent::onInitialize(); // TODO: Change the autogenerated stub
        // 从server中获取eof配置
        $this->eof = server()->setting['package_eof'] ?? $this->eof;
    }

    /**
     * 开启连接
     * @param TcpConnection $tcp
     */
    public function connect(TcpConnection $tcp)
    {
        // TODO: Implement open() method.
    }

    /**
     * 处理消息
     * @param TcpConnection $tcp
     * @param string $data
     */
    public function receive(TcpConnection $tcp, string $data)
    {
        // TODO: Implement message() method.
        // 主动退出处理 (ctrl+c)
        if (base64_encode($data) == '//T//QYNCg==') {
            $tcp->disconnect();
        }
        // 解析数据
        $data = json_decode($data, true);
        if (!$data) {
            $response = [
                'jsonrpc' => '2.0',
                'error'   => [
                    'code'    => -32600,
                    'message' => 'Invalid Request',
                ],
                'id'      => null,
            ];
            $tcp->send(JsonHelper::encode($response) . $this->eof);
            return;
        }
        if (!isset($data['method']) || !isset($data['params']) || !isset($data['id'])) {
            $response = [
                'jsonrpc' => '2.0',
                'error'   => [
                    'code'    => -32700,
                    'message' => 'Parse error',
                ],
                'id'      => null,
            ];
            $tcp->send(JsonHelper::encode($response) . $this->eof);
            return;
        }
        // 路由到控制器
        list($controller, $action) = explode('.', $data['method']);
        $controller = \Mix\Helper\NameHelper::snakeToCamel($controller, true) . 'Controller';
        $controller = 'Tcp\\Controllers\\' . $controller;
        $action     = 'action' . \Mix\Helper\NameHelper::snakeToCamel($action, true);
        $response   = [
            'jsonrpc' => '2.0',
            'error'   => [
                'code'    => -32601,
                'message' => 'Method not found',
            ],
            'id'      => $data['id'],
        ];
        if (!class_exists($controller)) {
            $tcp->send(JsonHelper::encode($response) . $this->eof);
            return;
        }
        $controller = new $controller;
        if (!method_exists($controller, $action)) {
            $tcp->send(JsonHelper::encode($response) . $this->eof);
            return;
        }
        call_user_func([$controller, $action], $data['params'], $data['id']);
    }

    /**
     * 连接关闭
     * @param TcpConnection $tcp
     */
    public function close(TcpConnection $tcp)
    {
        // TODO: Implement close() method.
    }

}
