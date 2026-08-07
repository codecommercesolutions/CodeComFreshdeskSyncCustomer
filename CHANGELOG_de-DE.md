# 1.0.0
- Erste Version des Freshdesk-Synchronisierung von Kundendaten für Shopware 6.5

# 2.0.0
- Erste Version des Freshdesk-Synchronisierung von Kundendaten für Shopware 6.6

# 3.0.0
- Erste Version des Freshdesk-Synchronisierung von Kundendaten für Shopware 6.7

# 3.2.0
- Konfigurierbarer Kunden-Uebertragungsmodus fuer die Synchronisierung aller Kunden oder nur Kunden mit Optin hinzugefuegt.
- Geplante Kunden-Batch-Synchronisierung mit Cron-Fortschritt und Statusanzeige hinzugefuegt.
- Manuellen CLI-Befehl fuer die Kundensynchronisierung hinzugefuegt.
- Administrationsfunktionen im Kundendetail zum Speichern des Optins und manuellen Synchronisieren eines Kunden mit Freshdesk hinzugefuegt.
- Shopware Kunden-Tags werden als Freshdesk Kontakt-Tags synchronisiert und Optin-Informationen im konfigurierten Freshdesk Custom Field beibehalten.

# 3.2.1
- Vertriebskanal-spezifischer contactTag aus der Plugin-Konfiguration wird nun zu den Freshdesk-Tags hinzugefuegt.
- Zusatzfeld freshdesk_api_response im Freshdesk-Set auf der Kunden-Entity hinzugefuegt, um API-Fehler oder Erfolg ("customer synced suceesfully in freshdesk") bei CLI-Befehlen und Cron-Laeufen zu protokollieren.
- Oeffentliche Log-Datei freshdesk.log (public/freshdesk.log) zur Erfassung fehlgeschlagener Kunden-API-Synchronisierungen mit Kunden-ID bei CLI-Befehlen und Cron-Laeufen hinzugefuegt.

# 3.2.2
- Neuer CLI-Befehl codecom:freshdesk:reset-sync-status hinzugefuegt, um alle Kunden-Sync-Status-Flags zurueckzusetzen und die Datei public/freshdesk.log zu leeren.

# 3.2.3
- Protokollierung der API-Antwort im Zusatzfeld freshdesk_api_response und vertriebskanalbasierter contactTag fuer das manuelle Ausloesen der Kundensynchronisierung im Administrationsbereich aktiviert.

# 3.2.4
- Konfigurationsschalter enableEventLog unter den API-Verbindungseinstellungen hinzugefuegt, um System-Event-Log-Eintraege (Tabelle log_entry) zu verwalten.

# 3.2.5
- Handhabung von markProcessed aktualisiert, um den Zeitstempel processed_at fuer alle verarbeiteten Kunden (einschliesslich fehlgeschlagener Versuche) bei Batch-Synchronisierungen zu setzen.

# 3.2.6
- Automatische Extraktion von user_id und erneuter Kontaktaktualisierungsversuch hinzugefuegt, wenn die Freshdesk-API den Validierungsfehler HTTP 400 duplicate_value zurueckgibt.

# 3.2.7
- Befehl reset-sync-status aktualisiert, um auch alle kumulativen Gesamtzaehler in der Plugin-Konfiguration auf 0 zurueckzusetzen.

# 3.2.8
- Schaltflaechenkomponente und API-Aktions-Endpunkt zum Zuruecksetzen des Sync-Status in der Erweiterungskonfiguration hinzugefuegt.








