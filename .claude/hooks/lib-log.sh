#!/usr/bin/env bash
# lib-log.sh — pomocnik logowania BLOKAD z bramek PreToolUse (Fala 2, 2.09.2026).
# Użycie w bramce, tuż przed `exit 2`:   bash "$(dirname "$0")/lib-log.sh" "<nazwa-hooka>" "<powód ≤ 120 zn>" "<klucz: komenda/ścieżka>"
# Pisze jedną linię JSON do ~/.claude/log-hookow.jsonl (ten sam plik co log-zdarzen.py). Nigdy nie psuje bramki: błędy połykane, exit 0.
H="${1:-?}"; P="${2:-}"; K="${3:-}"; S="${4:-}"; Z="${5:-BLOKADA}"   # $5 (od 2f): rodzaj zdarzenia (BLOKADA|POPRAWKA); $4 (od 2e): session_id ze stdin hooka — CLAUDE_SESSION_ID w env hooków jest PUSTE (zmierzone 2.09: 19/19 blokad bez sesji)
P="$(printf '%s' "$P" | tr '\n' ' ' | cut -c1-120)"; K="$(printf '%s' "$K" | tr '\n' ' ' | cut -c1-120)"
python3 - "$H" "$P" "$K" "$S" "$Z" <<'PY' 2>/dev/null || true
import sys, json, os, time
h, p, k, s, z = sys.argv[1:6]
rec = {"ts": time.strftime("%Y-%m-%dT%H:%M:%S"), "sesja": (s or os.environ.get("CLAUDE_SESSION_ID", ""))[:8], "zdarzenie": z or "BLOKADA",
       "narzedzie": h, "ok": False, "blad": p, "klucz": k, "cwd": os.path.basename(os.getcwd())}
with open(os.environ.get("HOOK_LOG") or os.path.expanduser("~/.claude/log-hookow.jsonl"), "a", encoding="utf-8") as f:   # HOOK_LOG: proby kontrolne podkladaja wlasny log
    f.write(json.dumps(rec, ensure_ascii=False) + "\n")
PY
exit 0
