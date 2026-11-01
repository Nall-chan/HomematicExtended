# Aktionen für Homematic-Geräte

Die Library stellt Aktionen für Instanzen des Symcon-Moduls `HomeMatic Device` bereit.  
Sie ergänzen Datenpunkte, die nur geschrieben werden können und deshalb keine Statusvariable besitzen (z.B. `ON_TIME`, `RAMP_TIME`), sowie Datenpunkte vom Typ Aktion (z.B. `STOP`).  
Die Aktionen stehen überall dort zur Verfügung, wo Symcon Aktionen anbietet (Ereignis, Ablaufplan, Wochenplan, Skripteditor mit „Befehl hinzufügen“, „Befehle testen“ einer Instanz).  

Eine Aktion wird nur für Instanzen angeboten, deren Statusvariablen zum Gerätetyp passen (Restriktion `hasIdent` bzw. `hasNoIdent`).  
Da Homematic-Rollläden und -Dimmer teils dieselben Statusvariablen besitzen, kann eine Dimmer-Aktion auch bei einem Rollladen angeboten werden und umgekehrt. Der Aufruf bleibt dann ohne Wirkung am Gerät.  

Die Aktionen verwenden die Funktionen `HM_WriteValueFloat` und `HM_WriteValueBoolean` des Symcon-Moduls. Die Zeitwerte werden vom Gerät zusammen mit dem nächsten Schaltbefehl übernommen, deshalb schreibt jede Aktion zuerst die Zeit und dann den Wert.  

## Übersicht

| Aktion | Datenpunkte | Angeboten bei Instanzen mit den Variablen (Ident) | Parameter |
|---|---|---|---|
| Einschalten mit Einschaltdauer | `ON_TIME`, `STATE` | `STATE`, `INHIBIT`, `WORKING` (Schaltaktoren) | Dauer in Sekunden |
| Dimmen mit Einschaltdauer | `ON_TIME`, `LEVEL` | `LEVEL`, `DIRECTION`, `INHIBIT`, `WORKING` (Dimmer) | Wert in %, Dauer in Sekunden |
| Dimmen mit Rampenzeit | `RAMP_TIME`, `LEVEL` | wie oben | Wert in %, Rampenzeit in Sekunden |
| Dimmen mit Rampenzeit und Einschaltdauer | `RAMP_TIME`, `ON_TIME`, `LEVEL` | wie oben | Wert in %, Rampenzeit, Dauer in Sekunden |
| Dimmen stoppen | `RAMP_STOP` | wie oben | – |
| Setze den Aktor auf den vorherigen Wert | `OLD_LEVEL` | wie oben | – |
| Rollladen oder Jalousie anhalten | `STOP` | `LEVEL`, `DIRECTION`, `INHIBIT`, `WORKING`, aber keine Dimmer-Variablen (`LEVEL_REAL`, `ERROR`, `ERROR_OVERHEAT`, `ERROR_OVERLOAD`, `ERROR_REDUCED`) | – |
| Fensterantrieb anhalten | `STOP` | `STATE_UNCERTAIN`, `LEVEL`, `DIRECTION`, `INHIBIT`, `WORKING` (HM-Sec-Win, WDF solar) | – |
| Fenster mit Geschwindigkeit fahren | `SPEED`, `LEVEL` | wie oben | Wert in %, Geschwindigkeit in % |
| Türschloss öffnen | `OPEN` | `STATE`, `STATE_UNCERTAIN`, `ERROR`, `DIRECTION`, `INHIBIT` (HM-Sec-Key) | – |
| Türschloss mit Wiederverriegelungsverzögerung öffnen | `RELOCK_DELAY`, `OPEN` | wie oben | Verzögerung in Sekunden |

Quelle der Datenpunkte, Bereiche und Einheiten sind die Gerätebeschreibungen der CCU-Firmware (OCCU, `rftypes`).  

## Tasten der Zentrale

| Aktion | Datenpunkte | Angeboten bei Instanzen mit den Variablen (Ident) | Parameter |
|---|---|---|---|
| Wert mit virtueller Taste senden | `LEVEL` | `PRESS_SHORT`, `PRESS_LONG` (virtuelle Tasten `BidCoS-RF:n`, HM-RCV-50) | Wert in % |

Das Drücken der Tasten (`PRESS_SHORT`, `PRESS_LONG`) ist als Statusvariable mit Aktion vorhanden. `LEVEL` kann nur geschrieben werden und sendet den Wert an die verknüpften Aktoren. Die Aktion wird auch bei echten Tastern angeboten, dort meldet die Funktion einen Fehler.  

## Fernbedienung mit Display

| Aktion | Datenpunkte | Angeboten bei | Parameter |
|---|---|---|---|
| Auf dem Display anzeigen | `TEXT`, `UNIT`, `BEEP`, `BACKLIGHT`, `ALARM_COUNT`, `SERVICE_COUNT`, Symbole (`BULB`, `SWITCH`, `WINDOW`, `DOOR`, `BLIND`, `SCENE`, `PHONE`, `BELL`, `CLOCK`, `ARROW_UP`, `ARROW_DOWN`), `SUBMIT` | Instanz des Kanals `DISPLAY` (Kanal 18) der HM-RC-19, HM-RC-19-B und HM-RC-19-SW | Text, Einheit (keine, %, W, °C, °F), Signalton (keiner, 1 bis 3), Hintergrundlicht (aus, an, langsam und schnell blinkend), Alarm- und Servicemeldungen (0 bis 255), Symbole |

Die Datenpunkte des Displays sind alle nur schreibbar, die Instanz des Kanals hat deshalb keine Statusvariablen, an denen Symcon sie erkennen könnte. Die Aktion wird daher bei jeder Instanz des Homematic-Moduls angeboten. Das Formular prüft beim Öffnen anhand der Beschreibung der Datenpunkte, ob das Ziel ein Display-Kanal ist, und zeigt sonst nur einen Hinweis statt der Eingabefelder. Beim Ausführen wird erneut geprüft (sonst Meldung, ohne zu schreiben). Der Text hat 5 Zeichen, mit Punkt oder Komma 6 Zeichen (längere Texte werden abgeschnitten), reine Zahlen stehen rechtsbündig. Alle Werte werden geschrieben und danach mit `SUBMIT` an das Display übertragen. Die Reihenfolge der Werte stammt aus der Gerätebeschreibung der CCU und einem bewährten Script, die Aktion ist nicht an einem Gerät getestet.

## LED-Anzeige HM-OU-LED16

| Aktion | Datenpunkte | Angeboten bei Instanzen mit den Variablen (Ident) | Parameter |
|---|---|---|---|
| Alle LEDs setzen | `ALL_LEDS` | `LED_STATUS`, `PRESS_SHORT` (Tastenkanäle der HM-OU-LED16, z.B. Kanal 1) | Farbe je LED 1 bis 16 (aus, rot, grün, orange) |

Die 16 LEDs werden in einem Telegramm gesetzt. `ALL_LEDS` erwartet eine Dezimalzahl als Text: LED n liegt auf den Bits 2(n-1) und 2(n-1)+1, 0 = aus, 1 = rot, 2 = grün, 3 = orange (z.B. `35` = LED 1 orange, LED 3 grün; alle LEDs orange = `4294967295`). Hexadezimale Werte (`0x…`) setzen falsche LEDs. Am Gerät geprüft. Das Gerät kann bei `ALL_LEDS` keine LED auslassen, jedes Telegramm enthält alle 16 Farben. Die Auswahl `Unverändert` gibt es deshalb nur für LEDs, deren Kanal als Instanz in Symcon vorhanden ist (die Aktion liest den aktuellen Zustand aus der Variable `LED_STATUS`, nachdem sie ihn von der CCU abgefragt hat). Fehlt die Instanz, steht nur Aus, Rot, Grün und Orange zur Verfügung. Die Instanzen aller Kanäle legt der Konfigurator von Symcon (`HomeMatic Konfigurator`) an. Einzelne LEDs lassen sich mit der Statusvariable `LED_STATUS` des jeweiligen Kanals (0 bis 3) schalten, der Schlafmodus `LED_SLEEP_MODE` kommt in der Firmware des Testgerätes nicht vor. Der Datenpunkt `LED_STATUS` des Kanals 0 (Wartung) setzt ebenfalls alle LEDs mit demselben Wert, hat aber keine Statusvariable.

## HomeMatic IP

Grundlage sind die Parameterlisten aus der Technischen Dokumentation der Homematic-IP-Geräte (`docs/hmip-device-documentation_1.pdf`) und den CCU-Quellen (OCCU). Geprüft ist bisher nur „Einschalten mit Einschaltdauer“ an einem HmIP-Schaltaktor (ON_TIME 10 s, danach schaltet das Gerät von selbst aus), die übrigen Aktionen sind nicht an Geräten getestet.  
Für HomeMatic IP laufen Zeiten über die Datenpunkte `ON_TIME` und `RAMP_TIME` (Sekunden, 0 bis 8580000) oder über `DURATION_VALUE` + `DURATION_UNIT` und `RAMP_TIME_VALUE` + `RAMP_TIME_UNIT` (Wert 0 bis 16343, Einheit 0 = Sekunden, 1 = Minuten, 2 = Stunden, 3 = 10 Millisekunden).  

| Aktion | Datenpunkte | Angeboten bei Instanzen mit den Variablen (Ident) | Parameter |
|---|---|---|---|
| HomeMatic IP: Einschalten mit Einschaltdauer | `ON_TIME`, `STATE` | `STATE`, aber nicht `INHIBIT`, `WORKING`, `LEVEL` (Schaltaktoren) | Dauer in Sekunden |
| HomeMatic IP: Dimmen mit Rampenzeit und Einschaltdauer | `RAMP_TIME`, `ON_TIME`, `LEVEL` | `LEVEL`, `ACTIVITY_STATE`, `PROCESS`, `SECTION`, aber nicht `LEVEL_2`, `COLOR`, `HUE`, `COLOR_TEMPERATURE`, `SOUNDFILE` (Dimmer) | Wert in %, Rampenzeit, Dauer in Sekunden |
| HomeMatic IP: Farbleuchte mit Rampenzeit und Dauer dimmen | `RAMP_TIME_*`, `DURATION_*`, `LEVEL` | wie Dimmer, zusätzlich `COLOR` (HmIP-BSL) | Wert in %, Rampenzeit und Dauer mit Einheit |
| HomeMatic IP: Universal-Lichtaktor mit Rampenzeit und Dauer dimmen | `RAMP_TIME_*`, `DURATION_*`, `LEVEL` | `LEVEL`, `ACTIVITY_STATE`, `HUE`, `SATURATION` (HmIP-RGBW, HmIP-LSC, DRG-DALI) | wie oben |
| HomeMatic IP: Rollladen oder Jalousie anhalten | `STOP` | `LEVEL`, `ACTIVITY_STATE`, `PROCESS`, `SECTION` | – |
| HomeMatic IP: Alarm auslösen | `DURATION_UNIT`, `DURATION_VALUE`, `OPTICAL_ALARM_SELECTION`, `ACOUSTIC_ALARM_SELECTION` | `ACOUSTIC_ALARM_ACTIVE`, `OPTICAL_ALARM_ACTIVE` (HmIP-ASIR) | optisches und akustisches Signal, Dauer mit Einheit |
| HomeMatic IP: Garagentor-Befehl | `DOOR_COMMAND` | `DOOR_STATE` (HmIP-MOD-HO) | Öffnen, Stopp, Schließen, Teilweise öffnen |
| HomeMatic IP: Türschloss-Befehl | `LOCK_TARGET_LEVEL` | `LOCK_STATE` (HmIP-DLD) | Verriegelt, Entriegelt, Öffnen |
| HomeMatic IP: Bewegungserkennung zurücksetzen | `RESET_MOTION` | `MOTION`, `MOTION_DETECTION_ACTIVE` (HmIP-SMI, -SMO) | – |
| HomeMatic IP: Präsenzerkennung zurücksetzen | `RESET_PRESENCE` | `PRESENCE_DETECTION_STATE` (HmIP-SPI) | – |
| HomeMatic IP: Wochenprogramm-Modus einstellen | `WEEK_PROGRAM_TARGET_CHANNEL_LOCK`, `WEEK_PROGRAM_TARGET_CHANNEL_LOCKS` | `WEEK_PROGRAM_CHANNEL_LOCKS` (Kanal „Wochenprofil“, z.B. Kanal 6 der HmIP-Schaltaktoren) | Modus (manuell, automatisch ohne/mit Zurücksetzen), virtuelle Kanäle 1 bis 8 |

Hinweise:  

* Wochenprogramm-Modus: Die Kanalauswahl entspricht dem Dialog „Modus einstellen“ der CCU. Der virtuelle Kanal 1 ist der erste Schaltkanal des Geräts (bei einem Schaltaktor mit den Kanälen 3 bis 5 ist Kanal 3 der virtuelle Kanal 1). Jeder Kanal belegt 3 Bit des Werts (1, 8, 64 …). Geprüft am Testschaltaktor: Der Wert von `WEEK_PROGRAM_CHANNEL_LOCKS` steigt mit „Manuell“ je Kanal um 1, 8 bzw. 64 und fällt mit „Automatisch“ wieder.  
* Rollläden und Dimmer haben bei HomeMatic IP dieselben Statusvariablen. Die Aktion „Rollladen anhalten“ erscheint deshalb auch bei Dimmern und „Dimmen“ auch bei Rollläden. Beim falschen Gerät meldet die Funktion einen Fehler.  
* Die Auswahllisten von Alarm, Garagentor und Türschloss entsprechen der Reihenfolge der Werte in der Gerätedokumentation (Alarm akustisch 0 bis 17, optisch 0 bis 7, Garagentor 0 = keine Aktion, 1 bis 4, Türschloss 0 bis 2).  
* Zeiten werden vor dem Schaltwert geschrieben und vom Gerät mit dem nächsten Schaltbefehl übernommen. Die CCU behält die Zeit bei HomeMatic IP bis zum nächsten Schaltbefehl (am HmIP-Schaltaktor bestätigt).  

## Noch nicht umgesetzt

* HM-Sec-Sir-WM, Kanal 4 (Scharfschaltung): Wirkung von `ON_TIME` ist noch ungeprüft.
* HM-LC-RGBW-WM, HM-OU-LED16 und weitere Geräte mit komplexen Parametern: Umsetzung noch offen.
* HomeMatic IP Rauchmelder (`SMOKE_DETECTOR_COMMAND`), Selbstkalibrierung, Display (HmIP-WRCD), Lüftungssteuerung und Heizungsgeräte: Die Heizungsgeräte deckt das Modul Heating Device ab, für die anderen fehlen verlässliche Wertetabellen.
