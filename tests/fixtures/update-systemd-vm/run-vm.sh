#!/bin/bash
set -eu
test "${SEARCH_TEST_MODE:-}" = 1
test -f /tmp/search-systemd-vm-test-only
test ! -e /tmp/search-systemd-vm-disk.qcow2
cp -a /opt/isolated-php /seed/isolated-php
genisoimage -quiet -output /tmp/search-systemd-vm-seed.iso -volid cidata -joliet -rock /seed
qemu-img create -q -f qcow2 -F qcow2 -b /opt/vm/noble-server-cloudimg-amd64.img /tmp/search-systemd-vm-disk.qcow2 6G
boot_vm() {
 timeout 900 qemu-system-x86_64 -accel tcg -m 1024 -smp 2 -no-reboot -display none -serial stdio -monitor none -nic none -kernel /opt/vm/noble-server-cloudimg-amd64-vmlinuz-generic -initrd /opt/vm/noble-server-cloudimg-amd64-initrd-generic -append 'root=LABEL=cloudimg-rootfs rw console=ttyS0 systemd.mask=systemd-networkd-wait-online.service' -drive file=/tmp/search-systemd-vm-disk.qcow2,if=virtio,format=qcow2 -cdrom /tmp/search-systemd-vm-seed.iso >> /tmp/search-systemd-vm-console.log 2>&1
}
boot_vm
if ! grep -q 'ISOLATED_SYSTEMD_FIRST_PASSED' /tmp/search-systemd-vm-console.log || grep -q 'ISOLATED_SYSTEMD_FAILED' /tmp/search-systemd-vm-console.log; then
 grep 'PASS:\|ISOLATED_SYSTEMD_\|VM_FAILURE_STATE' /tmp/search-systemd-vm-console.log || true
 tail -n 65 /tmp/search-systemd-vm-console.log
 exit 1
fi
# Cold boot the same persisted guest disk after its requested reboot, without a TCG warm reset.
boot_vm
if ! grep -q 'ISOLATED_SYSTEMD_FIRST_PASSED' /tmp/search-systemd-vm-console.log || ! grep -q 'ISOLATED_SYSTEMD_BOOT_PASSED' /tmp/search-systemd-vm-console.log || grep -q 'ISOLATED_SYSTEMD_FAILED' /tmp/search-systemd-vm-console.log; then
 grep 'PASS:\|ISOLATED_SYSTEMD_\|VM_FAILURE_STATE' /tmp/search-systemd-vm-console.log || true
 tail -n 65 /tmp/search-systemd-vm-console.log
 exit 1
fi
grep 'PASS:\|ISOLATED_SYSTEMD_' /tmp/search-systemd-vm-console.log
