<?php
namespace ProcessManager;

/**
 * summary
 */
class Task
{
    /**
     * 信号量
     * @var Single $singo
     */
    private $singo;

    /**
     * @var \swoole_process $worker
     */
    private $worker;

    /**
     * @var \swoole_table$memory_table
     */
    private $memory_table;

    /**
     * 自身pid，使用内存表会用到的
     * @var int $pid
     */
    private $pid;

    public function __construct()
    {
        declare(ticks = 1);
        $this->singo = new Single();
        $this->singo->install();
        $this->pid = posix_getpid();
    }

    public function setWorker($worker)
    {
        $this->worker = $worker;
    }

    public function setMemoryTable($table)
    {
        $this->memory_table = $table;
    }

    /**
     * 测试任务
     */
    public function Task1()
    {
        file_put_contents('./log', 'work1 start, pid: ' . posix_getpid() . ', signo: ' . $this->singo->singo . PHP_EOL, FILE_APPEND);
        while (!$this->singo->singo) {
            file_put_contents('./log', 'worker1 working, pid: ' . posix_getpid() . ', signo: ' . $this->singo->singo . PHP_EOL, FILE_APPEND);
            $this->incrProcessNumber(1);
            sleep(1);
        }
        file_put_contents('./log', 'worker1 sleeping pid: ' . posix_getpid() . ', signo: ' . $this->singo->singo . PHP_EOL, FILE_APPEND);
        sleep(2);
    }

    /**
     * 测试任务2
     */
    public function Task2()
    {
        file_put_contents('./log', 'work2 start, pid: ' . posix_getpid() . ', signo: ' . $this->singo->singo . PHP_EOL, FILE_APPEND);
        while (!$this->singo->singo) {
            file_put_contents('./log', 'worker2 working, pid: ' . posix_getpid() . ', signo: ' . $this->singo->singo . PHP_EOL, FILE_APPEND);
            sleep(1);
            $this->incrProcessNumber(1);
        }
        file_put_contents('./log', 'worker2 sleeping pid: ' . posix_getpid() . ', signo: ' . $this->singo->singo . PHP_EOL, FILE_APPEND);
        sleep(2);
    }

    /**
     * 修改处理数
     * @param int $number
     */
    public function incrProcessNumber($number = 1)
    {
        if (empty($this->pid)) {
            $this->pid = posix_getpid();
        }
        // 增加处理数
        $this->memory_table->incr($this->pid, 'number', $number);

        // 修改update_time
        $last_update_time = $this->memory_table->get($this->pid, 'update_time');
        $incr_by = time() - $last_update_time;
        $this->memory_table->incr($this->pid, 'update_time', $incr_by);
    }
}