[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20version-3.12-blue.svg)]()
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-5.1%20%3E-green.svg)](https://www.symcon.de/forum/threads/30857-IP-Symcon-5-1-%28Stable%29-Changelog)
[![Check Style](https://github.com/Nall-chan/HomematicExtended/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions) [![Run Tests](https://github.com/Nall-chan/HomematicExtended/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)  
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](../README.md#6-spenden) 

# HomeMatic Dis-WM55 <!-- omit in toc -->
   Erweitert IPS um die native Unterstützung der DisplayStatusAnzeige Dis-WM55

## Dokumentation <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Installation](#2-installation)
- [3. Einrichten der Instanzen in IP-Symcon](#3-einrichten-der-instanzen-in-ip-symcon)
- [4. Statusvariablen und Profile](#4-statusvariablen-und-profile)
- [5. PHP-Befehlsreferenz](#5-php-befehlsreferenz)
- [6. Lizenz](#6-lizenz)

## 1. Funktionsumfang

   Dynamische Textanzeige auf dem Display-Wandtaster mit Statusdisplay.  
   Unterstützt mehrseite Anzeigen und das durchblättern per Tastendruck.  
   Ausführen von benutzerspezifischen Aktionen, auch in Abhängigkeit der angezeigten Seite.  



## 2. Installation

Dieses Modul ist Bestandteil der [HomeMaticExtended-Library](../).  


## 3. Einrichten der Instanzen in IP-Symcon

   Hier handelt es sich um eine Instanz, welche die Verwendung des farbigen Statusdisplays im 55er-Rahmen vereinfachen soll.  
   Über eine konfigurierbare Anzahl von 'Seiten' ist es möglich verschiedene Inhalte darzustellen und durch diese zu blättern (z.B. mit den beiden Tasten der Statusanzeige).  
   Für die darzustellenden Inhalte muss das unterhalb der Instanz erzeugt Display-Script den eigenen Bedürfnissen angepasst werden.  
   Grundsätzlich ist die Statusanzeige nur empfangsbereit, und stellt eine Inhalt auf dem Display dar, wenn unmittelbar zuvor eine der beiden Tasten gedrückt wurde.  
   Hierzu ist wenigstens eine der vier Felder "Hoch-Taste", "Runter-Taste", "Aktion Hoch-Taste" oder "Aktion Runter-Taste" mit einem der PRESS Datenpunkte der Statusanzeige zu belegen.  
   Wird von IPS ein Telegramm mit einem der vier Datenpunkte empfange, so wird das "Display-Script" mit den entsprechenden Parametern ausgeführt und das Ergebnis anschließend zur Statusanzeige übertragen.  
   Die Anzahl der möglichen Seiten lässt sich in der Konfiguration der Instanz einstellen (1 ist auch möglich).  
   Ebenso ist das Timeout einstellbar, nach wieviel Sekunden wieder auf Seite 1 gesprungen wird.  

![Instanzen](../docs/HMExtendedInstanzen.png)  
   Unter Instanz hinzufügen ist das Gerät 'HomeMatic WM55-Dis' unter dem Hersteller 'HomeMatic' zu finden.  
   Nach dem Anlegen der Instanz sollte als übergeordnetes Gerät schon der HomeMatic Socket ausgewählt sein.  
   Existieren in IPS mehrere Homematic Socket, so ist der auszuwählen, an welcher CCU das Statusdisplay angelernt ist.  

   Bei dem anlegen der Instanz wird automatisch ein Demo Display-Script erzeugt.  
   Details zu diesem Script und die dort Verfügbaren $_IPS-Variablen, sind dem Script selbst zu entnehmen.  

**Konfigurationsseite:**  
![Instanz hinzufügen](../docs/Dis-WM55.png)    

| Eigenschaft  |   Typ   | Standardwert |                       Funktion                       |
| :----------: | :-----: | :----------: | :--------------------------------------------------: |
|   PageUpID   | integer |      0       |   Variablen-ID für die Funktion Seite hoch/zurück    |
|  PageDownID  | integer |      0       |  Variablen-ID für die Funktion Seite runter/weiter   |
|  ActionUpID  | integer |      0       | Variablen-ID für die Funktion Aktion ausführen oben  |
| ActionDownID | integer |      0       | Variablen-ID für die Funktion Aktion ausführen unten |
|   MaxPage    | integer |      0       |                  Anzahl der Seiten                   |
|   Timeout    | integer |      0       |  Zeit in Sekunden bis wieder Seite 1 angezeigt wird  |
|   ScriptID   | integer |      0       |  Script-ID welches beim Tastendruck ausgeführt wird  |

## 4. Statusvariablen und Profile  

   Es werden keine Statusvariablen und Profile angelegt.  

## 5. PHP-Befehlsreferenz

   Es existieren keine PHP-Befehle für dieses Modul.  

## 6. Lizenz

  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
