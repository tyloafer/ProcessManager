<?php
/**
 * @comment 
 * @authors Li Xiaoyu (lixiaoyu@myhexin.com)
 * @date    2019-01-12 14:18:12
 */

namespace ProcessManager;

class Single
{
    public $singo;

    public $handler;

    public $messages;

    public function __construct()
    {
        $this->messages = new Message();
    }

    /**
     * 默认信号处理器
     * @param $singo
     */
    public function singleHandler($singo)
    {
        $this->singo = $singo;
    }

    /**
     * 设置信号处理器
     * @param $singos
     * @param $callback
     * @return $this
     */
    public function setHandler($singos, $callback)
    {
        if (is_string($callback)) {
            if (!function_exists($callback)) {
                $this->messages->appendMessage('callback function not exist');
            }
        } elseif (is_array($callback)) {
            if (!method_exists($callback[0], $callback[1])) {
                $this->messages->appendMessage('callback function not exist');
            }
        }

        if (is_array($singos)) {
            foreach ($singos as $singo) {
                $this->handler[$singo] = $callback;
            }
        } else {
            $this->handler[$singos] = $callback;
        }
        return $this;        
    }

    /**
     * 安装信号处理器
     */
    public function install()
    {
        if (!empty($this->handler)) {
            foreach ($this->handler as $singo => $callback) {
                pcntl_signal($singo, $callback);
            }
        } else {
            pcntl_signal(SIGTERM, [$this, 'singleHandler']);
            pcntl_signal(SIGHUP, [$this, 'singleHandler']);
            pcntl_signal(SIGUSR1, [$this, 'singleHandler']);
        }
    }
}
