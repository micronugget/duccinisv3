# Self-Hosted VPN: Remote Terminal Access via Android SSH

Pi-hole + WireGuard + Unbound — with Android-to-Ubuntu SSH over VPN

*Last updated: 2026 | Tested on Ubuntu 24.04 LTS, Pi-hole v6, WireGuard (kernel module), Unbound 1.19.3*

---

## Introduction

This guide sets up a self-hosted VPN server combining network-level ad blocking, privacy-focused DNS resolution, and secure remote access. The primary goal is **SSH access from an Android device to an Ubuntu 24.04 PC** over an encrypted WireGuard tunnel — letting you control your Ubuntu terminal from your phone, from anywhere.

### What You'll Build

A cloud VPS running:

- **Pi-hole** — network-wide ad and tracker blocking
- **Unbound** — recursive DNS resolver for enhanced privacy
- **WireGuard** — modern, fast VPN protocol

With two clients connected:

- **Ubuntu 24.04 PC** — accessible via SSH from within the VPN
- **Android device** — SSH client connecting to the Ubuntu PC

### Network Topology

```
Android (10.6.0.3)
       |
       |  WireGuard VPN
       v
Cloud VPS / WireGuard Server (10.6.0.1)
       |
       |  WireGuard VPN
       v
Ubuntu PC (10.6.0.2)
```

Android SSHes to `10.6.0.2` (Ubuntu's WireGuard IP). All traffic is encrypted. Pi-hole filters DNS for all connected devices.

---

## Prerequisites

- Basic Linux command line knowledge
- A cloud hosting account (recommendations below)
- Ubuntu 24.04 PC with SSH server installed (`openssh-server`)
- Android device with WireGuard app + Termux (or another SSH client)
- SSH client on your local machine for VPS setup
- ~€5–10/month for VPS hosting

**VPS minimum requirements:** 1GB RAM, 1 vCPU, 10GB storage (2GB RAM recommended)

---

## Choosing a Cloud Provider

Any VPS provider running Ubuntu 24.04 LTS works. Current options in the €3–10/month range include Hetzner, DigitalOcean, Linode, and Vultr. Choose a data centre geographically close to you for lower latency.

**Note on streaming services:** Most cloud IP ranges are blocked by Netflix, HBO Max, etc. This setup is focused on security, privacy, and remote access — not geo-unblocking.

---

## Initial Server Setup

### Create Your Server

1. Create a new Ubuntu 24.04 LTS VPS instance
2. Choose a nearby data centre
3. Add your SSH public key during creation
4. Note the public IP address

### First Login and Updates

```bash
ssh root@your-server-ip
apt update && apt upgrade -y
apt autoremove -y
reboot
```

Reconnect after ~30 seconds.

### Configure Firewall

```bash
apt install -y ufw

ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp
ufw allow 51820/udp
ufw enable
ufw status verbose
```

### Install Fail2Ban

```bash
apt install -y fail2ban
systemctl enable fail2ban
systemctl start fail2ban
```

### Enable Automatic Security Updates

```bash
apt install -y unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades
```

Select **Yes** when prompted.

### Create a Non-Root User (Recommended)

```bash
adduser username
usermod -aG sudo username
rsync --archive --chown=username:username ~/.ssh /home/username
```

Test SSH login as the new user before proceeding.

---

## Installing Pi-hole

### Installation

```bash
apt install -y curl
curl -sSL https://install.pi-hole.net | bash
```

### Installation Wizard

1. **Upstream DNS Provider** — choose any (Cloudflare or Google); you'll replace this with Unbound shortly
2. **Blocklists** — accept defaults
3. **Admin Interface** — Yes
4. **Web Server** — Pi-hole v6 uses a built-in web server; lighttpd is no longer offered
5. **Query Logging** — enable if you want to monitor DNS requests
6. **Privacy Mode** — your preference

Note the admin password displayed at the end.

### Set Admin Password

The `-a -p` flag was removed in Pi-hole v6. Use:

```bash
pihole setpassword
```

### Access the Admin Interface

```
http://your-server-ip/admin
```

---

## Installing Unbound

### Installation

```bash
apt install -y unbound
```

### Configuration

```bash
nano /etc/unbound/unbound.conf.d/pi-hole.conf
```

Paste:

```
server:
    verbosity: 0
    interface: 127.0.0.1
    port: 5335
    do-ip4: yes
    do-udp: yes
    do-tcp: yes
    do-ip6: no

    num-threads: 2
    msg-cache-slabs: 2
    rrset-cache-slabs: 2
    infra-cache-slabs: 2
    key-cache-slabs: 2

    rrset-cache-size: 256m
    msg-cache-size: 128m

    so-rcvbuf: 1m
    so-sndbuf: 1m

    prefetch: yes
    prefetch-key: yes
    aggressive-nsec: yes

    qname-minimisation: yes
    hide-identity: yes
    hide-version: yes

    harden-glue: yes
    harden-dnssec-stripped: yes
    harden-below-nxdomain: yes
    harden-referral-path: yes
    use-caps-for-id: yes

    access-control: 127.0.0.1/32 allow
    access-control: 0.0.0.0/0 refuse

    root-hints: "/var/lib/unbound/root.hints"

    # NOTE: Do not add auto-trust-anchor-file here — already configured
    # in /etc/unbound/unbound.conf.d/root-auto-trust-anchor-file.conf

    logfile: ""
```

### Download Root Hints

```bash
wget -O /var/lib/unbound/root.hints https://www.internic.net/domain/named.root
```

### Setup Trust Anchor

```bash
apt install -y unbound-anchor
rm -f /var/lib/unbound/root.key
unbound-anchor -a /var/lib/unbound/root.key
chown unbound:unbound /var/lib/unbound/root.key
chmod 644 /var/lib/unbound/root.key
```

Verify no duplicate trust anchor definitions:

```bash
grep -r "auto-trust-anchor-file" /etc/unbound/
```

It should only appear in `root-auto-trust-anchor-file.conf`. If it also appears in `pi-hole.conf`, remove it from there.

### Start Unbound

```bash
unbound-checkconf
systemctl restart unbound
systemctl enable unbound
systemctl status unbound
```

### Test Unbound

```bash
dig @127.0.0.1 -p 5335 google.com
```

You should see a successful DNS response.

### Point Pi-hole at Unbound

1. Open Pi-hole admin → **Settings > DNS**
2. Uncheck all upstream DNS servers
3. In **Custom 1 (IPv4)** enter: `127.0.0.1#5335`
4. Click **Save**

---

## Installing WireGuard with PiVPN

### Installation

```bash
curl -L https://install.pivpn.io | bash
```

### Installation Wizard

1. **Static IP Warning** — acknowledge
2. **User Selection** — create a user (e.g. `vpnuser`) if prompted
3. **Unattended Upgrades** — Yes
4. **VPN Protocol** — select **WireGuard**
5. **Port** — `51820` (default)
6. **DNS Provider** — select **Pi-hole**
7. **Public IP** — confirm your VPS public IP
8. **Reboot** — allow reboot

### After Reboot

```bash
pivpn list
```

Should show "No clients found."

---

## Enable Client-to-Client Routing

By default PiVPN only routes traffic between clients and the server — not between clients. You need this so Android can SSH to your Ubuntu PC.

### Enable IP Forwarding

```bash
sysctl net.ipv4.ip_forward
```

If this returns `0`:

```bash
echo "net.ipv4.ip_forward=1" >> /etc/sysctl.conf
sysctl -p
```

### Add iptables Forwarding Rules

Edit the WireGuard server config:

```bash
nano /etc/wireguard/wg0.conf
```

Add these lines in the `[Interface]` section:

```
PostUp = iptables -A FORWARD -i wg0 -j ACCEPT
PostDown = iptables -D FORWARD -i wg0 -j ACCEPT
```

Restart WireGuard:

```bash
systemctl restart wg-quick@wg0
```

---

## Creating Client Profiles

### Ubuntu PC Profile

```bash
pivpn add -n ubuntu-pc
```

### Android Profile

```bash
pivpn add -n android
```

### List All Profiles

```bash
pivpn list
```

---

## Connecting the Ubuntu PC

### Install WireGuard

On Ubuntu 24.04, `openresolv` is not available — it is not needed as `systemd-resolved` handles DNS natively:

```bash
sudo apt install -y wireguard
```

### Get the Config File

If `scp` hangs (a known issue with some server configurations), use this instead:

```bash
ssh root@your-server-ip 'cat /home/vpnuser/configs/ubuntu-pc.conf' > ~/ubuntu-pc.conf
```

Verify the file was created before proceeding:

```bash
cat ~/ubuntu-pc.conf
```

Move it into place:

```bash
sudo mv ~/ubuntu-pc.conf /etc/wireguard/
```

### Update AllowedIPs

Open the config:

```bash
sudo nano /etc/wireguard/ubuntu-pc.conf
```

The default `AllowedIPs = 0.0.0.0/0, ::/0` routes all traffic through the VPN. This works fine for client-to-client routing and keeps all traffic filtered by Pi-hole. No change is required unless you want split tunnelling.

### Connect

```bash
sudo wg-quick up ubuntu-pc
```

Disconnect:

```bash
sudo wg-quick down ubuntu-pc
```

### Add to NetworkManager for GUI Control

Importing the profile into NetworkManager gives you a persistent VPN toggle in the Ubuntu desktop's top-right network menu — visible and clickable whether the VPN is active or not.

```bash
sudo nmcli connection import type wireguard file /etc/wireguard/ubuntu-pc.conf
```

Bring it up once to confirm it works:

```bash
nmcli connection up ubuntu-pc
```

Disable the `wg-quick` systemd service to avoid conflicts:

```bash
sudo systemctl disable wg-quick@ubuntu-pc
```

From this point, use the network menu in the top-right corner of the desktop to connect and disconnect manually.

Ensure auto-connect is **disabled** so the VPN does not start automatically on boot:

```bash
nmcli connection modify ubuntu-pc connection.autoconnect no
```

**Note:** Due to a bug in NetworkManager 1.46.0 (Ubuntu 24.04), importing a WireGuard config while the interface is already active causes NM to crash. Always bring the interface down with `sudo wg-quick down ubuntu-pc` before importing or re-importing the profile.

### Ensure SSH Server is Running on Ubuntu PC

```bash
sudo apt install -y openssh-server
sudo systemctl enable ssh
sudo systemctl start ssh
```

---

## Connecting Android

### Install WireGuard App

Install **WireGuard** from the Google Play Store.

### Get the Config

Generate a QR code on the VPS:

```bash
pivpn -qr android
```

In the WireGuard app:

1. Tap **+**
2. Select **Create from QR code**
3. Scan the QR code
4. Name the tunnel (e.g. "Home VPN")
5. Toggle **ON**

### Update AllowedIPs (Optional)

The default `0.0.0.0/0` routes everything through the VPN. This is fine and recommended for Pi-hole filtering. If you prefer split tunnelling, edit the profile in the app and change AllowedIPs to `10.6.0.0/24`.

---

## SSH from Android to Ubuntu PC

### Install Termux

Install **Termux** from F-Droid (recommended over Play Store for up-to-date packages).

```bash
pkg update && pkg install openssh
```

### Find Your Ubuntu PC's WireGuard IP

On the VPS:

```bash
wg show
```

Look for the peer corresponding to `ubuntu-pc` — its `allowed ips` value (e.g. `10.6.0.2/32`) is its VPN IP.

### Connect

In Termux, with the WireGuard VPN active on Android:

```bash
ssh lee@10.6.0.2
```

You should get a shell on your Ubuntu PC.

### Save the Connection (Optional)

```bash
nano ~/.ssh/config
```

Add:

```
Host ubuntu-pc
    HostName 10.6.0.2
    User lee
```

Then connect with just:

```bash
ssh ubuntu-pc
```

---

## Verification

### Confirm VPN is Working (Android)

In Termux:

```bash
curl ifconfig.me
```

This should return your VPS's IP, not your local mobile IP.

### Confirm Pi-hole is Filtering

```bash
nslookup doubleclick.net
```

This ad domain should resolve to `0.0.0.0`.

### Check Pi-hole Logs

Open the Pi-hole admin interface and go to **Tools > Query Log**. You should see queries from both `10.6.0.2` (Ubuntu) and `10.6.0.3` (Android).

---

## Security Hardening

### Restrict Pi-hole Admin to VPN Only

Pi-hole v6 uses a built-in web server with a self-signed certificate — no additional HTTPS configuration is needed. To restrict the admin interface to VPN-connected clients only, edit `pihole.toml`:

```bash
nano /etc/pihole/pihole.toml
```

Set the address to listen only on the WireGuard interface:

```
[webserver]
address = "10.6.0.1:80"
```

Replace `10.6.0.1` with your VPS's WireGuard IP (check with `wg show`). Restart Pi-hole FTL:

```bash
systemctl restart pihole-FTL
```

The admin interface is now only reachable from within the VPN. Close port 80 in UFW:

```bash
ufw delete allow 80/tcp
ufw reload
```


### Harden SSH on VPS

```bash
nano /etc/ssh/sshd_config
```

Set:

```
PasswordAuthentication no
PermitRootLogin no
```

```bash
systemctl restart sshd
```

**Only do this after confirming key-based SSH login works.**

---

## Maintenance

### Update Pi-hole

```bash
pihole -up
```

### Update Gravity (Blocklists)

```bash
pihole -g
```

### Update Root Hints (Quarterly)

```bash
wget -O /var/lib/unbound/root.hints https://www.internic.net/domain/named.root
systemctl restart unbound
```

### Monitor WireGuard Connections

```bash
wg show
pivpn -c
```

---

## Troubleshooting

### scp Hangs When Downloading Config Files

`scp` can hang due to server firewall or SFTP subsystem issues. Use `cat` over SSH instead:

```bash
ssh root@your-server-ip 'cat /home/vpnuser/configs/ubuntu-pc.conf' > ~/ubuntu-pc.conf
```

Verify the file exists before running `mv`:

```bash
cat ~/ubuntu-pc.conf
```

### openresolv Not Available on Ubuntu 24.04

`openresolv` was removed from Ubuntu 24.04 repositories. It is not needed — install WireGuard without it:

```bash
sudo apt install -y wireguard
```

### Android SSH Cannot Reach Ubuntu PC

1. Confirm both devices show as peers with `wg show` on the VPS
2. Confirm client-to-client iptables rules are in place (see above)
3. Confirm SSH server is running on Ubuntu: `systemctl status ssh`
4. Confirm Ubuntu's WireGuard tunnel is up: `sudo wg show`
5. Try pinging Ubuntu from Android in Termux: `ping 10.6.0.2`

### Pi-hole Password Command Changed (v6)

`pihole -a -p` was removed in Pi-hole v6. Use:

```bash
pihole setpassword
```

---

## Resources

- [Pi-hole Documentation](https://docs.pi-hole.net/)
- [WireGuard Documentation](https://www.wireguard.com/)
- [PiVPN Documentation](https://docs.pivpn.io/)
- [Unbound Documentation](https://nlnetlabs.nl/documentation/unbound/)
- [Termux on F-Droid](https://f-droid.org/packages/com.termux/)
- [Pi-hole recursive DNS setup guide](https://docs.pi-hole.net/guides/dns/unbound/)
