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


