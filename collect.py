import json, subprocess, time, os, platform, datetime

BASE = "/srv/magi"
WWW = os.path.join(BASE, "www")
# 実環境の値（IP・ホスト名・鍵ファイル名）は collect-config.json に分離（Git管理外）
CFG = {}
_cfg_path = os.path.join(BASE, "collect-config.json")
if os.path.exists(_cfg_path):
    with open(_cfg_path) as f:
        CFG = json.load(f)
TOKEN = open(os.path.join(BASE, "token.txt")).read().strip()
KEY = os.path.join(BASE, CFG.get("ssh_key", "collector_key"))
OCI01 = CFG.get("oci01", "192.0.2.11")
OCI02 = CFG.get("oci02", "192.0.2.12")
A1_HOST = CFG.get("a1_host", "a1.example.invalid")
A1_USER = CFG.get("a1_user", "ubuntu")
A1_PORT = int(CFG.get("a1_port", 25000))

def parse_dump(text):
    d = {}
    for line in text.strip().splitlines():
        if "=" in line:
            k, v = line.split("=", 1)
            d[k.strip()] = v.strip()
    return d

def local_dump():
    out = []
    out.append("H=" + platform.node())
    with open("/proc/uptime") as f:
        out.append("U=" + f.read().split()[0].split(".")[0])
    with open("/proc/loadavg") as f:
        out.append("L=" + " ".join(f.read().split()[:3]))
    out.append("C=" + str(os.cpu_count()))
    cm = ""
    try:
        with open("/proc/cpuinfo") as f:
            for line in f:
                if line.lower().startswith("model name") and ":" in line:
                    cm = line.split(":", 1)[1].strip()
                    break
    except Exception:
        pass
    if not cm:
        try:
            r = subprocess.run(["lscpu"], capture_output=True, text=True, timeout=5,
                               env=dict(os.environ, LC_ALL="C"))
            for line in r.stdout.splitlines():
                if line.startswith("Model name:"):
                    cm = line.split(":", 1)[1].strip()
                    break
        except Exception:
            pass
    out.append("CM=" + cm)
    mem = {}
    with open("/proc/meminfo") as f:
        for line in f:
            p = line.split()
            if p[0] in ("MemTotal:", "MemAvailable:", "SwapTotal:", "SwapFree:"):
                mem[p[0]] = int(p[1]) // 1024
    out.append("MT=%d" % mem.get("MemTotal:", 0))
    out.append("MU=%d" % (mem.get("MemTotal:", 0) - mem.get("MemAvailable:", 0)))
    out.append("ST=%d" % mem.get("SwapTotal:", 0))
    out.append("SU=%d" % (mem.get("SwapTotal:", 0) - mem.get("SwapFree:", 0)))
    df = subprocess.run(["df", "-m", "/"], capture_output=True, text=True).stdout.splitlines()[1].split()
    out.append("DT=%d" % int(df[1]))
    out.append("DU=%d" % int(df[2]))
    out.append("DP=%s" % df[4].rstrip("%"))
    osr = {}
    with open("/etc/os-release") as f:
        for line in f:
            if "=" in line:
                k, v = line.strip().split("=", 1)
                osr[k] = v.strip('"')
    out.append("OS=" + osr.get("PRETTY_NAME", ""))
    out.append("K=" + platform.release())
    try:
        dk = subprocess.run(["sudo", "-n", "docker", "ps", "--format", "{{.Names}}|{{.Status}}"],
                            capture_output=True, text=True, timeout=10).stdout.strip().replace("\n", "; ")
    except Exception:
        dk = ""
    out.append("X=" + dk)
    return parse_dump("\n".join(out))

REMOTE_CMD = (
    'echo "H=$(hostname)"; '
    'echo "U=$(cut -d. -f1 /proc/uptime)"; '
    'echo "L=$(cut -d\' \' -f1-3 /proc/loadavg)"; '
    'echo "C=$(nproc)"; '
    'echo "CM=$( (grep -m1 \'model name\' /proc/cpuinfo || LC_ALL=C lscpu | grep -m1 \'Model name\') | cut -d: -f2 | sed \'s/^ *//\')"; '
    'free -m | awk \'/^Mem:/{print "MT="$2"\\nMU="$3}\'; '
    'free -m | awk \'/^Swap:/{print "ST="$2"\\nSU="$3}\'; '
    'df -m / | awk \'NR==2{gsub(/%/,"",$5); print "DT="$2"\\nDU="$3"\\nDP="$5}\'; '
    'echo "OS=$(. /etc/os-release; echo $PRETTY_NAME)"; '
    'echo "K=$(uname -r)"'
)

def remote_dump(host, user="Muse", port=22):
    r = subprocess.run(["ssh", "-i", KEY, "-o", "IdentitiesOnly=yes", "-o", "BatchMode=yes",
                        "-o", "StrictHostKeyChecking=accept-new", "-o", "ConnectTimeout=8",
                        "-p", str(port), "%s@%s" % (user, host), REMOTE_CMD],
                       capture_output=True, text=True, timeout=25)
    if r.returncode != 0:
        return None
    return parse_dump(r.stdout)

def unit(uid, name, addr, role, d):
    if d is None:
        return {"id": uid, "name": name, "addr": addr, "role": role, "status": "unreachable"}
    return {
        "id": uid, "name": name, "addr": addr, "role": role, "status": "ok",
        "host": uid, "os": d.get("OS", ""), "kernel": d.get("K", ""),
        "cpu": int(d.get("C", "0")), "cpu_name": d.get("CM", ""),
        "load": [float(x) for x in d.get("L", "0 0 0").split()],
        "mem_total_mb": int(d.get("MT", "0")), "mem_used_mb": int(d.get("MU", "0")),
        "swap_total_mb": int(d.get("ST", "0")), "swap_used_mb": int(d.get("SU", "0")),
        "disk_total_mb": int(d.get("DT", "0")), "disk_used_mb": int(d.get("DU", "0")),
        "disk_pct": int(d.get("DP", "0")), "uptime_sec": int(d.get("U", "0") or 0),
        "extra": d.get("X", ""),
    }

def push_to_oci01(local_path):
    url = "http://%s/push-%s" % (OCI01, TOKEN)
    r = subprocess.run(["curl", "-s", "-o", "/dev/null", "-w", "%{http_code}",
                        "--connect-timeout", "8", "--max-time", "20",
                        "-X", "POST", "--data-binary", "@" + local_path, url],
                       capture_output=True, text=True, timeout=30)
    if r.stdout.strip() != "200":
        print("push http failed: code=%s err=%s" % (r.stdout.strip(), r.stderr.strip()[:150]), flush=True)

while True:
    try:
        units = [
            unit("MELCHIOR-1", "MELCHIOR-1", "", "Virtual DSM ホスト", local_dump()),
            unit("BALTHASAR-2", "BALTHASAR-2", "", "クラウド x86", remote_dump(OCI01)),
            unit("CASPER-3", "CASPER-3", "", "クラウド x86", remote_dump(OCI02)),
            unit("ARTABAN-4", "ARTABAN-4", "", "Nextcloud ／ 本ページ配信",
                 remote_dump(A1_HOST, A1_USER, A1_PORT)),
        ]
        payload = {
            "collected_at": datetime.datetime.now(
                datetime.timezone(datetime.timedelta(hours=9))).isoformat(timespec="seconds"),
            "units": units,
        }
        final = os.path.join(WWW, "status-%s.json" % TOKEN)
        tmp = os.path.join(WWW, ".status.tmp")
        with open(tmp, "w") as f:
            json.dump(payload, f, ensure_ascii=False)
        os.replace(tmp, final)
        try:
            push_to_oci01(final)
        except Exception as e:
            print("push error:", e, flush=True)
    except Exception as e:
        print("collect error:", e, flush=True)
    time.sleep(30)
