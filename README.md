[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.43-blue.svg)]()
[![Version](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-4.3%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-4-3-%28Stable%29-Changelog)

# IPSHomeMaticExtended
Erweitert IPS um die native Unterstützung:  

* Systemvariablen der CCU
* Programmen auf der CCU
* Summenzähler aller Typen von Verbrauchsmesser
* Display Status-Anzeige (Dis-WM55)
* ePaper Status-Anzeige (Dis-EP-WM55)
* HomeMaticScript
* Status der Funk-Interfaces
* Status des Wired-Interface

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation) 
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Anhang](#5-anhang)  
    1. [GUID der Module](#1-guid-der-module)
    2. [Changlog](#2-changlog)
    3. [Spenden](#3-spenden)
6. [Lizenz](#6-lizenz)

## 1. Funktionsumfang

### [HomeMatic Systemvariablen:](Systemvariablen/)  

   Abfragen von System- und Alarmvariablen inkl. Profilen und Werten von der CCU.  
   Schreiben von Werten der Systemvariablen zur CCU.  
   Standard Actionhandler für die Bedienung der System- und Alarmvariablen aus dem IPS-Webfront.  
   Die Alarmvariablen werden erst ab IPS 4.3 untersützt.  

### [HomeMatic Powermeter:](PowerMeter/)  
   Abfragen des Summenzählers der Geräte mit Leistungsmessung aus der CCU.  
   Ab IPS 4.3 werden alle Geräte unterstützt, bei IPS 3.x nur Geräte mit dem Datenpunk 'ENERGY_COUNTER'.  

### [HomeMatic Programme:](Programme/):  
   Abfragen der auf der CCU vorhandenen HM-Programme.  
   Ausführen der HM-Programme auf der CCU.  
   Standard Actionhandler für die Bedienung der HM-Programme aus dem IPS-Webfront.  

### [HomeMatic RemoteScript Interface:](HomeMaticScript)  
   Native Schnittstelle zur CCU, um HomeMatic-Scripte durch die CCU ausführen zu lassen.  
   Direkte Rückmeldung der Ausführung durch einen Antwortstring im JSON-Format.  

### [HomeMatic Dis-WM55:](DisplayStatusAnzeige/)
   Dynamische Textanzeige auf dem Display-Wandtaster mit Statusdisplay.  
   Unterstützt mehrseite Anzeigen und das durchblättern per Tastendruck.  
   Ausführen von benutzerspezifischen Aktionen, auch in Abhängigkeit der angezeigten Seite.  

### [HomeMatic Dis-EP-WM55:](ePaperStatusAnzeige/) (ab IPS 4.3)  
   Hier handelt es sich um eine Instanz, welche die Verwendung des ePaper Statusdisplays im 55er-Rahmen vereinfachen soll.  
   Über spezielle PHP-Befehle ist es möglich das Display anzusteuern.  
   
### [HomeMatic RF-Interface Splitter:](RFInterfaceSplitter/) (ab IPS 4.3)  
   Auslesen der Informationen zu jedem Funk-Interface der CCU.

### [HomeMatic RF-Interface:](RFInterface/) (ab IPS 4.3)  
   Bereitstellen der Informationen zu den Funk-Interfaces innerhalb von IPS.

### [HomeMatic WR-Interface:](WRInterface/) (ab IPS 4.3)  
   Bereitstellen der Informationen zu dem Wired-Interface innerhalb von IPS.

### Besonderheiten
   XML-API-Patch wird nicht benötigt.  
   Unterstützung von mehreren CCUs.  
   Einfache Einrichtung und Handhabung.  
   PHP-Befehle entsprechen dem vorhandenen Standard von IPS.  
 
## 2. Voraussetzungen

   Funktionsfähige CCU1 und/oder CCU2, welche schon mit einem HomeMatic Socket in IPS eingerichtet ist.  
   In der CCU muß die Firewall entsprechend eingerichtet sein, das IPS auf die 'Remote HomeMatic-Script API' der CCU zugreifen kann.  

    Einstellungen -> Systemsteuerung -> Firewall

   Bei 'Remote HomeMatic-Script API' muß entweder 'Vollzugriff' oder 'Eingeschränkt' eingestellt sein.
   Bei 'Eingeschränkt' ist dann unter 'IP-Adressen für eingeschränkten Zugriff' euer LAN / IPS-PC einzugeben.  
   (z.B. 192.168.178.0/24 => /24 ist die Subnet-Maske für das Netzwerk. Bei 255.255.255.0 ist das 24 bei 255.255.0.0. ist es 16.  
   Oder es kann direkt eine einzelne Adresse eingetragen werden. z.B. 192.168.0.2  

![Doku/CCUFirewall.png](docs/CCUFirewall.png)  


## 3. Software-Installation

### IPS 3.x  
        Kopieren von der HMSysVar.dll in das Unterverzeichniss 'modules' unterhalb des IP-Symcon Installationsverzeichnisses.  
        Der Ordner 'modules' muss u.U. manuell angelegt werden.
        Beispiel: 'C:\\IP-Symcon\\modules'  
        IPS-Dienst Neustarten.  

### IPS 4.3  oder neuer
        Über das 'Module-Control' folgende URL hinzufügen:  
        `git://github.com/Nall-chan/IPSHomematicExtended.git`  

## 4. Einrichten der Instanzen in IP-Symcon

  Ist direkt in der Dokumentation der jeweiligen Module beschrieben.  
  Die Module der Geräte sind im Diaog 'Instanz hinzufügen' unter dem Hersteller 'HomeMatic' zu finden.  
  **Bei kommerzieller Nutzung (z.B. als Errichter oder Integrator) wenden Sie sich bitte an den Autor.**  

![Instanzen](docs/HMExtendedInstanzen.png)  


## 5. Anhang

###  1. GUID der Module

| Modul                            | Typ      | GUID                                   |
| :------------------------------: | :------: | :------------------------------------: |
| HomeMatic Systemvariablen        | Device   | {400F9193-FE79-4086-8D76-958BF9C1B357} |
| HomeMatic Powermeter             | Device   | {AF50C42B-7183-4992-B04A-FAFB07BB1B90} |
| HomeMatic Programme              | Device   | {A5010577-C443-4A85-ABF2-3F2D6CDD2465} |
| HomeMatic RemoteScript Interface | Device   | {246EDB89-70BC-403B-A1FA-3B3B1B540401} |
| HomeMatic Dis-WM55               | Device   | {271BCAB1-0658-46D9-A164-985AEB641B48} |
| HomeMatic Dis-EP-WM55            | Device   | {E64ED916-FA6C-45B2-B8E3-EDC3191BC4C0} |
| HomeMatic RF-Interface Splitter  | Splitter | {6EE35B5B-9DD9-4B23-89F6-37589134852F} |
| HomeMatic RF-Interface           | Device   | {36549B96-FA11-4651-8662-F310EEEC5C7D} |
| HomeMatic WR-Interface           | Device   | {01C66202-7E94-49C4-8D8F-6A75CE944E87} |


### 2. Changlog

Version 2.43:  
 Fix: Für IPS 5.0  

Version 2.42:  
 Fix: RF-Splitter hat beim anlegen von RF-Interface Instanzen der CCU1 Fehler gemeldet.  
 Fix: Icon ON/OFF vertauscht in der Display-Statusanzeige.  

Version 2.40:  
 Neu: Übersetztungen für IPS 4.3  
 Neu: Doku überarbeitet  
 Fix: Systemvariablen vom Typ Float konnten falsch übertragen werden.  

Version 2.35:  
 Fix: Dis-EP-WM55 hat nur Icons von 0-3 angenommen.  

Version 2.31:  
 Fix: Fehlerbehandlung verbessert.  

Version 2.30:  
 Fix: Fehlerbehandlung verbessert.  
 Fix: Eventuelle XML-Fehler durch die CCU versucht abzufangen.  

Version 2.22:  
 Fix: HomeMatic Remote-Script Instanzen belegten unnötig PHP-Slots.  
 Fix: Fehlermeldung im HomeMatic Systemvariablen durch eine falsche Fehlermeldung wurde ein eigentlicher Fehler überdeckt.  

Version 2.20:  
 Neu: Dis-EP-WM55 Ermöglicht es per PHP die Anzeige zu beschreiben.
 Neu: Doku für HomeMatic WR-Interface ergänzt.
 
Version 2.10:  
 Neu: HomeMatic WR-Interface zeigt den Status des Wired-Interfaces der CCU an.  
 Neu: Alle 'CONNECTED' Statusvariablen der CCU-Interfaces werden immer aktualisiert um Ausfälle besser detektieren zu können.  
 Fix: Instanzen haben nicht erkannt wenn sich der Parent geändert hat.  
 Fix: Timer erzeugen keine Fehlermeldungen mehr.  
 
Version 2.07:  
 Fix: Summenzähler für Powermeter hat bei GAS falsche Werte geliefert.  
 Fix: Dis-WM55 ohne Funktion.  
 Fix: Dis-WM55 hat immer das Display-Script überschrieben.  
 New: Mehr Debug-Ausgaben bei Dis-WM55.  

Version 2.06:  
 Fix: Doku geändert (Final).  
 Fix: GUID für Empfang vom RF-Interface Splitter.  
 Fix: Trigger für Powermeter und Systemvariablen waren unter Umständen falsch.  
 Fix: HM-Systemvariablen vom Typ String wurden falsch dargestellt, wenn Umlaute enthalten waren.  
 
Version 2.05:  
 Fix: Unter Umständen konnte die Adresse der CCU nicht aus dem HomeMatic-Socket ermitteln werden.  
 New: Beschreibung in module.json ergänzt.

Version 2.04:  
 Fix: RFInstance-Splitter hat fehler gemeldet beim Anlegen von Instanzen, wenn keine vorhanden waren.  

Version 2.03:  
 Fix: Doku geändert (Teil1).

Version 2.02:  
 Fix: Powermeter-Instanz kann jetzt auch mit allen Varianten von HM-ES-TX-WM umgehen.  
 Fix: Powermeter-Instanz unterstützt jetzt auch HMIP-PSM und ähnliche HMIP-'Mess-Steckdosen'  

Version 2.01:  
 Neu: RF-Interface-Splitter zum auslesen der RF-Interfaces aus der CCU.  
 Neu: RF-Interface zum darstellen der Werte eines RF-Interfaces der CCU.  

Version 2.0:  

Version 1.5:  

Version 1.3:  

Version 1.1:  

### 3. Spenden  
  
  Die Library ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Autor werden hier akzeptiert:  

<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=G2SLW2MEMQZH2" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>

## 6. Lizenz

  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
