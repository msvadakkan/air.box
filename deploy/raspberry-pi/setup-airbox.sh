#!/bin/bash
# Turns Raspberry Pi OS Lite (64-bit, trixie) into an Air_Box:
#   * nginx + PHP-FPM serving the air.box app from /var/www/air.box
#   * a Wi-Fi hotspot "Air_Box" (NetworkManager), with the Pi at 10.0.0.1
#   * a captive portal: on the hotspot every hostname resolves to the Pi, and
#     nginx redirects anything that isn't air.box to http://air.box/, so phones
#     pop the site up as soon as they join
#   * hostname "airbox" (http://airbox.local when plugged into a wired network)
#
# Used two ways:
#   * by build-image.sh, inside the image it's building (AIRBOX_IMAGE_BUILD=1)
#   * directly on a running Pi, from a copy of this repository:
#       sudo bash deploy/raspberry-pi/setup-airbox.sh && sudo reboot
#     The first run needs internet to install nginx and PHP.
#
# Re-running it updates the app code and config, and needs no internet once
# nginx and PHP are installed, so an Air_Box can be updated over its own
# hotspot. The library on the device (/var/www/air.box/data and storage,
# including Rotana Bot's answers) is left alone. Pass the same settings as
# the first time, or the hotspot goes back to the defaults below.
#
# Settings (environment variables, all optional):
#   AIRBOX_SSID            Wi-Fi name                      default Air_Box
#   AIRBOX_WIFI_PASSWORD   8+ chars for a WPA2 network     default empty = open network
#   AIRBOX_COUNTRY         Wi-Fi country (ISO 3166 code)   default AE
#   AIRBOX_CHANNEL         2.4 GHz channel, 1-11           default 6
#   AIRBOX_HOSTNAME        device hostname                 default airbox
#   AIRBOX_ADMIN_PASSWORD  sets the web admin password     default: leave as is
set -euo pipefail

SSID="${AIRBOX_SSID:-Air_Box}"
WIFI_PASSWORD="${AIRBOX_WIFI_PASSWORD:-}"
COUNTRY="${AIRBOX_COUNTRY:-AE}"
CHANNEL="${AIRBOX_CHANNEL:-6}"
HOST="${AIRBOX_HOSTNAME:-airbox}"
ADMIN_PASSWORD="${AIRBOX_ADMIN_PASSWORD:-}"
IMAGE_BUILD="${AIRBOX_IMAGE_BUILD:-0}"

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_SRC="${AIRBOX_APP_SRC:-$(cd "$HERE/../.." && pwd)}"
WEB_ROOT=/var/www/air.box
AP_ADDR=10.0.0.1   # also in files/nginx-airbox.conf and files/airbox-captive-dns.conf

log() { printf '\n==> %s\n' "$*"; }
die() { echo "Error: $*" >&2; exit 1; }

[ "$(id -u)" -eq 0 ] || die "run as root (sudo)."
[ -f "$APP_SRC/includes/config.php" ] || die "air.box app not found at $APP_SRC"
grep -q "^${COUNTRY}[[:space:]]" /usr/share/zoneinfo/iso3166.tab || die "unknown country code '$COUNTRY'"
case "$CHANNEL" in [1-9]|1[01]) ;; *) die "AIRBOX_CHANNEL must be 1-11" ;; esac
if [ -n "$WIFI_PASSWORD" ] && [ "${#WIFI_PASSWORD}" -lt 8 ]; then
    die "AIRBOX_WIFI_PASSWORD must be at least 8 characters"
fi

# ---------------------------------------------------------------- packages --
export DEBIAN_FRONTEND=noninteractive
if dpkg -s nginx php-fpm > /dev/null 2>&1; then
    log "nginx and PHP are already installed"
else
    log "Installing nginx and PHP"
    apt-get update
    apt-get install -y --no-install-recommends nginx php-fpm
fi

PHPV="$(php -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;')"

# --------------------------------------------------------------------- PHP --
log "Configuring PHP $PHPV"
# Same upload/runtime limits the app documents for every other setup.
install -m 644 "$APP_SRC/php.ini" "/etc/php/$PHPV/fpm/conf.d/99-airbox.ini"
# Debian allows 5 PHP workers. Media goes out through nginx
# (X-Accel-Redirect), so workers only handle quick page loads, but a cabin of
# phones joining at once can still queue up behind 5. 10 stays well within a
# 512 MB Pi Zero 2 W.
sed -i -E 's/^pm\.max_children = .*/pm.max_children = 10/' "/etc/php/$PHPV/fpm/pool.d/www.conf"

# ------------------------------------------------------------------- nginx --
log "Configuring nginx"
sed -e "s#unix:/run/php/php-fpm.sock#unix:/run/php/php${PHPV}-fpm.sock#" \
    -e "s#airbox\.local#${HOST}.local#" \
    "$HERE/files/nginx-airbox.conf" > /etc/nginx/sites-available/airbox
ln -sf ../sites-available/airbox /etc/nginx/sites-enabled/airbox
rm -f /etc/nginx/sites-enabled/default
nginx -t

# --------------------------------------------------------------------- app --
log "Installing the app to $WEB_ROOT"
mkdir -p "$WEB_ROOT"
# data/ and storage/ are the device's own library: never copied over or
# overwritten. Dev-only files (Windows launcher, php -S router) are skipped.
tar -C "$APP_SRC" \
    --exclude=./.git --exclude=./.gitignore --exclude=./.gitattributes \
    --exclude=./.claude --exclude=./.playwright-mcp --exclude=./dist \
    --exclude=./deploy --exclude='./*;C' --exclude=./data --exclude=./storage \
    --exclude=./start.bat --exclude=./router.php --exclude=./php.ini \
    --exclude=./nginx.conf.example --exclude=./README.md \
    -cf - . | tar -C "$WEB_ROOT" -xf -
mkdir -p "$WEB_ROOT/data" "$WEB_ROOT/storage"
chown -R www-data:www-data "$WEB_ROOT"

# Loading the app once runs its first-run seed (admin account + demo
# catalog), so the library exists before the first visitor arrives.
log "Preparing the library"
runuser -u www-data -- env AIRBOX_ADMIN_PASSWORD="$ADMIN_PASSWORD" php -r '
    require "/var/www/air.box/includes/config.php";
    $pw = getenv("AIRBOX_ADMIN_PASSWORD");
    if ($pw !== false && $pw !== "") {
        $admins = users_db()->where(function ($u) { return ($u["role"] ?? "") === "admin"; });
        foreach ($admins as $u) {
            users_db()->update($u["id"], ["password" => password_hash($pw, PASSWORD_DEFAULT)]);
        }
        echo "Web admin password set.\n";
    }
'

# ----------------------------------------------------------------- hotspot --
log "Configuring the $SSID hotspot"
CONN=/etc/NetworkManager/system-connections/airbox-hotspot.nmconnection
UUID="$(sed -n 's/^uuid=//p' "$CONN" 2>/dev/null || true)"
[ -n "$UUID" ] || UUID="$(cat /proc/sys/kernel/random/uuid)"
{
    cat <<EOF
# Air_Box Wi-Fi hotspot (written by setup-airbox.sh).
# ipv4.method=shared makes NetworkManager run DHCP and DNS (dnsmasq) for
# clients; dnsmasq-shared.d/airbox-captive.conf turns that DNS into the
# captive portal.
[connection]
id=airbox-hotspot
uuid=$UUID
type=wifi
interface-name=wlan0
autoconnect=true
autoconnect-priority=100
autoconnect-retries=0

[wifi]
mode=ap
ssid=$SSID
band=bg
channel=$CHANNEL
powersave=2

[ipv4]
method=shared
address1=$AP_ADDR/24

[ipv6]
method=disabled
EOF
    if [ -n "$WIFI_PASSWORD" ]; then
        cat <<EOF

[wifi-security]
key-mgmt=wpa-psk
proto=rsn
pairwise=ccmp
group=ccmp
psk=$WIFI_PASSWORD
EOF
    fi
} > "$CONN"
chmod 600 "$CONN"
install -m 644 "$HERE/files/airbox-captive-dns.conf" \
    /etc/NetworkManager/dnsmasq-shared.d/airbox-captive.conf

log "Setting Wi-Fi country to $COUNTRY"
raspi-config nonint do_wifi_country "$COUNTRY" || true
CMDLINE=/boot/firmware/cmdline.txt
if [ -f "$CMDLINE" ] && ! grep -q "cfg80211.ieee80211_regdom=$COUNTRY" "$CMDLINE"; then
    sed -i -e 's/\s*cfg80211.ieee80211_regdom=\S*//' -e "s/\$/ cfg80211.ieee80211_regdom=$COUNTRY/" "$CMDLINE"
fi

# ---------------------------------------------------------------- hostname --
log "Setting hostname to $HOST"
echo "$HOST" > /etc/hostname
if grep -q '^127\.0\.1\.1' /etc/hosts; then
    sed -i -E "s/^127\.0\.1\.1\s.*/127.0.1.1\t$HOST/" /etc/hosts
else
    printf '127.0.1.1\t%s\n' "$HOST" >> /etc/hosts
fi

systemctl enable nginx "php${PHPV}-fpm" NetworkManager avahi-daemon

if [ "$IMAGE_BUILD" != 1 ]; then
    hostnamectl set-hostname "$HOST" || true
    systemctl restart "php${PHPV}-fpm" nginx
    cat <<EOF

Done. Reboot to start the $SSID hotspot:  sudo reboot
After the reboot wlan0 is the hotspot, so this Pi stops using Wi-Fi to reach
the internet or your home network. Reach it at http://air.box on the hotspot,
or over Ethernet at http://$HOST.local.
EOF
fi
