<?php
/**
 * @comment 
 * @authors TY loafer (tyloafer@gmail.com)
 * @date    2019-01-12 14:18:12
 */
namespace ProcessManager;

class Message
{
    private $messages = [];

    /**
     * 添加信息
     * @param $message
     */
    public function appendMessage($message)
    {
        $this->messages[] = $message;
    }

    /**
     * 获取全部信息
     * @return array
     */
    public function getMessages()
    {
        return $this->messages;
    }

    /**
     * 获取全部信息
     * @return mixed
     */
    public function getLastMessage()
    {
        return end($this->messages);
    }

    /**
     * 获取第一条信息
     * @return mixed
     */
    public function getFirstMessage()
    {
        return reset($this->messages);
    }
}
