#!/bin/bash
# Builds a flashable Air_Box image: the official Raspberry Pi OS Lite (64-bit)
# with the air.box app, Wi-Fi hotspot and captive portal preinstalled (see
# setup-airbox.sh), plus a ready-made login so it needs no screen or keyboard.
#
# Normally started by build-image.bat (Windows + Docker Desktop). Runs as root
# on Debian/Ubuntu, in a --privileged container (it uses loop devices) with
# these paths mounted:
#   /src   this repository (read-only)
#   /work  scratch space, about 6 GB; the downloaded base image is cached here
#   /out   receives airbox-pi-<date>.img.xz and airbox-credentials.txt
# The arm64 Pi OS programs run under qemu. Docker Desktop provides that; on
# plain Linux, install qemu-user-static and binfmt-support first.
#
# Settings: the AIRBOX_* variables listed in setup-airbox.sh, plus
#   AIRBOX_SSH_PASSWORD     password for the "airbox" login  default: generated
#   AIRBOX_ADMIN_PASSWORD   web admin password               default: generated
set -euo pipefail

SRC="${SRC:-/src}"
WORK="${WORK:-/work}"
OUT="${OUT:-/out}"
BASE_URL="${AIRBOX_BASE_IMAGE_URL:-https://downloads.raspberrypi.com/raspios_lite_arm64_latest}"
EXTRA_MB=1024        # room for nginx/PHP; the Pi grows the filesystem to fill the card on first boot
LOGIN_USER=airbox

log() { printf '\n==> %s\n' "$*"; }
genpass() { tr -dc 'A-HJ-NP-Za-km-z2-9' < /dev/urandom | head -c 12 || true; }  # no 0/O/1/l/I

SSH_PASSWORD="${AIRBOX_SSH_PASSWORD:-$(genpass)}"
ADMIN_PASSWORD="${AIRBOX_ADMIN_PASSWORD:-$(genpass)}"

export DEBIAN_FRONTEND=noninteractive
log "Installing build tools"
apt-get update -qq
apt-get install -y -qq --no-install-recommends \
    ca-certificates curl xz-utils fdisk e2fsprogs mount jq openssl > /dev/null

mkdir -p "$WORK" "$OUT"
cd "$WORK"

# ------------------------------------------------------------- base image --
URL="$(curl -fsSIL -o /dev/null -w '%{url_effective}' "$BASE_URL")"
XZ="$(basename "$URL")"
if [ ! -f "$XZ" ]; then
    log "Downloading $XZ"
    curl -fL --progress-bar -o "$XZ.part" "$URL"
    mv "$XZ.part" "$XZ"
fi
log "Verifying $XZ"
curl -fsSL -o "$XZ.sha256" "$URL.sha256"
sha256sum -c "$XZ.sha256"

NAME="airbox-pi-$(date +%Y-%m-%d)"
IMG="$WORK/$NAME.img"
log "Unpacking and enlarging the image"
xz -dc "$XZ" > "$IMG"
truncate -s "+${EXTRA_MB}M" "$IMG"
echo ", +" | sfdisk -q --no-reread -N 2 "$IMG"

part() { sfdisk -J "$IMG" | jq -r ".partitiontable.partitions[$1] | \"\(.start) \(.size)\""; }
read -r BOOT_START BOOT_SIZE < <(part 0)
read -r ROOT_START ROOT_SIZE < <(part 1)

R="$WORK/root"
ROOT_LOOP=""; BOOT_LOOP=""; RESOLV_BACKUP=""
cleanup() {
    for m in "$R/dev/pts" "$R/dev" "$R/proc" "$R/sys" "$R/boot/firmware" "$R"; do
        if mountpoint -q "$m"; then umount "$m" || umount -l "$m" || true; fi
    done
    if [ -n "$ROOT_LOOP" ]; then losetup -d "$ROOT_LOOP" || true; ROOT_LOOP=""; fi
    if [ -n "$BOOT_LOOP" ]; then losetup -d "$BOOT_LOOP" || true; BOOT_LOOP=""; fi
}
trap cleanup EXIT

# Containers only get the loop device nodes that existed when they started.
for i in $(seq 0 31); do
    [ -e "/dev/loop$i" ] || mknod -m 660 "/dev/loop$i" b 7 "$i"
done
ROOT_LOOP="$(losetup -f --show -o $((ROOT_START * 512)) --sizelimit $((ROOT_SIZE * 512)) "$IMG")"
BOOT_LOOP="$(losetup -f --show -o $((BOOT_START * 512)) --sizelimit $((BOOT_SIZE * 512)) "$IMG")"
e2fsck -fy "$ROOT_LOOP" > /dev/null || [ $? -le 1 ]
resize2fs "$ROOT_LOOP" > /dev/null

mkdir -p "$R"
mount "$ROOT_LOOP" "$R"
mount "$BOOT_LOOP" "$R/boot/firmware"
mount --bind /dev "$R/dev"
mount --bind /dev/pts "$R/dev/pts"
mount -t proc proc "$R/proc"
mount -t sysfs sysfs "$R/sys"

if ! chroot "$R" /bin/true; then
    echo "Error: can't run the image's arm64 programs. Enable arm64 emulation, e.g." >&2
    echo "  docker run --privileged --rm tonistiigi/binfmt --install arm64" >&2
    exit 1
fi

# Let the chroot reach the network, and keep package installs from trying
# to start services inside it.
RESOLV_BACKUP="$WORK/resolv.conf.orig"
rm -f "$RESOLV_BACKUP"
if [ -e "$R/etc/resolv.conf" ] || [ -L "$R/etc/resolv.conf" ]; then cp -a "$R/etc/resolv.conf" "$RESOLV_BACKUP"; fi
rm -f "$R/etc/resolv.conf"
cp /etc/resolv.conf "$R/etc/resolv.conf"
printf '#!/bin/sh\nexit 101\n' > "$R/usr/sbin/policy-rc.d"
chmod 755 "$R/usr/sbin/policy-rc.d"

# ----------------------------------------------------------- install app --
log "Copying the app into the image"
STAGE="$R/tmp/airbox-src"
rm -rf "$STAGE"
mkdir -p "$STAGE"
tar -C "$SRC" --exclude=./.git --exclude=./dist --exclude=./.playwright-mcp \
    --exclude=./data --exclude=./storage -cf - . | tar -C "$STAGE" -xf -
# Windows checkouts may have CRLF line endings; scripts and configs for the
# Pi need LF.
find "$STAGE/deploy" -type f ! -name '*.bat' -exec sed -i 's/\r$//' {} +
sed -i 's/\r$//' "$STAGE/php.ini"

log "Running setup-airbox.sh inside the image (arm64 emulation, takes a while)"
ENV_ARGS=(AIRBOX_IMAGE_BUILD=1 AIRBOX_APP_SRC=/tmp/airbox-src "AIRBOX_ADMIN_PASSWORD=$ADMIN_PASSWORD")
for v in AIRBOX_SSID AIRBOX_WIFI_PASSWORD AIRBOX_COUNTRY AIRBOX_CHANNEL AIRBOX_HOSTNAME; do
    if [ -n "${!v:-}" ]; then ENV_ARGS+=("$v=${!v}"); fi
done
chroot "$R" /usr/bin/env "${ENV_ARGS[@]}" /bin/bash /tmp/airbox-src/deploy/raspberry-pi/setup-airbox.sh

# ------------------------------------------------------ first-boot login --
log "Adding the first-boot login and enabling SSH"
# Pi OS's first-boot user setup reads userconf.txt (name:password-hash),
# renames the default account to it and skips the on-screen prompt. An empty
# "ssh" file makes it turn SSH on. Both files are removed once applied.
echo "$LOGIN_USER:$(openssl passwd -6 "$SSH_PASSWORD")" > "$R/boot/firmware/userconf.txt"
touch "$R/boot/firmware/ssh"
# cloud-init runs on first boot too; give it the same hostname so it can't
# set a different one.
printf '\nhostname: %s\n' "${AIRBOX_HOSTNAME:-airbox}" >> "$R/boot/firmware/user-data"

# --------------------------------------------------------------- tidy up --
log "Cleaning up"
chroot "$R" apt-get clean
rm -rf "$STAGE" "$R/usr/sbin/policy-rc.d"
rm -f "$R/etc/resolv.conf"
if [ -n "$RESOLV_BACKUP" ] && { [ -e "$RESOLV_BACKUP" ] || [ -L "$RESOLV_BACKUP" ]; }; then
    cp -a "$RESOLV_BACKUP" "$R/etc/resolv.conf"
fi
# Zero the free space so the compressed image stays small.
dd if=/dev/zero of="$R/zero.fill" bs=4M status=none 2> /dev/null || true
rm -f "$R/zero.fill"
sync
cleanup
trap - EXIT

log "Compressing (several minutes)"
xz -T0 -6 -c "$IMG" > "$OUT/$NAME.img.xz.part"
mv "$OUT/$NAME.img.xz.part" "$OUT/$NAME.img.xz"
(cd "$OUT" && sha256sum "$NAME.img.xz" > "$NAME.img.xz.sha256")
rm -f "$IMG"

SSID_OUT="${AIRBOX_SSID:-Air_Box}"
HOST_OUT="${AIRBOX_HOSTNAME:-airbox}"
cat > "$OUT/airbox-credentials.txt" <<EOF
Air_Box image $NAME - keep this file private.

Wi-Fi name:      $SSID_OUT
Wi-Fi password:  ${AIRBOX_WIFI_PASSWORD:-(none - open network)}

Website:         http://air.box  (on the hotspot)
Web admin:       http://air.box/admin/login.php
                 user: admin   password: $ADMIN_PASSWORD

SSH login:       ssh $LOGIN_USER@10.0.0.1  (on the hotspot)
                 or ssh $LOGIN_USER@$HOST_OUT.local  (over Ethernet)
                 password: $SSH_PASSWORD
EOF

log "Done"
ls -lh "$OUT/$NAME.img.xz"
echo "Login details: airbox-credentials.txt (next to the image)"
