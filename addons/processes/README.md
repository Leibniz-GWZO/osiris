# Add-on „Vorgänge“ (processes)

Lokales Add-on für einfache Vorgänge mit Freigaben oder Phasen. Es läuft
neben dem OSIRIS-Kern und ist über das Feature `processes` schaltbar.

- Definitionen (JSON) werden aus dem Verzeichnis in der Umgebungsvariable
  `OSIRIS_PROCESS_DEFINITIONS` geladen, ohne Angabe aus `definitions/` im
  Add-on. Sie sind nicht Teil dieses Repositorys.
- Daten: Collections mit Präfix `proc_`, Dateien im GridFS-Bucket `proc_files`.
- Einstiegspunkte im Kern: `$GLOBALS['OSIRIS_ADDON_HOOKS']` (Seitenleiste,
  Aufgaben, Digest).
- `/processes/check` zeigt Administrator*innen die geladenen Definitionen und
  Fehler darin.
