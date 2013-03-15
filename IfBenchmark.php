<?php
if(!isset($_GET['True']))
    die('What the fuck, no get?');
$condition = ($_GET['True'] == 1);

function psrIf($n)
{
    ob_start();
    $t = microtime(true);
    while ($i < $n) {
        if (true) {
           $i++; 
        } else {
            $i++;
        }
    }
    $tmp = microtime(true) - $t;
    ob_end_clean();
    
    return $n . ' - ' .$tmp;
}

function altIf($n)
{
    ob_start();
    $t = microtime(true);
    while ($i < $n) {
        if ($condition):
            $i++;
        else:
            $i++;
        endif;
    }
    $tmp = microtime(true) - $t;
    ob_end_clean();
    
    return $n . ' - ' .$tmp;
}

function wngIf($n)
{
    ob_start();
    $t = microtime(true);
    while ($i < $n) {
        if ($condition)
            $i++;
        else
            $i++;
    }
    $tmp = microtime(true) - $t;
    ob_end_clean();
    
    return $n . ' - ' .$tmp;
}

print('psr' . psrIf(0) . '</br>');
print('alt' . altIf(0) . '</br>');
print('wng' . wngIf(0) . '</br>');
print('psr' . psrIf(10) . '</br>');
print('alt' . altIf(10) . '</br>');
print('wng' . wngIf(10) . '</br>');
print('psr' . psrIf(100) . '</br>');
print('alt' . altIf(100) . '</br>');
print('wng' . wngIf(100) . '</br>');
print('psr' . psrIf(1000) . '</br>');
print('alt' . altIf(1000) . '</br>');
print('wng' . wngIf(1000) . '</br>');
print('psr' . psrIf(10000) . '</br>');
print('alt' . altIf(10000) . '</br>');
print('wng' . wngIf(10000) . '</br>');
print('psr' . psrIf(100000) . '</br>');
print('alt' . altIf(100000) . '</br>');
print('wng' . wngIf(100000) . '</br>');
print('psr' . psrIf(1000000) . '</br>');
print('alt' . altIf(1000000) . '</br>');
print('wng' . wngIf(1000000) . '</br>');
