<?php
/**
 */
ini_set('default_socket_timeout', -1);
date_default_timezone_set("Asia/Shanghai");
/**
 * Class Task
 */
abstract class Task {
    /* config */

    private $process_name = 'php_task_';

    const uid = 80;

    const gid = 80;

    private $pid_dir = __DIR__ . '/';

    private $pidfile;

    private $pidname;

    private $start_time;

    /**
     * @param $restart bool
     * @return int
     */
    private function daemon($restart = false) {
        if (file_exists($this->pidfile) && !$restart) {
            echo "The file $this->pidfile exists.\n";
            exit();
        }
        $pid = pcntl_fork();
        if ($pid == -1) {
            die('could not fork');
        } else if ($pid) {
            // we are the parent
            exit($pid);
        } else {
            $pid2 = pcntl_fork();
            if ($pid2 == -1) {
                die('could not fork');
            } else if ($pid2) {
                // we are the parent
                exit($pid2);
            } else {
                // we are the child
                $i = file_put_contents($this->pidfile, posix_getpid());
                if ($i === false) {
                    exit("无法写入pid文件！");
                }
                posix_setuid(self::uid);
                posix_setgid(self::gid);
                cli_set_process_title($this->process_name . $this->pidname);
                pcntl_signal(SIGHUP, [$this, 'signoH']);
                pcntl_signal(SIGTERM, [$this, 'signoH']);
                pcntl_signal(SIGCHLD, [$this, 'signoH']);
                pcntl_signal(SIGQUIT, [$this, 'signoH']);
                pcntl_signal(SIGINT, [$this, 'signoH']);
                pcntl_signal(SIGUSR1, [$this, 'signoH']);
                $this->start_time = time();
                return (getmypid());
            }
        }
    }

    /**
     *
     */
    protected function run() {
        pcntl_signal_dispatch();
    }

    private function restart() {
        $this->stop();
        $this->start();
    }

    /**
     * @param $restart
     */
    private function start($restart = false) {
        $pid = $this->daemon($restart);
        $this->run();
    }

    /**
     *
     */
    private function stop() {
        if (file_exists($this->pidfile)) {
            $pid = file_get_contents($this->pidfile);
            posix_kill($pid, SIGKILL);
            unlink($this->pidfile);
        }
    }

    /**
     * @param $proc
     */
    private function help($proc) {
        printf("%s php your-class-name.php start|stop|restart|stat|list|help taskname\n", $proc);
        print <<<DOC
    继承此类重写run方法，在重写时,在循环里面调用parent::run();
    指定pid文件的名字,用来管理stop|stat|list)进程,要求有意义并且唯一;
    最后： (new yourclass)->main(\$argv)来运行你的代码;
    php your-phpfile start       :启动当前脚本并设置tsak_name
    php any-your-phpfile restart :重新启动task_name
    php any-your-phpfile stop    :停止 task_name
    php any-your-phpfile stat    :输出进程号和进程名称task_name
    php any-your-phpfile list    :列出正在执行的类名task_name

DOC;

    }

    /**
     * @param $argv
     */
    public function main($argv) {

        if (count($argv) < 2) {
            $this->help("使用方法 :");
            exit();
        }

        $this->pid_dir = sys_get_temp_dir() . '/php_task_pid/';

        if (!is_dir($this->pid_dir)) {
            mkdir($this->pid_dir);
        }

        $arr = explode("/", $argv[0]);
        $class_name = $arr[count($arr) - 1];
        $class_name = str_replace('.php', '', $class_name);
        $this->pidfile = $this->pid_dir . $class_name . ".pid";
        $this->pidname = $class_name;

        if ($argv[1] === 'stop') {
            $this->stop();
        } else if ($argv[1] === 'start') {
            $this->start();
        } else if ($argv[1] === 'list') {
            $this->list_pid();
        } else if ($argv[1] === 'restart') {
            $this->restart();
        } else if ($argv[1] === 'stat') {
            $this->stat();
        } else {
            $this->help("使用方法 :");
        }
    }

    private function stat() {

        $_pid = trim(shell_exec("ps -ef | grep \"{$this->process_name}{$this->pidname}\" | grep -v \"grep\" |awk '{print $2}'"));

        if (!$_pid) {
            $this->stop();
            exit("\n进程已停止\n");
        }

        if (is_file($this->pidfile)) {
            $pid_from_file = file_get_contents($this->pidfile);
            if ($_pid == $pid_from_file) {
                posix_kill($pid_from_file, SIGHUP);
            } else {
                posix_kill($_pid, SIGHUP);
                file_put_contents($this->pidfile, $_pid);
            }
        } else {
            posix_kill($_pid, SIGHUP);
            file_put_contents($this->pidfile, $_pid);
        }
        sleep(2);
        $stat = file_get_contents('stat.txt');

        print "\e[32m" . $stat . "\n";
        print "\e[0m";
        unlink('stat.txt');
    }

    private function getLogFile() {
        return date("Y-m-d-H-i-s-")."log.txt";
    }
    /**
     * @param $signo
     */
    public function signoH($signo) {
        switch ($signo) {
            case SIGHUP :
                $pid =posix_getpid();
                $pidname = cli_get_process_title();
                $start_time = date('Y-m-d H:i:s', $this->start_time);
                $stat = <<<STAT
-------------运行状态-----------
PID : $pid
CLASS_NAME : {$this->pidname}
PROCESS_NAME : $pidname 
START_TIME : $start_time 
-------------------------------

STAT;

                file_put_contents('stat.txt', $stat);
                break;
            case SIGTERM:
                exit;
            default : ;
        }
    }

    private function list_pid() {
        print "runnig class list：\n";
        foreach (glob($this->pid_dir . "*.pid") as $_file) {
            $arr = explode("/", $_file);
            $pidfile = $arr[count($arr) - 1];
            $pidname = str_replace('.pid', '', $pidfile);
            print "\033[32m$pidname\n";
        }
        print "\033[0m";
    }
}
