#!/usr/bin/env python3
"""ZBROJENIE-GUARD — PreToolUse na Agent | Workflow | Bash(tmux send-keys). Fala 2c, 2.09.2026.
Klasa: zadanie dla innej sesji/agenta BEZ skilli, dokumentów, rejestru (≥ 25 przypomnień właściciela / 14 dni; 1.09 dwa razy).
Sprawdza, czy DŁUŻSZE zadanie (prompt ≥ 300 zn. / send-keys ≥ 150 zn. / skrypt Workflow) niesie choć jedno „gdzie sięgnąć po wiedzę”:
ścieżkę .md, `SKILL.md`, `/skills/`, URL docs, nazwę rejestru/briefingu. Sprawdza też, czy podane ŚCIEŻKI ISTNIEJĄ (bliźniak 1.09: skill nazwą bez ścieżki).
TRYB: LOG (domyślny do 2026-09-04 — krytycy: „48 h loguj, nie blokuj przed egzaminem”) → wpis BLOKADA-LOG, exit 0.
      BLOCK: gdy istnieje plik ~/.claude/zbrojenie-guard.BLOCK → exit 2. Przepustka: touch ~/.claude/zgoda-bez-zbrojenia (60 s).
Ważne do: 2026-10-02 (metryka: wpisy BLOKADA-LOG/tydzień → 0 = nawyk; fałszywe trafienia → poprawić wzorce).
"""
import sys, json, os, re, time, subprocess
# KOPIA z warsztatu (/root/praca/warsztat/.claude/hooks/, 2.09.2026) do repo klienta — zmieniaj TAM, potem kopiuj tu.
try:
    d = json.load(sys.stdin)
except Exception:
    sys.exit(0)
tn = d.get("tool_name", ""); ti = d.get("tool_input") or {}
H = os.path.dirname(os.path.abspath(__file__))
if tn == "Agent":
    tekst = ti.get("prompt", "") or ""; prog = 300; kanal = "Agent"
elif tn == "Workflow":
    tekst = ti.get("script", "") or ""; prog = 1; kanal = "Workflow"
    if not tekst and ti.get("scriptPath") and os.path.exists(ti["scriptPath"]): tekst = open(ti["scriptPath"], encoding="utf-8", errors="ignore").read()
elif tn == "Bash":
    cmd = ti.get("command", "") or ""
    if not re.search(r"tmux\s+send-keys", cmd): sys.exit(0)
    tekst = cmd; prog = 150; kanal = "send-keys"
else:
    sys.exit(0)
if len(tekst) < prog: sys.exit(0)
# 1) czy jest gdzie sięgnąć po wiedzę
ma_wiedze = bool(re.search(r"SKILL\.md|/skills/|\.claude/agents/|https?://(code|platform)\.claude\.com|llms\.txt|SCIAGA-EGZAMINATORA|RECENZENT-BRIEFING|BRIEFING|DECYZJE\.md|lekcje\.md|[\w./-]+\.md\b", tekst))
# 2) czy podane ścieżki absolutne do .md/.sh/.py istnieją
brakujace = []
for p in set(re.findall(r"(/root/[\w./-]+?\.(?:md|sh|py|json))", tekst)):
    p = p.rstrip(".,;:)")
    if not os.path.exists(p): brakujace.append(p)
problemy = []
if not ma_wiedze: problemy.append("brak skilla/dokumentu/rejestru (ścieżka .md, SKILL.md, URL docs)")
if brakujace: problemy.append("ścieżki NIE ISTNIEJĄ: " + ", ".join(brakujace[:3]))
if not problemy: sys.exit(0)
powod = f"{kanal}: " + "; ".join(problemy)
subprocess.run(["bash", os.path.join(H, "lib-log.sh"), "zbrojenie-guard.py", ("BLOKADA-LOG " if not os.path.exists(os.path.expanduser("~/.claude/zbrojenie-guard.BLOCK")) else "") + powod, tekst[:120]], check=False)
Z = os.path.expanduser("~/.claude/zgoda-bez-zbrojenia")
if os.path.exists(Z) and time.time() - os.path.getmtime(Z) <= 60: sys.exit(0)
if not os.path.exists(os.path.expanduser("~/.claude/zbrojenie-guard.BLOCK")):
    # TRYB LOG: nie blokuj, ale powiedz Claude'owi, co brakuje (additionalContext dla PreToolUse nie ma — stderr przy exit 0 jest niewidoczny; zapis do logu wystarczy)
    sys.exit(0)
sys.stderr.write(f"⛔ ZBROJENIE-GUARD: zadanie dla {kanal} bez zbrojenia — {powod}.\nDopisz: ścieżkę do SKILL.md (warsztat: /root/praca/warsztat/.claude/skills/<nazwa>/SKILL.md), dokument oceny (RECENZENT-BRIEFING / DECYZJE) i rejestr klas (audyt/SCIAGA-EGZAMINATORA.md) z numerami. Ścieżki muszą istnieć. Przepustka: touch ~/.claude/zgoda-bez-zbrojenia (60 s).\n")
sys.exit(2)
