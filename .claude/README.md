# `.claude/` w repo klienta — bramki i role (2.09.2026)

Sesje Claude Code uruchamiane w tym katalogu (koordynatorka, subagenci, recenzent w CI) do 2.09 nie miały ŻADNYCH hooków —
bramki warsztatu działają tylko w cwd warsztatu. Ten katalog daje minimum:

- `hooks/zbrojenie-subagenta.py` (SubagentStart) — każdy subagent dostaje na starcie: zasady dowodu, ścieżki skilli,
  rejestr klas `audyt/SCIAGA-EGZAMINATORA.md`, briefing recenzenta, `DECYZJE.md`.
- `hooks/zbrojenie-guard.py` (PreToolUse: Agent | Workflow | Bash z `tmux send-keys`) — zadanie dla innego agenta bez
  ścieżki do skilla/dokumentu/rejestru jest LOGOWANE (`~/.claude/log-hookow.jsonl`); tryb blokady po `~/.claude/zbrojenie-guard.BLOCK`.
- `hooks/lib-log.sh` — pomocnik logowania.
- `agents/audytor.md`, `agents/builder.md` — role subagentów (`Agent(subagent_type: "audytor" | "builder")`).

⛔ Pliki hooków to KOPIE z `/root/praca/warsztat/.claude/hooks/` — zmiany robi się w warsztacie i kopiuje tu.
Hooki nic nie zapisują w repo, nie wołają sieci, przy błędzie kończą kodem 0 (nie blokują pracy). Nie wchodzą do paczek
wtyczek (`boty/paczki/*.zip` budowane z `wtyczki/`).
