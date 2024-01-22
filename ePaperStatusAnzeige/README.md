[![SDK](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Modul%20version-3.74-blue.svg)]()
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
[![Version](https://img.shields.io/badge/Symcon%20Version-6.1%20%3E-green.svg)](https://community.symcon.de/t/ip-symcon-6-1-stable-changelog/40276-IP-Symcon-5-1-%28Stable%29-Changelog)
[![Check Style](https://github.com/Nall-chan/HomematicExtended/workflows/Check%20Style/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions) [![Run Tests](https://github.com/Nall-chan/HomematicExtended/workflows/Run%20Tests/badge.svg)](https://github.com/Nall-chan/HomematicExtended/actions)   
[![Spenden](https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_SM.gif)](../README.md#6-spenden) 

# HomeMatic Dis-EP-WM55  <!-- omit in toc -->
   Hier handelt es sich um eine Instanz, welche die Verwendung des ePaper-Statusdisplays vereinfachen soll.  

## Dokumentation <!-- omit in toc -->

**Inhaltsverzeichnis**

- [1. Funktionsumfang](#1-funktionsumfang)
- [2. Installation](#2-installation)
- [3. Einrichten der Instanzen in IP-Symcon](#3-einrichten-der-instanzen-in-ip-symcon)
- [4. Statusvariablen und Profile](#4-statusvariablen-und-profile)
- [5. PHP-Befehlsreferenz](#5-php-befehlsreferenz)
  - [HM_WriteValueDisplayNotify](#hm_writevaluedisplaynotify)
  - [HM_WriteValueDisplayLine](#hm_writevaluedisplayline)
  - [HM_WriteValueDisplayLineEx](#hm_writevaluedisplaylineex)
  - [HM_WriteValueDisplay](#hm_writevaluedisplay)
  - [HM_WriteValueDisplayEx](#hm_writevaluedisplayex)
- [6. Lizenz](#6-lizenz)

## 1. Funktionsumfang

   Hier handelt es sich um eine Instanz, welche die Verwendung des ePaper Statusdisplays im 55er-Rahmen vereinfachen soll.  
   Über spezielle PHP-Befehle ist es möglich das Display anzusteuern.  

## 2. Installation

Dieses Modul ist Bestandteil der [HomeMaticExtended-Library](../).  

## 3. Einrichten der Instanzen in IP-Symcon


![Instanzen](../docs/HMExtendedInstanzen.png)  
   Unter Instanz hinzufügen ist das Gerät 'HomeMatic WM55-EP-Dis' unter dem Hersteller 'HomeMatic' zu finden.  
   Nach dem Anlegen der Instanz sollte als übergeordnetes Gerät schon der HomeMatic Socket ausgewählt sein.  
   Existieren in IPS mehrere Homematic Socket, so ist der auszuwählen, an welcher CCU das Statusdisplay angelernt ist.  

**Konfigurationsseite:**  
   Als Adresse ist der Kanal 3 der Anzeige einzutragen z.B. LEY012345:3  

| Eigenschaft |  Typ   | Standardwert |      Funktion       |
| :---------: | :----: | :----------: | :-----------------: |
|   Address   | string |              | Adresse des Gerätes |


## 4. Statusvariablen und Profile  

   Es werden keine Statusvariablen und Profile angelegt.  


## 5. PHP-Befehlsreferenz

   Das Display wird über folgende PHP-Befehle beschrieben.  

   Die Werte für die Parameter sind dabei immer identisch:  
   '$Text' Der darzustellende Text (bis 12 Zeichen).  
       Es können auch die vordefinierten Textblöcke durch die Zeichenfolge '0x80' bis '0x89' angesprochen werden.  

   '$Icon' Das anzuzeigende Icon (0-9):  

| Wert  |    Icon     |
| :---: | :---------: |
|   0   |    keins    |
|   1   |  Lampe aus  |
|   2   |  Lampe an   |
|   3   | Schloss auf |
|   4   | Schloss zu  |
|   5   |   Fehler    |
|   6   |     OK      |
|   7   |    Info     |
|   8   |  Nachricht  |
|   9   |   Service   |

   '$Chime' Tonfolge (0-6):  

| Wert  |      Ton       |
| :---: | :------------: |
|   0   |      aus       |
|   1   |   lang lang    |
|   2   |   lang kurz    |
|   3   | lang kurz kurz |
|   4   |      kurz      |
|   5   |   kurz kurz    |
|   6   |      lang      |

   '$Repeat' Anzahl der Wiederholungen (0-15).  

   '$Wait' Wartezeit in 10 Sekunden zwischen den Wiederholungen.  

   '$Color' Farbe der LED (0-3):  

| Wert  | Farbe  |
| :---: | :----: |
|   0   |  aus   |
|   1   |  rot   |
|   2   |  grün  |
|   3   | orange |


   
### HM_WriteValueDisplayNotify  

Steuert den Summer und die LED des Display.  

 ```php
    boolean HM_WriteValueDisplayNotify(int $InstantID /*[HomeMatic Dis-EP-WM55]*/,int $Chime, int $Repeat, int $Wait, int $Color)
```  

**Beispiele:**  

```php
// Ton 1, keine Wiederholung, Farbe rot
HM_WriteValueDisplayNotify(12345,1,0,0,1);

// Ton 2, eine Wiederholung, 30 Sekunden pause, Farbe grün
HM_WriteValueDisplayNotify(12345,2,1,3,2);

// Kein Ton, Farbe orange
HM_WriteValueDisplayNotify(12345,0,0,0,3);

// Ton 3, zwei Wiederholung, 10 Sekunden pause, keine Farbe
HM_WriteValueDisplayNotify(12345,3,2,1,0);
```

### HM_WriteValueDisplayLine  

Beschreibt eine Zeile vom Display.  
Wird ein leerer Text übergeben, wird die Anzeige gelöscht.  

 ```php
    boolean HM_WriteValueDisplayLine(int $InstantID /*[HomeMatic Dis-EP-WM55]*/,int $Line, string $Text, int $Icon)
```  

**Beispiele:**  

```php
// Zeile 1 mit Text 'Zeile 1' ohne Icon setzen.
HM_WriteValueDisplayLine(12345,1,'Zeile 1',0);

// Zeile 1 löschen und Icon OK anzeigen.
HM_WriteValueDisplayLine(12345,1,'',6);

// Zeile 3 mit Text 'Welt!' und Icon Service
HM_WriteValueDisplayLine(12345,3,'Welt!',9);

// Zeile 2 mit Text 'Hallo' und Icon Information
HM_WriteValueDisplayLine(12345,2,'Hallo',7);
```

### HM_WriteValueDisplayLineEx  

Beschreibt eine Zeile vom Display und steuert den Summer sowie die LED des Display an.  
Wird ein leerer Text übergeben, wird die Anzeige gelöscht.  

 ```php
    boolean HM_WriteValueDisplayLineEx(int $InstantID /*[HomeMatic Dis-EP-WM55]*/,int $Line, string $Text, int $Icon, int $Chime, int $Repeat, int $Wait, int $Color)
```  

**Beispiele:**  

```php
// Zeile 1 mit Text 'Zeile 1' ohne Icon setzen.
// Ton 1, keine Wiederholung, Farbe rot
HM_WriteValueDisplayLineEx(12345,1,'Zeile 1',0,1,0,0,1);
```

### HM_WriteValueDisplay  

Beschreibt alle Zeilen vom Display.
Wird ein leerer Text übergeben, wird die Anzeige gelöscht.  

 ```php
    boolean HM_WriteValueDisplay(int $InstantID /*[HomeMatic Dis-EP-WM55]*/,string $Text1, int $Icon1, string $Text2, int $Icon2, string $Text3, int $Icon3)
```  

**Beispiele:**  

```php
// Zeile 1 mit Text '111' und Icon Licht an
// Zeile 2 mit Text '222' und Icon Licht aus
// Zeile 3 mit Text '333' und Icon Schloss offen
HM_WriteValueDisplay(12345,'111',1,'222',2,'333',3);
```

### HM_WriteValueDisplayEx  

Beschreibt alle Zeilen vom Display und steuert den Summer sowie die LED des Display an.  
Wird ein leerer Text übergeben, wird die Anzeige gelöscht.  

 ```php
    boolean HM_WriteValueDisplayEx(int $InstantID /*[HomeMatic Dis-EP-WM55]*/,string $Text1, int $Icon1, string $Text2, int $Icon2, string $Text3, int $Icon3, int $Chime, int $Repeat, int $Wait, int $Color)
```  

**Beispiele:**  

```php
// Zeile 1 mit Text '111' und Icon Licht an
// Zeile 2 mit Text '222' und Icon Licht aus
// Zeile 3 mit Text '333' und Icon Schloss offen
// Ton 1, 15 Wiederholungen ohne Pause, Farbe grün
HM_WriteValueDisplayEx(12345,'111',1,'222',2,'333',3,1,15,0,2);
```   

## 6. Lizenz

  [CC BY-NC-SA 4.0](https://creativecommons.org/licenses/by-nc-sa/4.0/)  
