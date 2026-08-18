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

# 3.2.9
- Korrektur der Symfony-Routenreihenfolge und des Pfads fuer den Controller-Endpunkt resetStatus zur Vermeidung von UUID-Routenkonfliktfehlern.

# 3.3.0
- Erweiterte Freshdesk-Duplikatkontakthandhabung mit Fallback-E-Mail-Suche.

# 3.3.1
- CLI-Synchronisierungsbefehl aktualisiert, um formatierte Echtzeit-Fortschrittszeilen auszugeben und Sync-Zaehler zu speichern.

# 3.3.2
- Echtzeit-Zaehleraktualisierungen in der Schleife und dynamische Verbleibend-Berechnung implementiert.

# 3.3.3
- Dynamische laenderbasierte Freshdesk-Tag-Zuweisung hinzugefuegt: Rechnungsadressen aus der Schweiz (CH) und Liechtenstein (LI) erhalten das Tag "Webshop-CH", alle anderen Laender "Webshop-EU".

# 3.3.4
- Konfigurierbare Mehrfachauswahl-Einstellung fuer Länder für Webshop-CH Tag (chWebshopCountries) in der Plugin-Administration hinzugefuegt, mit Fallback auf die Standardpruefung von Schweiz und Liechtenstein.

# 3.3.5
- Bezeichnung der Laender-Mehrfachauswahl in "Zugeordnete Länder für Kontakt-Tag" umbenannt, um die vertriebskanalspezifische dynamische Tag-Zuordnung korrekt widerzuspiegeln.

# 3.3.6
- Event-Handler fuer Kundenregistrierung und Double-Opt-in in Try-Catch-Bloecke eingeschlossen, um Abstuerze bei Registrierung und Gast-Checkout im Storefront bei Freshdesk-API-Ausnahmen zu verhindern.
- Explizites handles-Attribut in services.xml und getHandledMessages()-Methode in FreshdeskCustomerSyncTaskHandler hinzugefuegt, um NoHandlerForMessageException-Fehler im Symfony Messenger fuer geplante Tasks zu beheben.

# 3.3.7
- Pruefung auf eine Mindestlaenge von 5 Zeichen fuer Telefonnummern in FreshdeskService hinzugefuegt, um zu kurze Telefonnummern auszulassen und HTTP 400-Validierungsfehler der Freshdesk-API zu vermeiden.

# 3.3.8
- DBAL-Abfrage in findMatchingSalesChannelIdForCountry() auf SELECT LOWER(HEX(sales_channel_id)) AS sales_channel_id aktualisiert, um ungueltige binaere UUID-Validierungsfehler beim Abrufen der Vertriebskanalkonfiguration zu verhindern.

# 3.3.9
- Konfigurierbare Fehler-E-Mail-Benachrichtigungsfunktion (enableErrorEmail und errorEmailAddress) hinzugefuegt, um Echtzeit-E-Mail-Benachrichtigungen mit vollstaendigen Fehler-Stack-Traces zu senden, wenn ein Freshdesk-API-Synchronisierungsfehler oder eine Ausnahme auftritt.












