<?php
declare(strict_types=1);
/**
 * PHPInSimMod - CRON Module
 * @package PRISM
 * @subpackage CRON
*/

/**
* Filur proving, once again, that he is a much better programmer then Dygear.
*/
class cron
{
    public //config
        $crontab;

    protected
    array $jobs = [];
    protected
    int $time = 0;

    public function __construct()
    {
        $this->time = time() - 1;

        Config::GetMyConfig();

        try
        {
            if (FALSE === file_exists($this->crontab))
                $this->writeTemplate($this->crontab);

            $this->loadTable($this->crontab);
#            Timers::Add('Cron', new Timer(1, -1, -1, array($this, 'tick')));
#            console('Cron started ('.sizeof($this->jobs).' jobs)');
        }
        catch (Exception $e)
        {
            trigger_error($e->getMessage(), E_USER_WARNING);
        }
    }

    public function tick()
    {
        $time = time();

        if (abs($time - $this->time) > 10)
            $this->time = $time - 1;

        while ($this->time < $time)
        {
            $now = date('siHdmw', ++$this->time);

            foreach ($this->jobs as $job)
            {
                if (FALSE === (bool) preg_match('/'.$job['regex'].'/', $now))
                    continue;

                switch ($job['cmd'][0])
                {
                    case '/':
                        IS_MST()->Msg($job['cmd'])->Send();
                        break;
                    default:
                        IS_MSX()->Msg($job['cmd'])->Send();
                        break;
                }
            }
        }
    }

    /**
     * @param $s
     */
    protected function write($s)
    {
        $logfile = $this->crontab . '.log';
        file_put_contents($logfile, @file_get_contents($logfile) . '['.date('y-m-d H:i:s').'] ' . $s . PHP_EOL);
    }

    /**
     * @throws Exception
     */
    /**
     * @param $file
     * @throws Exception
     */
    protected function loadTable($file)
    {
        if (FALSE === file_exists($file) || FALSE === is_file($file) ||  FALSE === ($file_contents = file_get_contents($file)))
            throw new Exception('<Cron> cannot not load crontab "'.$file.'"');

        foreach (preg_split('/\r?\n/', $file_contents, -1, PREG_SPLIT_NO_EMPTY) as $line)
        {
            if ($line[0] === '#')
                continue;

            list($seconds, $minutes, $hours, $mday, $month, $day, $command) = preg_split('/\s+/', $line, 7, PREG_SPLIT_NO_EMPTY);

            if ($seconds == '' || $minutes == '' || $hours == '' || $mday == '' || $month == '' || $day == '' || $command == '')
                continue;

            $this->jobs[] = [
                'regex'    => $this->format($seconds, 59) . $this->format($minutes, 59) . $this->format($hours, 23) . $this->format($mday, 31) . $this->format($month, 12) . $this->format($day, 6, 1),
                'cmd'    => $command
            ];
        }
    }

    /**
     * @param $value
     * @param $rangemax
     * @param int $digits
     * @return string
     */
    protected function format($value, $rangemax, $digits=2): string
    {
        if ($value === '*')
        {
            return str_repeat('.', $digits);
        }

        $value = (preg_match('/\*\/(\d+)/', $value, $match)) ? range(0, $rangemax, $match[1]) : preg_split('/\s*,\s*/', $value, -1, PREG_SPLIT_NO_EMPTY);
        $value = $this->zeroPadArray($value, $digits);

        return '('.join($value, '|').')';
    }

    /**
     * @param $array
     * @param $size
     * @return mixed
     */
    protected function zeroPadArray($array, $size)
    {
        foreach ($array as $key => &$value)
        {
            $value = str_pad($value, $size, '0', STR_PAD_LEFT);
        }

        return $array;
    }

    /**
     * @param $filename
     */
    protected function writeTemplate($filename)
    {
        file_put_contents($filename,
            '#    +-------------------------- second (0 - 59)'. PHP_EOL .
            '#    |'. PHP_EOL .
            '#    |    +---------------------- minute (0 - 59)'. PHP_EOL .
            '#    |    |'. PHP_EOL .
            '#    |    |    +------------------ hour (0 - 23)'. PHP_EOL .
            '#    |    |    |'. PHP_EOL .
            '#    |    |    |    +-------------- day of month (1 - 31)'. PHP_EOL .
            '#    |    |    |    |'. PHP_EOL .
            '#    |    |    |    |    +---------- month (1 - 12)'. PHP_EOL .
            '#    |    |    |    |    |'. PHP_EOL .
            '#    |    |    |    |    |    +------ day of week (0 - 6) (Sunday=0 or 7)'. PHP_EOL .
            '#    |    |    |    |    |    |'. PHP_EOL .
            '#    |    |    |    |    |    |    +-- command'. PHP_EOL .
            '#    |    |    |    |    |    |    |'. PHP_EOL .
            ''. PHP_EOL .
            '#    *    *    *    *    *    *    command'. PHP_EOL
        );
    }
}


