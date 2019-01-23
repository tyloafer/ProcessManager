<?php
/**
 * @comment
 * @authors TY loafer (tyloafer@gmail.com)
 * @date    2019-01-11 17:12:42
 */
namespace ProcessManager;
use \swoole_process;
use \swoole_table;

class Manager
{
    /**
     *  manager 配置
     * @var array $config
     */
    private $config;

    /**
     * 任务配置
     * @var array $task
     */
    private $tasks;

    /**
     * 任务对应的进程id
     * @var array $task_process_ids
     */
    private $task_process_ids;

    /**
     * 进程资源
     * @var array $processes
     */
    private $processes;

    /**
     * 主进程id
     * @var int $master_pid
     */
    private $master_pid;

    /**
     * 信号量
     * @var Single $singo
     */
    private $singo;

    /**
     * 内存表
     * @var swoole_table $memory_table
     */
    private $memory_table;
    
    /**
     * 初始化 配置
     */
    public function __construct()
    {
        declare(ticks = 1);
        $this->master_pid = posix_getpid();
        if (!file_exists(__DIR__ . '/config/config.php')) {
            exit('config file can not be load!' . PHP_EOL);
        }

        if (!file_exists(__DIR__ . '/config/tasks.php')) {
            exit('task file can not be load!' . PHP_EOL);
        }

        $this->config = require __DIR__ . '/config/config.php';
        $this->tasks = require __DIR__ . '/config/tasks.php';

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
            $this->config = require __DIR__ . 'config/config.php';
        }

        if (!file_exists('config/tasks.php')) {
            echo 'Reload failed, task file can not be load!' . PHP_EOL;
            exit;
        } else {
            $this->tasks = require __DIR__ . 'config/tasks.php';
        }
        $this->resetProcessNumber();
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
     * 重置信号量后，增删进程数量
     */
    public function resetProcessNumber()
    {
        foreach ($this->task_process_ids as $index => $tasks) {
            $task_num = 0;
            foreach ($tasks as $pid) {
                ++$task_num;
                // 去除超出的进程
                if ($task_num > $this->tasks[$index]['work_num']) {
                    swoole_process::kill($pid, 0);
                }
            }
            // 添加变更后增加的进程
            for ($i = $task_num; $i <= $this->tasks[$index]['work_num']; $i++) {
                $this->createProcess($index, $this->tasks[$index]['action']);
            }
        }
    }

    /**
     * 进程检查
     */
    public function checkTask()
    {
        while (true) {
            switch ($this->singo->singo) {
                case 0:
                    // 重启down掉的进程
                    while ($ret = swoole_process::wait(false)) {
                        echo "PID {$ret['pid']} is down" . PHP_EOL;
                        $this->restartProcess($ret['pid']);
                        $this->recycleFromMemoryTable($ret['pid']);
                    }

                    foreach ($this->processes as $pid => $task) {
                        if ($this->upToRequestNumberLimit($pid)) {
                            $this->restartProcess($pid);
                        }
                    }

                    // To-do 进程数量检测
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
        $this->recyclePid($pid);
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
        print_r($this->task_process_ids);
        if (isset($this->task_process_ids[$index]) && count($this->task_process_ids[$index]) > $this->config['max_children']) {
            echo 'Up to Max children';
            $this->tasks[$index]['work_num'] = $this->config['max_children'];
        } else {
            $memory_table = $this->memory_table;
            $process = new swoole_process(function (swoole_process $worker) use ($task_name, $memory_table) {
                $task = new Task();
                $task->setMemoryTable($memory_table);
                $task->$task_name();
            }, false);
            $pid = $process->start();

            $this->task_process_ids[$index][] = $pid;
            $this->processes[$pid]['process'] = $process;
            $this->processes[$pid]['index'] = $index;
            $this->saveDataToMemoryTable($pid);
        }

    }

    /**
     * 重启 进程
     * @param $pid
     */
    public function restartProcess($pid)
    {
        echo $pid . ' is restarting' . PHP_EOL;
        $task = $this->getTaskByPid($pid);
        print_r($task);
        $this->stopProcess($pid);
        if (!empty($task)) {
            $this->createProcess($task['index'], $task['action']);
        }
    }

    /**
     * 根据 pid 获取进程的任务名及索引
     * @param $pid
     * @return mixed
     */
    private function getTaskByPid($pid)
    {
        if (!isset($this->processes[$pid])) {
            return [];
        }
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
        register_shutdown_function(function () {
            if (posix_getpid() === $this->master_pid) {
                echo 'master calling shutdown' . PHP_EOL;
                $this->stopAllProcess();
            } else {
                echo 'children calling shutdown' . PHP_EOL;
            }
        });
    }

    /**
     * 注册命名空间的自动加载
     */
    public function registerAutoload()
    {
        spl_autoload_register(function ($class_name) {
            $classes = explode('\\', $class_name);
            array_shift($classes);
            $file = implode('', $classes) . '.php';
            if (file_exists($file)) {
                require $file;
            }
        });
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
     * manager 启动
     */
    public function run()
    {
        $this->initMemoryTable();
        foreach ($this->tasks as $key => $task) {
            for ($i = 0; $i < $task['work_num']; $i++) {
                $this->createProcess($key, $task['action']);
            }
        }
    }

    /**
     * 初始化内存表
     */
    public function initMemoryTable()
    {
        $size = $this->getMemoryTableSize();
        $this->memory_table = new swoole_table($size);
        $this->memory_table->column('pid', swoole_table::TYPE_INT, 4);
        $this->memory_table->column('number', swoole_table::TYPE_INT, 4);
        $this->memory_table->column('start_time', swoole_table::TYPE_INT, 8);
        $this->memory_table->column('update_time', swoole_table::TYPE_INT, 8);
        $this->memory_table->create();
    }

    /**
     * 获取内存表的大小
     */
    public function getMemoryTableSize()
    {
        $task_total = count($this->tasks);
        $size = $task_total * $this->config['max_children'];
        return $size;
    }

    public function saveDataToMemoryTable($key)
    {
        $time = time();
        $this->memory_table->set($key, [
            'pid' => $key,
            'number' => 0,
            'start_time' => $time,
            'update_time' => $time,
        ]);
    }

    public function recycleFromMemoryTable($key)
    {
        $this->memory_table->del($key);
    }

    /**
     * 判断进程是否处理超过限制次数
     * @param $pid
     * @return bool
     */
    public function upToRequestNumberLimit($pid)
    {
        $request_number = $this->memory_table->get($pid, 'number');
        if ($request_number >= $this->config['max_handle_requests']) {
            echo $pid . ' is up to Limit' . PHP_EOL;
            return true;
        } else {
            return false;
        }
    }

    /**
     * 回收pid，避免被重启
     * @param $pid
     */
    public function recyclePid($pid)
    {
        if (isset($this->processes[$pid])) {
            // 删除这个 pid对应的process
            $index = $this->processes[$pid]['index'];
            unset($this->processes[$pid]);
            // 删除task_process_ids 中的key
            $key = array_search($pid, $this->task_process_ids[$index]);
            if ($key !== false) {
                unset($this->task_process_ids[$index][$key]);
            }
        }
    }
}
echo posix_getpid() .PHP_EOL;
$manager = new Manager();
$manager->run();
$manager->checkTask();
