# IPSHomeMaticExtended
Erweitert IPS um die native Unterstützung von:

* Systemvariablen der CCU
* Programmen auf der CCU
* Summenzähler der Leistungsmesser
* Display Status-Anzeige
* HomeMaticScript

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang) 
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [HomeMatic Systemvariablen] (#4-homematic-systemvariablen)
5. [HomeMatic Powermeter](#5-homematic-powermeter)
6. [HomeMatic Programme](#6-homematic-programme)
7. [HomeMatic WM55-Dis](#7-homematic-wm55-dis)
8. [HomeMatic-Script](#8-homematic-script) 
9. [Anhang](#9-anhang)

## 1. Funktionsumfang

   Abfragen von Systemvariablen inkl. Profilen und Werten von der CCU.  
   Schreiben von Werten der Systemvariablen zur CCU.    
   Standard Actionhandler für die Bedienung der Systemvariablen aus dem IPS-Webfront.  

   Abfragen des Summenzählers der Schaltaktoren mit Leistungsmessung aus der CCU.  
   (Weitere Energiemesser folgen)  

   Abfragen der auf der CCU vorhandenen HM-Programme.  
   Ausführen der HM-Programme auf der CCU.  
   Standard Actionhandler für die Bedienung der HM-Programme aus dem IPS-Webfront.  

   Dynamische Textanzeige auf dem Display-Wandtaster mit Statusdisplay.  
   Unterstützt mehrseite Anzeigen und das durchblättern per Tastendruck.  
   Ausführen von benutzerspezifischen Aktionen, auch in abhängigkeit der angezeigten Seite.  
   
   Native Schnittstelle zur CCU, um HomeMatic-Scripte durch die CCU ausführen zu lassen.  
   Direkte Rückmeldung der Ausführung durch einen Antwortstring im JSON-Format.  

   XML-API-Patch wird nicht benötigt.  
   Unterstützung von mehreren CCUs.  
   Einfache Einrichtung und Handhabung.  
   PHP-Befehle entsprechen dem vorhanden Standard von IPS.  
 
## 2. Voraussetzungen

   Funktionsfähige CCU1 und/oder CCU2, welche schon mit einem HomeMatic Socket in IPS eingerichtet ist.  
   In der CCU muß die Firewall entsprechend eingerichtet sein, das IPS auf die 'Remote HomeMatic-Script API' der CCU zugreifen kann.

    Einstellungen -> Systemsteuerung -> Firewall

   Bei 'Remote HomeMatic-Script API' muß entweder 'Vollzugriff' oder 'Eingeschränkt' eingestellt sein.
   Bei 'Eingeschränkt' ist dann unter 'IP-Adressen für eingeschränkten Zugriff' euer LAN / IPS-PC einzugeben.  
   (z.B. 192.168.178.0/24 => /24 ist die Subnet-Maske für das Netzwerk. Bei 255.255.255.0 ist das 24 bei 255.255.0.0. ist es 16.
   Oder es kann direkt eine einzelne Adresse eingetragen werden. z.B. 192.168.0.2

## 3. Installation

   - IPS 3.x  
        Kopieren von der HMSysVar.dll in das Unterverzeichniss 'modules' unterhalb des IP-Symcon Installationsverzeichnisses.  
        Der Ordner 'modules' muss u.U. manuell angelegt werden.
        Beispiel: 'C:\\IP-Symcon\\modules'  
        IPS-Dienst Neustarten.  

   - IPS 4.x  
        Über das 'Modul Control' folgende URL hinzufügen:  
        `git://github.com/Nall-chan/IPSHomematicExtended.git`  

## 4. HomeMatic Systemvariablen

   Unter Instanz hinzufügen sind die Systemvariablen unter dem Hersteller 'HomeMatic' zu finden.  
   Nach dem Anlegen der Instanz sollte als übergeordnetes Gerät schon der HomeMatic Socket ausgewählt sein.  
   Existieren in IPS mehrere Homematic Socket, so ist der auszuwählen, der der CCU entspricht von dem die Systemvariablen gelesen werden sollen.  

   Dieses Modul unterstützt zwei Möglichkeiten die Systemvariablen von der CCU abzufragen:  

   - Abfrage erfolgt über einen einstellbaren Intervall (Pull).

   - Die CCU löst einen Tastendruck einer virtuellen Fernbedienung aus,  
     welche in diesem Modul als Trigger für eine Abfrage verwendet wird (Push).

    **Vor/Nachteile der beiden Varianten:**

    * Intervall (Pull):  
        - \+ Benötigt kein Programm in der CCU.  
        - \- Änderungen werden in IPS nur mit Verzögerung erkannt.  
        - \- Unnötige Abfragen der CCU, wenn sich kein Wert in der CCU geändert hat.  
        - \- Hierdurch unnötiger Netzwerkverkehr und CPU-Rechenzeit der CCU und des IPS-Systems.  
        - \- Rückmeldung im WebFront nach auslösen einer Aktion kann bis zur Intervallzeit  
             verzögert dargestellt werden. (Status emulieren einschalten um Dies zu unterbinden.)  

    * Trigger von der CCU (Push):  
        - \- Benötigt ein Zentralenprogramm in der CCU, welches bei Aktualisierung von  
             Systemvariablen einen Tastendruck einer virtuellen Fernbedienung auslöst.  
        - \+ Änderungen werden sofort erkannt.  
        - \+ Unnötige Abfragen werden minimiert.  
        - \+ Rückmeldung im WebFront nach auslösen einer Aktion, entspricht sofort dem Wert der CCU.  

    Für die Intervall-Variante ist die Einstellung des Abfrage-Intervalls in Sekunden
    vorzunehmen, und bei Bedarf der Haken bei 'Status emulieren' zu setzen.

    Für die Trigger-Variante ist der in dem Zentralenprogramm der CCU verwendete
    Datenpunkt der virtuellen Fernbedienung unter 'Trigger für Refresh' auszuwählen
    (z.B. PRESS_SHORT).  

    **Hinweis:** Über den Homematic Konfigurator in IPS kann das benötigte Homematic Device
    komfortabel angelegt werden.  

    Über das Testcenter des Einstellungsdialog können die Systemvariablen sofort eingelesen
    werden, ohne auf den Intervall oder einen Trigger zu warten.  

    Unter dem Reiter 'Statusvariablen' sollten jetzt alle (\* siehe Powermeter) in der CCU
    vorhandenen Systemvariablen angezeigt werden.  

    Hier kann mit dem entfernen des Haken 'Benutze Standardaktion' die Bedienung einer
    Variable, aus dem WebFront heraus, unterbunden werden.  

    **Achtung:**  
    Die Profile der Systemvariablen werden nur beim Anlegen in IPS aus der CCU ausgelesen
    und übernommen.  
    Später in der CCU vorgenommene Änderungen an dem Profil einer Systemvariable werden nicht abgeglichen !  
    Änderungen sind dann entweder von Hand in IPS durchzuführen, oder das entsprechende Profil
    ist manuell zu löschen, es wird dann automatisch neu angelegt.

    Manuelle Änderungen an den Profilen sind teilweise nötig, da die CCU nur begrenzt
    Informationen zur Verfügung stellt.
    Dies betrifft z.B. die Schrittweite und die Anzahl der Kommastellen bei Float-Variablen.

    Außerdem können die Profile individuell verändert / ergänzt werden, dieses Modul ändert
    vorhandene Profile nicht.

    Der Profilname lautet immer:
    'HM.SysVar\<ID der Systemvariablen Instanz\>.\<IDENT der Systemvariable\>; (z.B. HM.SysVar12345.950).  
    Alle Statusvariablen dieses Moduls werden so benannt wie in der CCU.  

    **Hinweis:**  
    Namensänderungen in IPS werden durch die CCU immer überschrieben!  
    In der CCU gelöschte Systemvariablen, werden in IPS nicht antomatisch gelöscht.  

    Alle aus der CUU ausgelesenen Werte werden in IPS aufgrund des Zeitstempels der
    CCU-Variable und der IPS-Variable abgeglichen.  
    Somit werden unnötige Variablen-Updates in IPS vermieden, wenn die Variable in der
    CCU gar nicht aktualisiert wurde.  

    Hierbei ist es irrelevant ob sich der Wert geändert hat, ausschlaggebend ist die
    Aktualisierung.  

    Eventuelle Differenzen der Uhrzeiten und/oder Zeitzonen beider Systeme werden dabei
    automatisch berücksichtigt und erfordern somit keinen Eingriff durch den Benutzer.  

    ### PHP-Funktionen

    Um einen Wert einer Systemvariable aus IPS heraus in die CCU zu schreiben, werden die
    schon vorhandenen HM_WriteValue* Befehle von IPS genutzt.  

    Hier entspricht der Parameter mit dem Namen 'Parameter' dem IDENT der Systemvariable.  
    (Die IDENT werden unter dem Reiter 'Statusvariablen' des Einstellungsdialogs der Instanz angezeigt.)  

    **Beispiele:**  

        HM_WriteValueBoolean(integer $InstantID /*[HomeMatic Systemvariablen]*/, string '950' /* IDENT von Anwesenheit */, boolean true);  
        HM_WriteValueFloat(integer $InstantID /*[HomeMatic Systemvariablen]*/, string '2588' /* IDENT von Solltemp Tag */, float 21.0);  
        HM_WriteValueInteger(integer $InstantID /*[HomeMatic Systemvariablen]*/, string '12829', integer 56);  
        HM_WriteValueString(integer $InstantID /*[HomeMatic Systemvariablen]*/, string '14901', string 'TestString');  

## 5. HomeMatic Powermeter

   Die CCU legt für jeden 'Schaltaktor mit Leistungsmessung' automatisch eine Systemvariable
   und ein Programm an, welches den Totalwert dieses Aktors hoch zählt.  

   Dieser Wert wird auch bei Stromausfall bzw. ausstecken des entsprechenden Aktors, gehalten.  

   Diese Systemvariable unterscheidet sich von den 'normalen' Systemvariablen dahingehend,
   dass Sie nicht in der der Übersicht aller Systemvariablen in der CCU auftaucht.  
   (Im Gegensatz zu den Regenmengen Zählern des OC3.)  

   Entsprechend war es nötig für diesen Typ von Systemvariable ein eingenes IPS-Device zu
   implementieren.  

   Unter Instanz hinzufügen ist die Systemvariable 'Powermeter' unter dem Hersteller
   'HomeMatic' zu finden.  

   Nach dem Anlegen der Instanz sollte als übergeordnetes Gerät schon der HomeMatic Socket
   ausgewählt sein.  
   Existieren in IPS mehrere Homematic Socket, so ist der auszuwählen, der der CCU
   entspricht an dem der Aktor angelernt ist.  

   Dieses Modul fragt den Wert aus der CCU immer dann ab, wenn der Wert
   der Variable 'ENERGY_COUNTER' des entsprechenden Aktors sich in IPS aktualisiert.  
   Oder der IPS-Dienst startet bzw. wenn eine Instanz neu konfiguriert wurde.  

   Im Einstellungsdialog der Instanz ist entsprechend die zugehörige 'ENERGY_COUNTER'
   Variable des Aktors auszuwählen, von dem der 'ENERGY_COUNTER_TOTAL' Wert
   gelesen werden soll.  

   Als Profil für diese Variable ist ein Standard-IPS-Profil zugeordnet, und die Werte werden
   automatisch nach kWh umgerechnet.  

   
## 6. HomeMatic Programme

   Die auf der CCU eingerichteten Programme können mit dieser Instanz ausgelesen und auch gestartet werden.  

   Unter Instanz hinzufügen sind die 'HomeMatic Programme' unter dem Hersteller 'HomeMatic' zu finden.  
   Nach dem Anlegen der Instanz sollte als übergeordnetes Gerät schon der HomeMatic Socket ausgewählt sein.  
   Existieren in IPS mehrere Homematic Socket, so ist der auszuwählen, aus welcher CCU die Programme gelesen werden sollen.  

   Dieses Modul hat keinerlei Einstellungen, welche konfiguriert werden müssen.  

   Im Testcenter ist es jedoch über den Button 'CCU auslesen' möglich, die auf der CCU vorhandenen Programme auszulesen.
   Dies erfolgt auch autoamtisch bei Systemstart von IPS und wenn die Instanz angelegt wird.  

   Die Programme werden als Integer-Variable unterhalb der Instanz erzeugt. Es wird automatisch der Name und die Beschreibung aus der CCU übernommen.  

   Des weiteren wird ein Standard-Profil 'Execute-HM' angelegt und den Variablen zugeordnet.  

   Es ist somit sofort möglich die Programme aus dem WebFront heraus zu starten.  

   Werden in der CCU Programme gelöscht, so müssen die dazugehörigen Variablen in IPS bei Bedarf manuell gelöscht werden.  

### PHP-Funktionen

    string HM_ReadPrograms(integer $InstantID /*[HomeMatic Programme]*/)
   Alle Programme auf der CCU werden ausgelesen und bei Bedarf umbenannt oder neu angelegt.

    string HM_StartProgram(integer $InstantID /*[HomeMatic Programme]*/, string $IDENT);
   Startet ein auf der CCU hinterlegtes Programm. Als `$IDENT` muss der Ident der Variable des Programmes übergeben werden.  
   (Die IDENT werden unter dem Reiter 'Statusvariablen' des Einstellungsdialogs der Instanz angezeigt.)  

   **Beispiele:**

        HM_ReadPrograms(12345 /*[HomeMatic Programme]*/);  
        HM_StartProgram(12345 /*[HomeMatic Programme]*/, string '4711' /* IDENT von Programm Licht Alles aus */);  


## 7. HomeMatic WM55-Dis

Unvollständig
=============

Work in progress...


## 8. HomeMatic-Script

Dies Instanz ermöglicht es eigene Homematic-Scripte zur CCU zu senden.  
Des weiteren wird die Rückgabe der Ausführung an den Aufrufer zurück gegeben.  
So kann z.B. per PHP-Script in IPS ein dynamisches Homematic-Script als String erstellt werden,
und die erfolgte Ausführung ausgewertet werden.  

### PHP-Funktionen

    string HM_RunScript(integer $InstantID /*[HomeMatic RemoteScript Interface]*/,string $Script)

   **Beispiel:**

   Abfrage der Uhrzeit und Zeitzone von der CCU:

    $HMScript = 'Now=system.Date("%F %T%z");' . PHP_EOL  
              . 'TimeZone=system.Date("%z");' . PHP_EOL;   
    $HMScriptResult = HM_RunScript(12345 /*[HomeMatic RemoteScript Interface]*/, $HMScript);  
    var_dump(json_decode($HMScriptResult));  


## 9. Anhang

**GUID's:**  

| Device                           | GUID                                   |
| :------------------------------: | :------------------------------------: |
| HomeMatic Systemvariablen        | {400F9193-FE79-4086-8D76-958BF9C1B357} |
| HomeMatic Powermeter             | {AF50C42B-7183-4992-B04A-FAFB07BB1B90} |
| HomeMatic Programme              | {A5010577-C443-4A85-ABF2-3F2D6CDD2465} |
| HomeMatic RemoteScript Interface | {246EDB89-70BC-403B-A1FA-3B3B1B540401} |
| HomeMatic Dis-WM55               | {271BCAB1-0658-46D9-A164-985AEB641B48} |

**Changelog:**

Version 2.0:

Version 1.5:

Version 1.3:

Version 1.1:


