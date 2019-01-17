<?php
namespace ProcessManager;

/**
 * summary
 */
class Task
{
    /**
     * summary
     */
    
    private $singo;

    public function __construct()
    {
        declare(ticks = 1);
        $this->singo = new Single();
        $this->singo->install();
    }

    /**
     * 测试任务
     */
    public function Task1()
    {
        file_put_contents('./log', 'work1 start, pid: ' . posix_getpid() . ', signo: ' . $this->singo->singo . PHP_EOL, FILE_APPEND);
        while (!$this->singo->singo) {
            file_put_contents('./log', 'worker1 working, pid: ' . posix_getpid() . ', signo: ' . $this->singo->singo . PHP_EOL, FILE_APPEND);
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
        }
        file_put_contents('./log', 'worker2 sleeping pid: ' . posix_getpid() . ', signo: ' . $this->singo->singo . PHP_EOL, FILE_APPEND);
        sleep(2);
    }
}