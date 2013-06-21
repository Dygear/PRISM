#!/bin/bash
php -e PHPInSimMod.php %1 %2
if [ $? != 0 ]; then
echo "PRISM Crashed, Restarting in 3 Seconds.."; sleep 3
sh $0
fi