<?php

declare(strict_types=1);
//## GRUNDFUNKTION
/*
  Beispiel für das Zusammenstellern der Daten für die Dis-WM55 Instanz.
  Das Script wird als "Display-Script" in der dazugehörigen Dis-WM55 Instanze eingetragen.
  Die vorbereiteten Daten für das Display werden als JSON kodierter String an die
  Dis-WM55 Instanz als Rückgabewert "Script-Result" übergeben.
  Beispiel der erzeugten Daten:
  {"1":{"Text":"SEITE 1","Icon":130,"Color":129},"2":{"Text":"Zeile2","Icon":0,"Color":129},"3":{"Text":"Zeile3","Icon":130,"Color":130},"4":{"Text":"Zeile4","Icon":0,"Color":130},"5":{"Text":"Zeile5","Icon":131,"Color":132},"6":{"Text":"Zeile6","Icon":0,"Color":132}}

  Der JSON-String wird aus einem Array erzeugt, welches folgendem Aufbau haben
  __MUSS__, damit die Dis-WM55 Instanz die Daten verarbeiten und an das Display
  senden kann.
  Zeile[1]['Text']  = Text Zeile 1
  Zeile[1]['Icon']  = Icon Zeile 1
  Zeile[1]['Color']  = Farbe Zeile 1
  Zeile[2]['Text']  = Text Zeile 2
  Zeile[2]['Icon']  = Icon Zeile 2
  Zeile[2]['Color']  = Farbe Zeile 2
  .
  .
  .
  Zeile[6]['Text']  = Text Zeile 6
  Zeile[6]['Icon']  = Icon Zeile 6
  Zeile[6]['Color']  = Farbe Zeile 6

  Um nicht immer die Zahlen für die Icons und Farben eintragen zu müssen wurden
  Konstanten definiert.
  Des weiteresn müssen Textzeilen mit der Funktion text_encode("Zeile mit Umlaut")
  übergeben werden, wenn Umlaute in der Zeile verwendet werden.

  Folgende Zeichen werden von der Anzeige zur Darstellung umgewandelt:
  \' => "="
  ] => "&"
  ; => Sanduhr
  < => Pfeil nach links oben
  = => Pfeil nach links unten
  @ => Pfeil nach unten (großes "V")
  > => Pfeil nach oben ("V" im Kopfstand)
 */

//## Konstanten
//--------------------------------
// Definition der Werte für die Icons
// 0x80 AUS                Icon_on
// 0x81 EIN                Icon_off
// 0x82 OFFEN              Icon_open
// 0x83 geschlossen        Icon_closed
// 0x84 fehler             Icon_error
// 0x85 alles ok           Icon_ok
// 0x86 information        Icon_information
// 0x87 neue nachricht     Icon_message
// 0x88 servicemeldung     Icon_service
// 0x89 Signal grün        Icon_green
// 0x8A Signal gelb        Icon_yellow
// 0x8B Signal rot         Icon_red
//      ohne Icon          Icon_no

define('Icon_on', 0x80);
define('Icon_off', 0x81);
define('Icon_open', 0x82);
define('Icon_closed', 0x83);
define('Icon_error', 0x84);
define('Icon_ok', 0x85);
define('Icon_information', 0x86);
define('Icon_message', 0x87);
define('Icon_service', 0x88);
define('Icon_signal_green', 0x89);
define('Icon_signal_yellow', 0x8A);
define('Icon_signal_red', 0x8B);
define('Icon_no', 0);

// Definition der Werte für die Farben
// 0x80 weiss              Color_white
// 0x81 rot                Color_red
// 0x82 orange             Color_orange
// 0x83 gelb               Color_yellow
// 0x84 grün               color_green
// 0x85 blau               color_blue

define('Color_white', 0x82);
define('Color_red', 0x81);
define('Color_orange', 0x82);
define('Color_yellow', 0x83);
define('Color_green', 0x84);
define('Color_blue', 0x85);

//## VERWENDUNG VON $_IPS

/*
  Die Dis-WM55 Instanz stellt über die IPS-Systemvariable $_IPS folgende Daten zur Verfügung:

  (string) $_IPS["ACTION"]
  "UP"				=>  Trigger für Taste-Hoch wurde ausgelößt
  "DOWN"			=>  Trigger für Taste-Runter wurde ausgelößt
  "ActionUP"		=>  Trigger für Aktion-Hoch wurde ausgelößt
  "ActionDOWN"	=>  Trigger für Aktion-Runter wurde ausgelößt

  (int) $_IPS["PAGE"]
  Die "Seite" welche dargestellt oder deren Aktion ausgeführt werden soll.

  (string) $_IPS["SENDER"] => "HMDisWM55"
  Fester Wert

  (int) $_IPS["EVENT"]
  Die Instanz-ID der HMDis-WM55 Instanz, welche dieses Script ausführt.


  Auf der Basis der Variable $_IPS["PAGE"] ist es nun möglich verschiedene Daten
  je nach "Seite" zu berechnen und übergeben.
  Ebenso ist es möglich (z.B. durch langen und kurzen Tastendruck) zwischen UP/DOWN
  und ActionUP/ActionDOWN zu unterscheiden und so Aktionen wie das Schalten von Licht ausführen zu lassen.

  Natürlich kann man auch nur kurze Tastendrücke verwenden und z.B. Kanal:2 als ActionUP und Kanal:1 als DOWN zu definieren.

 */

if ($_IPS['SENDER'] != 'HMDisWM55') {
    echo 'Dieses Skript wird automatisch über die Homematic Dis-WM55 Instanz ausgeführt';
    return;
}

if (($_IPS['ACTION'] == 'UP') || ($_IPS['ACTION'] == 'DOWN')) {
    switch ($_IPS['PAGE']) {                                  // Anzeige pro Seite
        case 1:  // Seite 1

            $display_line[1] = ['Text'  => 'SEITE 1', // Text  Seite 1 Zeile 1
                'Icon'                  => Icon_open, // Icon  Seite 1 Zeile 1
                'Color'                 => Color_red];                      // Farbe Seite 1 Zeile 1

            $display_line[2] = ['Text'  => 'Zeile2',
                'Icon'                  => Icon_no,
                'Color'                 => Color_red];

            $display_line[3] = ['Text'  => 'Zeile3',
                'Icon'                  => Icon_open,
                'Color'                 => Color_orange];

            $display_line[4] = ['Text'  => 'Zeile4',
                'Icon'                  => Icon_no,
                'Color'                 => Color_orange];

            $display_line[5] = ['Text'  => 'Zeile5',
                'Icon'                  => Icon_closed,
                'Color'                 => Color_green];

            $display_line[6] = ['Text'  => 'Zeile6',
                'Icon'                  => Icon_no,
                'Color'                 => Color_green];
            break;
        case 2:  // Seite 2
            $display_line[1] = ['Text' => ':',
                'Icon'                 => Icon_no];

            $display_line[2] = ['Text'  => 'SEITE 2',
                'Icon'                  => Icon_open,
                'Color'                 => Color_orange];

            $display_line[3] = ['Text' => '',
                'Icon'                 => Icon_no];

            $display_line[4] = ['Text'  => 'Uhrzeit',
                'Icon'                  => Icon_no,
                'Color'                 => Color_white];

            $display_line[5] = ['Text'  => date('H:i:s', time()), // Uhrzeit
                'Icon'                  => Icon_no,
                'Color'                 => Color_white];

            $display_line[6] = ['Text' => '',
                'Icon'                 => Icon_no];

            break;
        case 3:  // Seite 3
            $display_line[1] = ['Text' => '',
                'Icon'                 => Icon_no];

            $display_line[4] = ['Text'  => 'SEITE 3',
                'Icon'                  => Icon_open,
                'Color'                 => Color_orange];

            $display_line[2] = ['Text' => '', // GetValueFormatted(12345 /*[Objekt #12345 existiert nicht]*/);
                'Icon'                 => Icon_no];

            $display_line[3] = ['Text' => '',
                'Icon'                 => Icon_no];

            $display_line[5] = ['Text' => '',
                'Icon'                 => Icon_no];

            $display_line[6] = ['Text' => '',
                'Icon'                 => Icon_no];

            break;
    }
}

if ($_IPS['ACTION'] == 'ActionUP') {                              // Aktion & Anzeige bei ActionUP
    // Hier kann auch wie oben bei 'PAGE' noch je nach Seite unterschieden werden !
    $display_line[1] = ['Text'  => hex_encode('Führe'),
        'Icon'                  => Icon_no,
        'Color'                 => Color_orange];

    $display_line[2] = ['Text'  => 'Aktion',
        'Icon'                  => Icon_no,
        'Color'                 => Color_orange];

    $display_line[3] = ['Text'  => 'OBEN ',
        'Icon'                  => Icon_no,
        'Color'                 => Color_orange];

    $display_line[4] = ['Text'  => 'Seite ' . $_IPS['PAGE'],
        'Icon'                  => Icon_no,
        'Color'                 => Color_orange];

    $display_line[5] = ['Text'  => 'aus',
        'Icon'                  => Icon_no,
        'Color'                 => Color_orange];

    $display_line[6] = ['Text' => '',
        'Icon'                 => Icon_no];
}

if ($_IPS['ACTION'] == 'ActionDOWN') {                             // Aktion & Anzeige bei ActionDOWN
    // Hier kann auch wie oben bei 'PAGE' noch je nach Seite unterschieden werden !
    $display_line[1] = ['Text'  => hex_encode('Führe'),
        'Icon'                  => Icon_no,
        'Color'                 => Color_orange];

    $display_line[2] = ['Text'  => 'Aktion',
        'Icon'                  => Icon_no,
        'Color'                 => Color_orange];

    $display_line[3] = ['Text'  => 'UNTEN',
        'Icon'                  => Icon_no,
        'Color'                 => Color_orange];

    $display_line[4] = ['Text'  => 'Seite ' . $_IPS['PAGE'],
        'Icon'                  => Icon_no,
        'Color'                 => Color_orange];

    $display_line[5] = ['Text'  => 'aus',
        'Icon'                  => Icon_no,
        'Color'                 => Color_orange];

    $display_line[6] = ['Text' => '',
        'Icon'                 => Icon_no];
}

$data = json_encode($display_line);
echo $data; //Daten zurückgeben an Dis-WM55-Instanz
function hex_encode($string)
{
    $umlaut = ['Ä', 'Ö', 'Ü', 'ä', 'ö', 'ü', 'ß', ':'];
    $hex_neu = [chr(0x5b), chr(0x23), chr(0x24), chr(0x7b), chr(0x7c), chr(0x7d), chr(0x5f), chr(0x3a)];
    $return = str_replace($umlaut, $hex_neu, $string);
    return $return;
}
