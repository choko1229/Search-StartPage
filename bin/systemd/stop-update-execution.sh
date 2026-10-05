#!/bin/sh
# Invoked by the systemd example, which supplies its tracked MAINPID.
# Never return to the manager while the supervisor is draining an update.
case "${MAINPID:-}" in
 ''|*[!0-9]*|0) echo 'Update stop supervisor identity unavailable.' >&2; exit 1 ;;
esac
/usr/bin/php /srv/search-startpage/bin/update-execution-worker.php --stop
stop_result=$?
while kill -0 "$MAINPID" 2>/dev/null; do
 /bin/sleep 1
done
exit "$stop_result"
