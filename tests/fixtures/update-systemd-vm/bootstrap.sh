#!/bin/bash
set -eu
seed=/mnt/isolated-seed
cp -a "$seed/isolated-php" /opt/isolated-php
ln -s /opt/isolated-php/php /usr/bin/php
mkdir -p /srv/search-startpage/bin/systemd /srv/search-startpage/tests /srv/search-startpage/storage
cp "$seed/stop-update-execution.sh" /srv/search-startpage/bin/systemd/
cp "$seed/update-execution-worker.php" /srv/search-startpage/bin/
cp "$seed/update-systemd-manager.php" /srv/search-startpage/tests/
cp "$seed/diagnostics.sh" /srv/search-startpage/tests/diagnostics.sh
cp "$seed/search-update-execution.service" /etc/systemd/system/
chmod 0644 /etc/systemd/system/search-update-execution.service
chown -R www-data:www-data /srv/search-startpage
touch /etc/search-systemd-vm-test-only
cat > /etc/systemd/system/search-isolated-boot-proof.service <<'EOF'
[Unit]
Description=Disposable VM boot verification
After=search-update-execution.service
ConditionPathExists=/srv/search-startpage/storage/verify-second-boot
[Service]
Type=simple
ExecStart=/usr/bin/php /srv/search-startpage/tests/update-systemd-manager.php
StandardOutput=journal+console
StandardError=journal+console
[Install]
WantedBy=multi-user.target
EOF
cat > /etc/systemd/system/search-isolated-diagnostics.service <<'EOF'
[Unit]
Description=Disposable VM process observations
After=systemd-journald.service
[Service]
Type=simple
ExecStart=/bin/bash /srv/search-startpage/tests/diagnostics.sh
StandardOutput=journal+console
StandardError=journal+console
[Install]
WantedBy=multi-user.target
EOF
systemctl daemon-reload
systemctl enable search-isolated-boot-proof.service
systemctl enable --now search-isolated-diagnostics.service
/usr/bin/php /srv/search-startpage/tests/update-systemd-manager.php > /dev/ttyS0 2>&1 || { echo ISOLATED_SYSTEMD_FAILED > /dev/ttyS0; poweroff; exit 1; }
