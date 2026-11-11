# Air_Box: offline Raspberry Pi streaming device

This folder turns a Raspberry Pi into a self-contained, offline air.box. Phones join its **Air_Box** Wi-Fi, air.box opens on its own as a sign-in page (captive portal), and anyone can browse to **http://air.box**. No internet connection is needed or used.

It's the official Raspberry Pi OS Lite (64-bit) with these additions:

- **nginx + PHP** serving the app from `/var/www/air.box`
- **Wi-Fi hotspot** `Air_Box`: an open network, with the Pi at `10.0.0.1`
- **Captive portal**: every web address resolves to the Pi, and anything that isn't air.box is redirected to `http://air.box/`
- **Rotana Bot**: a chat assistant that answers questions about Rotana Jet from a PDF you provide (with its photos), fully offline
- **Hostname** `airbox`: on a wired network the box is at `http://airbox.local`

**Hardware:** any Raspberry Pi with built-in Wi-Fi that runs 64-bit Pi OS: Pi 3B / 3B+ / 4 / 5 / 400 / 500 / Zero 2 W. Use a microSD card of at least 8 GB; the library is stored on it, so size it for your movies.

## 1. Build the image (Windows)

1. Install and start [Docker Desktop](https://www.docker.com/products/docker-desktop/).
2. Double-click **`deploy\raspberry-pi\build-image.bat`**.

It takes roughly 15–30 minutes and about 6 GB of disk space. The first run also downloads Pi OS (about 550 MB) and keeps it for later builds. When it finishes, `dist\` contains:

- `airbox-pi-<date>.img.xz`: the image to flash
- `airbox-credentials.txt`: the web admin and SSH passwords generated for this image. **Keep it private.** The hotspot is an open network, so the passwords are all that stop guests from changing the library.

### Optional settings

Set any of these in a Command Prompt, then run `build-image.bat` from that same window:

```
set AIRBOX_WIFI_PASSWORD=secret123
deploy\raspberry-pi\build-image.bat
```

| Variable | Default | Meaning |
|---|---|---|
| `AIRBOX_SSID` | `Air_Box` | Wi-Fi name |
| `AIRBOX_WIFI_PASSWORD` | *(none)* | 8+ characters makes it a WPA2 network. Leave it empty for an open network, which is what captive portals expect. |
| `AIRBOX_COUNTRY` | `AE` | Wi-Fi country code (`GB`, `US`, `SA`, …). Must match where the box is used. |
| `AIRBOX_CHANNEL` | `6` | 2.4 GHz channel, 1–11 |
| `AIRBOX_HOSTNAME` | `airbox` | Device name, used for `http://<name>.local` |
| `AIRBOX_ADMIN_PASSWORD` | generated | Web admin password |
| `AIRBOX_SSH_PASSWORD` | generated | Password for the `airbox` login |

## 2. Flash it

1. Open [Raspberry Pi Imager](https://www.raspberrypi.com/software/).
2. Under **Choose OS**, pick **Use custom** and select the `.img.xz` from `dist\`.
3. Choose your microSD card and write it.
4. If Imager offers **OS customisation**, decline it. The image already has its login, hostname and Wi-Fi settings, and customisation would replace them.

## 3. First boot

Insert the card and power on. The first boot takes a couple of minutes: the Pi expands the filesystem to fill the card and applies the login. After that, the **Air_Box** network appears.

## 4. Using it

**Guests:** join the **Air_Box** Wi-Fi. A sign-in page opens showing air.box, and movies, books and music play right there. Guests can also open a browser and go to **http://air.box**.

- **iPhone/iPad:** if you close the sign-in page, choose **Use Without Internet** to stay connected, then use Safari.
- **Android:** if the phone says the network has no internet, choose to stay connected. With mobile data on, Android may send browser traffic over mobile data instead of Air_Box, so turn mobile data off (or use airplane mode with Wi-Fi on).
- Ordinary websites (for example `https://google.com`) won't load. The box is offline; only air.box is available.

**Adding content:** go to **http://air.box/admin/login.php** and log in as `admin` with the password from `airbox-credentials.txt`. Upload movies (MP4 with H.264 video plays on practically every phone; up to 1 GB each), books (PDF) and music. The first boot includes a few demo titles; select them in the admin lists and delete them in bulk. Large movies upload faster over a cable: plug the Pi into your router and use **http://airbox.local/admin/login.php** from a computer on that network.

**Rotana Bot:** in the admin panel open **Rotana Bot**, choose your PDF (an answer sheet, brochure, manual or slide deck) and click **Import**. Its text and photos are learned, and caution/warning notices are left out. The chat button appears on every page once it's imported. See the main README for how the bot answers.

**SSH:** `ssh airbox@10.0.0.1` on the hotspot, or `ssh airbox@airbox.local` over Ethernet. The password is in `airbox-credentials.txt`.

### Changing Wi-Fi settings on a running box

Over SSH:

```bash
sudo nmcli connection modify airbox-hotspot wifi.ssid "New_Name"
# add a password (8+ characters):
sudo nmcli connection modify airbox-hotspot wifi-sec.key-mgmt wpa-psk wifi-sec.psk "secret123"
sudo nmcli connection up airbox-hotspot
```

## Installing on a Pi that's already set up

If you'd rather start from your own Raspberry Pi OS Lite (64-bit, trixie) install, connect it to the internet over Ethernet, copy this repository onto it, and run:

```bash
sudo bash deploy/raspberry-pi/setup-airbox.sh
sudo reboot
```

It uses the same settings variables as the image build, e.g. `sudo AIRBOX_COUNTRY=GB bash deploy/raspberry-pi/setup-airbox.sh`. After the reboot the Wi-Fi chip becomes the hotspot, so the Pi stops using Wi-Fi for internet.

## Updating a box that's already in use

To put a newer version of the app on a box you've already flashed, without rebuilding the image or losing its library (movies, books, music and Rotana Bot's answers stay as they are), join the **Air_Box** Wi-Fi from your PC and run this in a Command Prompt in the project folder. No internet is needed.

```
tar --exclude=./dist --exclude=./.git -czf airbox-update.tgz .
scp airbox-update.tgz airbox@10.0.0.1:
ssh -t airbox@10.0.0.1 "rm -rf air.box && mkdir air.box && tar -xzf airbox-update.tgz -C air.box && sudo bash air.box/deploy/raspberry-pi/setup-airbox.sh"
```

Each step asks for the SSH password from `airbox-credentials.txt`. If you built the box with custom settings (Wi-Fi name, password, country), pass them again, e.g. `sudo AIRBOX_COUNTRY=GB bash …`; otherwise the hotspot settings go back to the defaults. The new hotspot settings take effect after `sudo reboot`; the app update takes effect immediately.

## How it works

| Piece | File on the Pi | Source here |
|---|---|---|
| Hotspot (NetworkManager, DHCP + DNS for clients) | `/etc/NetworkManager/system-connections/airbox-hotspot.nmconnection` | written by `setup-airbox.sh` |
| Every hostname → `10.0.0.1` | `/etc/NetworkManager/dnsmasq-shared.d/airbox-captive.conf` | `files/airbox-captive-dns.conf` |
| air.box site + redirect everything else to it | `/etc/nginx/sites-available/airbox` | `files/nginx-airbox.conf` |
| Upload limits | `/etc/php/*/fpm/conf.d/99-airbox.ini` | `../../php.ini` |

When a phone joins, it checks for internet by fetching a known address (for example `captive.apple.com` or `connectivitycheck.gstatic.com`). DNS sends that request to the Pi, and nginx answers with a redirect to `http://air.box/`. The phone takes that as a sign-in page and opens it.

Video, audio and PDFs are sent by nginx itself (`X-Accel-Redirect`) after the app approves the request. This keeps PHP free for page loads and stops large files from being copied to temporary files on the SD card while they stream.

## Limits

- **Number of viewers:** the Pi's built-in Wi-Fi is designed for a small number of devices, and Raspberry Pi doesn't publish a maximum. Before relying on it, test with as many phones as you expect to stream at once.
- **Clock:** most Pis have no battery-backed clock, so without internet the date can be wrong. This doesn't affect streaming.
