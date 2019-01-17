<?php
/**
 * @comment
 * @authors TY loafer (tyloafer@gmail.com)
 * @date    2019-01-11 17:12:42
 */
namespace ProcessManager;
use \swoole_process;

class Manager
{
    /* manager 配置 */
    private $config;

    /* 任务配置 */
    private $tasks;

    /* 任务对应的进程id */
    private $task_process_ids;

    /* 进程资源 */
    private $processes;

    /* 主进程id */
    private $master_pid;

    /* 信号量 */
    private $singo;
    
    /**
     * 初始化 配置
     */
    public function __construct()
    {
        declare(ticks = 1);
        $this->master_pid = posix_getpid();
        if (!file_exists('config/config.php')) {
            exit('config file can not be load!' . PHP_EOL);
        }

        if (!file_exists('config/tasks.php')) {
            exit('task file can not be load!' . PHP_EOL);
        }

        $this->config = require 'config/config.php';
        $this->tasks = require 'config/tasks.php';

        // 注册全局异常退出函数
        $this->registerShutdown();
        $this->registerAutoload();
        $this->registerSingo();
    }

    /**
     * 重载配置
     */
    public function reload()
    {
        if (!file_exists('config/config.php')) {
            echo 'Reload failed, config file can not be load!' . PHP_EOL;
            exit;
        } else {
            $this->config = require 'config/config.php';
        }

        if (!file_exists('config/tasks.php')) {
            echo 'Reload failed, task file can not be load!' . PHP_EOL;
            exit;
        } else {
            $this->tasks = require 'config/tasks.php';
        }
        $this->resetSingo();
    }

    /**
     * 重置信号量，在非退出信息处理结束后重置信号
     */
    public function resetSingo()
    {
        echo 'reset singo' . PHP_EOL;
        $this->singo->singo = 0;        
    }

    /**
     * 进程检查
     */
    public function checkTask()
    {
        while (true) {
            switch ($this->singo->singo) {
                case 0:
                    while ($ret = swoole_process::wait(false)) {
                        echo "PID {$ret['pid']} is down" . PHP_EOL;
                        print_r($ret);
                        $this->restartProcess($ret['pid']);
                    }
                    echo 'sleeping' . PHP_EOL;
                    sleep(1);
                    break;
                case SIGTERM:
                    $this->stopAllProcess();
                    break;
                case SIGUSR1:
                    $this->reload();
                    break;
            }
        }
    }

    /**
     * 根据 pid 停止进程
     * @param $pid
     */
    public function stopProcess($pid)
    {
        if (swoole_process::kill($pid, 0)) {
            echo '关闭进程 ' . $pid . PHP_EOL;
            swoole_process::kill($pid);
        }
    }

    /**
     * 停止所有管理的子进程
     */
    public function stopAllProcess()
    {
        foreach ($this->task_process_ids as $tasks) {
            foreach ($tasks as $pid) {
                if (swoole_process::kill($pid, 0)) {
                    echo '关闭进程 ' . $pid . PHP_EOL;
                    swoole_process::kill($pid);
                }
            }
        }
    }

    /**
     * 根据指定任务，创建子进程
     * @param $index int 任务索引
     * @param $task_name string 任务名称
     */

    public function createProcess($index, $task_name)
    {
        $process = new swoole_process(function (swoole_process $workder) use ($task_name) {
            (new Task())->$task_name();
        }, false);
        $pid = $process->start();

        $this->task_process_ids[$index][] = $pid;
        $this->processes[$pid]['process'] = $process;
        $this->processes[$pid]['index'] = $index;
    }

    /**
     * 重启 进程
     * @param $pid
     */
    public function restartProcess($pid)
    {
        echo 'STop ' . $pid . PHP_EOL;
        $this->stopProcess($pid);
        $task = $this->getTaskByPid($pid);
        print_r($task);
        $this->createProcess($task['index'], $task['action']);
    }

    /**
     * 根据 pid 获取进程的任务名及索引
     * @param $pid
     * @return mixed
     */
    private function getTaskByPid($pid)
    {
        $index = $this->processes[$pid]['index'];
        $task = $this->tasks[$index];
        $task['index'] = $index;
        return $task;
    }

    /**
     * 注册 Manager 结束操作
     */
    public function registerShutdown()
    {
        echo 'register shutdown' . PHP_EOL;
        register_shutdown_function([$this, 'shutdownCallback']);
    }

    /**
     * shutdown回调
     */
    public function shutdownCallback()
    {
        if (posix_getpid() === $this->master_pid) {
            echo 'master calling shutdow' . PHP_EOL;
            $this->stopAllProcess();
        } else {
            echo 'children calling shutdow' . PHP_EOL;
        }
    }

    /**
     * 注册命名空间的自动加载
     */
    public function registerAutoload()
    {
        spl_autoload_register([$this, 'autoload']);
    }

    /**
     * 注册信号量
     */
    public function registerSingo()
    {
        if (!$this->singo) {
            $this->singo = new Single();
            $this->singo->install();
        }
    }

    /**
     * 自动加载的回调处理
     * @param $class_name
     */
    private function autoload($class_name)
    {
        $classes = explode('\\', $class_name);
        array_shift($classes);
        $file = implode('', $classes) . '.php';
        require $file;
    }

    /**
     * manager 启动·
     */
    public function run()
    {
        foreach ($this->tasks as $key => $task) {
            for ($i = 0; $i < $task['work_num']; $i++) {
                $this->createProcess($key, $task['action']);
            }
        }
    }
}
echo posix_getpid() .PHP_EOL;
$manager = new Manager();
$manager->run();
$manager->checkTask();
