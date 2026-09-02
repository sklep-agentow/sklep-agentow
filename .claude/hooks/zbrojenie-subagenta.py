#!/usr/bin/env python3
"""ZBROJENIE SUBAGENTA — SubagentStart, matcher `*`. Wstrzykuje KRÓTKI, STAŁY kontekst każdemu subagentowi (Fala 2b, 2.09.2026).
Docs (hooks.md → SubagentStart): dostaje `agent_type`, „can't block subagent creation, but can inject context” przez
`hookSpecificOutput.additionalContext`. Ładunek celowo mały i stały (stabilny prefiks = cache; krytycy 2.09: „minimalne, nie wszystko”).
Powód: klasa błędu „zadanie dla innej sesji bez skilli/dokumentów/rejestru” — właściciel przypominał ≥ 25×/14 dni.
Ważne do: 2026-10-02 (metryka: liczba zadań subagentów, które zwróciły „CZEGO NIE POTWIERDZIŁAM” / odwołały się do skilla ze ścieżki).
"""
import sys, json, os
# KOPIA z warsztatu (/root/praca/warsztat/.claude/hooks/, 2.09.2026) do repo klienta — zmieniaj TAM, potem kopiuj tu.
try:
    d = json.load(sys.stdin)
except Exception:
    sys.exit(0)
typ = d.get("agent_type") or "?"
cwd = d.get("cwd") or os.getcwd()
W = "/root/praca/warsztat"
linie = [
    "ZBROJENIE WARSZTATU (wstrzyknięte automatycznie, SubagentStart):",
    "• Jesteś częścią warsztatu Gosi (asystentka, żeńskie końcówki: sprawdziłam/zrobiłam). Nie zgaduj: każde twierdzenie z [ŹRÓDŁO: plik:linia|URL] albo [OPINIA]; brak źródła = „NIE WIEM”.",
    "• Na końcu ZAWSZE sekcja „CZEGO NIE POTWIERDZIŁAM” (koordynatorka traktuje ją jako listę zadań). Wynik > 1 strony → do pliku, zwróć ścieżkę + ≤ 15 linii.",
    "• Grep z zerem trafień wymaga próby kontrolnej (fraza, która MUSI trafić). Liczby tylko z uruchomienia, nie z opisu.",
    f"• Dokumentacja Anthropic: NIE z pamięci — https://code.claude.com/docs/llms.txt (Claude Code), https://platform.claude.com/llms.txt (API). Kopii lokalnej nie ma.",
    f"• Skille warsztatu (czytaj narzędziem do plików, pełną ścieżką): {W}/.claude/skills/<nazwa>/SKILL.md — mapa: {W}/MAPA-ETAPOW.md. Audyt: jak-audytowac (+ references/lekcje.md), wydania: github-wydania-klienta, krytyka planu: krytyka-planu.",
]
sciaga = os.path.join(cwd, "audyt", "SCIAGA-EGZAMINATORA.md")
if os.path.exists(sciaga):
    linie.append(f"• REJESTR KLAS BŁĘDÓW tego repo: {sciaga} — czytaj PRZED pisaniem kodu; zasady oceny: {os.path.join(cwd, '.github', 'RECENZENT-BRIEFING.md')}, decyzje: {os.path.join(cwd, 'DECYZJE.md')}.")
if typ in ("Explore", "Plan", "claude-code-guide"):
    linie = linie[:5]  # czytacze: bez listy skilli budowy
tekst = "\n".join(linie)
print(json.dumps({"hookSpecificOutput": {"hookEventName": "SubagentStart", "additionalContext": tekst}}, ensure_ascii=False))
sys.exit(0)
