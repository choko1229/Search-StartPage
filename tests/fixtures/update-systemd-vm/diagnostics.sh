#!/bin/bash
set -eu
test -f /etc/search-systemd-vm-test-only
while true; do
 echo VM_PROCESS_OBSERVATION
 ps -eo pid,ppid,pgid,sid,stat,wchan:24,comm | awk 'NR==1 || $NF=="php" || $NF=="systemctl"'
 timeout 5 systemctl show search-update-execution.service --property=ActiveState --property=SubState --property=MainPID --property=ControlPID --property=Job || true
 sleep 10
done
