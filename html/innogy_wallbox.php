#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2021]  [Ulrich Kunz]
//
//  Dieses Programm ist freie Software. Sie können es unter den Bedingungen
//  der GNU General Public License, wie von der Free Software Foundation
//  veröffentlicht, weitergeben und/oder modifizieren, entweder gemäß
//  Version 3 der Lizenz oder (nach Ihrer Option) jeder späteren Version.
//
//  Die Veröffentlichung dieses Programms erfolgt in der Hoffnung, daß es
//  Ihnen von Nutzen sein wird, aber OHNE IRGENDEINE GARANTIE, sogar ohne
//  die implizite Garantie der MARKTREIFE oder der VERWENDBARKEIT FÜR EINEN
//  BESTIMMTEN ZWECK. Details finden Sie in der GNU General Public License.
//
//  Ein original Exemplar der GNU General Public License finden Sie hier:
//  http://www.gnu.org/licenses/
//
//  Dies ist ein Programmteil des Programms "Solaranzeige"
//
//  Es dient dem Auslesen der Wallbe Wallbox über das LAN
//  Port 502 GeräteID = 255
//
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
*****************************************************************************/
$path_parts = pathinfo( $argv[0] );
$Pfad = $path_parts['dirname'];
//if (!is_file( $Pfad."/1.user.config.php" )) {
  // Handelt es sich um ein Multi Regler System?
  require ($Pfad."/user.config.php");
//}
require_once ($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen( );
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}
$Tracelevel = 7; //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Version = "";
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "-------------   Start  innogy_wallbox.php   --------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  $funktionen->log_schreiben( "Hardware Version: ".$Platine, "o  ", 7 );
  $Version = trim( $Teile[2] );
  if ($Teile[3] == "Model") {
    $Version .= trim( $Teile[4] );
    if ($Teile[5] == "Plus") {
      $Version .= trim( $Teile[5] );
    }
  }
}
switch ($Version) {

  case "2B":
    break;

  case "3B":
    break;

  case "3BPlus":
    break;

  case "4B":
    break;

  default:
    break;
}

if (empty($WR_Adresse)) {
  $WR_ID = "01";
}
elseif (strlen( $WR_Adresse ) == 1) {
  $WR_ID = str_pad( $WR_Adresse, 2, "0", STR_PAD_LEFT );
}
elseif (strlen( $WR_Adresse ) == 3) {
  $WR_ID = $WR_Adresse;
}
else {
  $WR_ID = str_pad( substr( $WR_Adresse, - 2 ), 2, "0", STR_PAD_LEFT );
}

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Ladeleistung der Wallbox
//  pro Ladung zu speichern.
//
*****************************************************************************/
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProLadung.txt";
if (file_exists( $StatusFile )) {

  /***************************************************************************
  //  Daten einlesen ...
  ***************************************************************************/
  $aktuelleDaten["WattstundenProLadung"] = file_get_contents( $StatusFile );
  $funktionen->log_schreiben( "WattstundenProLadung: ".round( $aktuelleDaten["WattstundenProLadung"], 2 ), "   ", 8 );
  if (empty($aktuelleDaten["WattstundenProLadung"])) {
    $aktuelleDaten["WattstundenProLadung"] = 0;
  }
}
else {
  /***************************************************************************
  //  Inhalt der Status Datei anlegen.
  ***************************************************************************/
  $rc = file_put_contents( $StatusFile, "0" );
  if ($rc === false) {
    $funktionen->log_schreiben( "Konnte die Datei WhProLadung.txt nicht anlegen.", "XX ", 5 );
  }
}


$COM1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 150 ); // normal 15
if (!is_resource( $COM1 )) {
  $funktionen->log_schreiben( "Kein Kontakt zur Wallbox ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 3 );
  goto Ausgang;
}




/***************************************************************************
//  Einen Befehl an die Wallbox senden
//
//  Per MQTT  start = 1    amp = 6
//  Per HTTP  start_1      amp_6
//
***************************************************************************/
if (file_exists( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" )) {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' vorhanden----", "|- ", 5 );
  $Inhalt = file_get_contents( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  $Befehle = explode( "\n", trim( $Inhalt ));
  $funktionen->log_schreiben( "Befehle: ".print_r( $Befehle, 1 ), "|- ", 9 );
  for ($i = 0; $i < count( $Befehle ); $i++) {
    if ($i >= 6) {
      //  Es werden nur maximal 5 Befehle pro Datei verarbeitet!
      break;
    }

    /*********************************************************************************
    //  In der Datei "befehle.ini.php" müssen alle gültigen Befehle aufgelistet
    //  werden, die man benutzen möchte.
    //  Achtung! Genau darauf achten, dass der Befehl richtig geschrieben wird,
    //  damit das Gerät keinen Schaden nimmt.
    //  curr_6000 ist nur zum Testen ...
    //  Siehe Dokument:  Befehle_senden.pdf
    *********************************************************************************/
    if (file_exists( $Pfad."/befehle.ini.php" )) {
      $funktionen->log_schreiben( "Die Befehlsliste 'befehle.ini.php' ist vorhanden----", "|- ", 9 );
      $INI_File = parse_ini_file( $Pfad.'/befehle.ini.php', true );
      $Regler35 = $INI_File["Regler35"];
      $funktionen->log_schreiben( "Befehlsliste: ".print_r( $Regler35, 1 ), "|- ", 9 );
      foreach ($Regler35 as $Template) {
        $Subst = $Befehle[$i];
        $l = strlen( $Template );
        for ($p = 1; $p < $l;++$p) {
          $funktionen->log_schreiben( "Template: ".$Template." Subst: ".$Subst." l: ".$l, "|- ", 10 );
          if ($Template[$p] == "#") {
            $Subst[$p] = "#";
          }
        }
        if ($Template == $Subst) {
          break;
        }
      }
      if ($Template != $Subst) {
        $funktionen->log_schreiben( "Dieser Befehl ist nicht zugelassen. ".$Befehle[$i], "|o ", 3 );
        $funktionen->log_schreiben( "Die Verarbeitung der Befehle wird abgebrochen.", "|o ", 3 );
        break;
      }
    }
    else {
      $funktionen->log_schreiben( "Die Befehlsliste 'befehle.ini.php' ist nicht vorhanden----", "|- ", 3 );
      break;
    }
    $Teile = explode( "_", $Befehle[$i] );
    $Antwort = "";
    // Hier wird der Befehl gesendet...
    //  $Teile[0] = Befehl
    //  $Teile[1] = Wert
    //  GeraeteAdresse.Befehl.Register.Laenge.Wert
    if (strtolower( $Teile[0] ) == "start") {
      continue;
    }
    if (strtolower( $Teile[0] ) == "stop") {
      // $sendenachricht = hex2bin( $TransactionIdentifier.$ProtocilIdentifier.$MessageLenght.$GeraeteAdresse.$FunktionsCode.$Register.$RegisterAnzahl.$Befehl );

      $sendenachricht = hex2bin( "000100000006FF06138C".$AmpHex ); //  Stromänderung
    }
    if (strtolower( $Teile[0] ) == "amp") {
      $Ampere =  $Teile[1];
      $AmpHex = str_pad( dechex( $Ampere ), 4, "0", STR_PAD_LEFT );
      //  11 = 000B = 11 Ampere
      $sendenachricht = hex2bin( "000100000006FF06138C".$AmpHex ); //  Stromänderung
    }
    $rc = fwrite( $COM1, $sendenachricht );
    $Antwort = bin2hex( fread( $COM1, 1000 )); // 1000 Bytes lesen
    $funktionen->log_schreiben( "Antwort: ".$Antwort, "   ", 3 );
    sleep( 2 );
  }
  $rc = unlink( $Pfad."/../pipe/".$GeraeteNummer.".befehl.steuerung" );
  if ($rc) {
    $funktionen->log_schreiben( "Datei  /../pipe/".$GeraeteNummer.".befehl.steuerung  gelöscht.", "    ", 9 );
  }

}
else {
  $funktionen->log_schreiben( "Steuerdatei '".$GeraeteNummer.".befehl.steuerung' nicht vorhanden----", "|- ", 9 );
}









$i = 1;
do {
  /***************************************************************************
  //  Ab hier wird die Wallbox ausgelesen.
  //
  //  [Produkt] => eBox5DDE
  //  [Seriennummer] => LE005DDE
  //  [Firmware] => 1.3.36
  //  [Hersteller] => innogy eMobility Solutions GmbH
  //  [Status] => A
  //  [Kabelstatus] => 0001
  //  [MaxLadestrom] => 16
  //  [MaxLadestrom_R] => 16
  //  [MaxLadsterom_S] => 16
  //  [Max_Ladestrom_T] => 16
  //  [Strom_R] => 0.07
  //  [Strom_S] => 0.03
  //  [Strom_T] => 0.07
  //
  //  modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase = 600000 ) {
  //  $GeraeteAdresse  $WR_ID in Hex 2stellig
  //  $FunktionsCode   03, 04 usw.
  //  $RegisterAdresse in Dezimal
  //  $RegisterAnzahl  in HEX!
  //  $DatenTyp        U16 Float32 usw.
  //  $Timebase        100000
  //
  ***************************************************************************/

  $funktionen->log_schreiben( "Abfrage: ", "   ", 9 );

  $Timebase = 10000; // Je nach Dongle Firmware zwischen 60000 und 200000

  /**********************
    stream_set_blocking( $COM1, false );
    $sendenachricht = hex2bin("00010000000A01100401000200010002");
    echo "Senden: ".bin2hex($sendenachricht)."\n";
    //$rc = fwrite( $COM1, $sendenachricht, strlen($sendenachricht));
    $funktionen->log_schreiben( bin2hex($sendenachricht)."   Antwort 1: ".$rc, "    ", 5 );
    sleep(1);
    $Antwort = bin2hex( fread( $COM1, 1000 )); // 1000 Bytes lesen
    echo "Antwort 2: ".$Antwort."\n";

    $Antwort = bin2hex( fread( $COM1, 1000 )); // 1000 Bytes lesen

    $sendenachricht = hex2bin("200200000006010304010002");
    echo "Senden: 200200000006010404010002\n";
    $rc = fwrite( $COM1, $sendenachricht, strlen($sendenachricht));
    sleep(3);
    $Antwort = bin2hex( fread( $COM1, 1000 )); // 1000 Bytes lesen
    echo "Antwort 3: ".$Antwort."\n";

    stream_set_blocking( $COM1, true );

    echo "Ende\n";
    echo $COM1."\n\n";
//  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "06", "1028", "0001", "U16", $Timebase, "00" );
//  $funktionen->log_schreiben( print_r($rc,1), "    ", 5 );

  **********************/

  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "0000", "0019", "String", $Timebase );
  $aktuelleDaten["Produkt"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "0025", "0019", "String", $Timebase );
  $aktuelleDaten["Seriennummer"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "0200", "0019", "String", $Timebase );
  $aktuelleDaten["Firmware"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "0100", "0019", "String", $Timebase );
  $aktuelleDaten["Hersteller"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "0275", "0002", "String", $Timebase );
  $aktuelleDaten["Status"] = substr( $rc["Wert"], 0, 1 );
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "0300", "0001", "U16", $Timebase );
  $aktuelleDaten["Kabelstatus"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "1000", "0002", "Float32", $Timebase );
  $aktuelleDaten["MaxLadestrom"] = trim( $rc["Wert"] );
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "1000", "0002", "Float32", $Timebase );
  $aktuelleDaten["MaxLadestrom_R"] = $rc["Wert"];
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "1002", "0002", "Float32", $Timebase );
  $aktuelleDaten["MaxLadsterom_S"] = $rc["Wert"];
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "1004", "0002", "Float32", $Timebase );
  $aktuelleDaten["MaxLadestrom_T"] = $rc["Wert"];
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "1006", "0002", "Float32", $Timebase );
  $aktuelleDaten["Strom_R"] = floor( $rc["Wert"] * 10 )/10;
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "1008", "0002", "Float32", $Timebase );
  $aktuelleDaten["Strom_S"] = floor( $rc["Wert"] * 10 )/10;
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "04", "1010", "0002", "Float32", $Timebase );
  $aktuelleDaten["Strom_T"] = floor( $rc["Wert"] * 10 )/10;

  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "03", "1025", "0001", "U16", $Timebase );
  $aktuelleDaten["Setup_L1"] = $rc["Wert"];
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "03", "1026", "0001", "U16", $Timebase );
  $aktuelleDaten["Setup_L2"] = $rc["Wert"];
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "03", "1027", "0001", "U16", $Timebase );
  $aktuelleDaten["Setup_L3"] = $rc["Wert"];
  $rc = $funktionen->modbus_tcp_lesen( $COM1, $WR_ID, "03", "1028", "0001", "U16", $Timebase );
  $aktuelleDaten["Station_aktiv"] = $rc["Wert"];

  /**************************************************************************
  //  Ende Wallbox auslesen
  ***************************************************************************/
  $FehlermeldungText = "";

  $aktuelleDaten["Leistung_R"] = round(230 * $aktuelleDaten["Strom_R"],1);
  $aktuelleDaten["Leistung_S"] = round(230 * $aktuelleDaten["Strom_S"],1);
  $aktuelleDaten["Leistung_T"] = round(230 * $aktuelleDaten["Strom_T"],1);
  $aktuelleDaten["Leistung"] = ($aktuelleDaten["Leistung_R"] + $aktuelleDaten["Leistung_S"] + $aktuelleDaten["Leistung_T"]);
  $aktuelleDaten["Frequenz"] = 0;

  $aktuelleDaten["Anz_Phasen"] = 0;
  if ($aktuelleDaten["Leistung_R"] > 0) {
    $aktuelleDaten["Anz_Phasen"] .= +1 ;
  }
  if ($aktuelleDaten["Leistung_S"] > 0) {
    $aktuelleDaten["Anz_Phasen"] .= +1 ;
  }
  if ($aktuelleDaten["Leistung_T"] > 0) {
    $aktuelleDaten["Anz_Phasen"] .= +1 ;
  }




  if ($aktuelleDaten["Kabelstatus"] != 3 and $aktuelleDaten["WattstundenProLadung"] > 0) {
    $aktuelleDaten["WattstundenProLadung"] = 0; // Zähler pro Ladung zurücksetzen
    $rc = file_put_contents( $StatusFile, "0" );
    $funktionen->log_schreiben( "WattstundenProLadung gelöscht.", "    ", 5 );
  }

  // $funktionen->log_schreiben( var_export( $rc, 1 ), "   ", 7 );










  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $aktuelleDaten["WattstundenGesamtHeute"] = 0; // dummy

  if (date("Ymd") < "20220607") {
    $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 7 );
  }
  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/innogy_wallbox_math.php" )) {
    include 'innogy_wallbox_math.php'; // Falls etwas neu berechnet werden muss.
  }

  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and $i == 1) {
    $funktionen->log_schreiben( "MQTT Daten zum [ $MQTTBroker ] senden.", "   ", 1 );
    require ($Pfad."/mqtt_senden.php");
  }

  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time( );
  $aktuelleDaten["Monat"] = date( "n" );
  $aktuelleDaten["Woche"] = date( "W" );
  $aktuelleDaten["Wochentag"] = strftime( "%A", time( ));
  $aktuelleDaten["Datum"] = date( "d.m.Y" );
  $aktuelleDaten["Uhrzeit"] = date( "H:i:s" );

  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] = $InfluxPort;
  $aktuelleDaten["InfluxUser"] = $InfluxUser;
  $aktuelleDaten["InfluxPassword"] = $InfluxPassword;
  $aktuelleDaten["InfluxDBName"] = $InfluxDBName;
  $aktuelleDaten["InfluxDaylight"] = $InfluxDaylight;
  $aktuelleDaten["InfluxDBLokal"] = $InfluxDBLokal;
  $aktuelleDaten["InfluxSSL"] = $InfluxSSL;
  $aktuelleDaten["Demodaten"] = false;

  /*********************************************************************
  //  Daten werden in die Influx Datenbank gespeichert.
  //  Lokal und Remote bei Bedarf.
  *********************************************************************/
  if ($InfluxDB_remote) {
    // Test ob die Remote Verbindung zur Verfügung steht.
    if ($RemoteDaten) {
      $rc = $funktionen->influx_remote_test( );
      if ($rc) {
        $rc = $funktionen->influx_remote( $aktuelleDaten );
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local( $aktuelleDaten );
    }
  }
  else {
    $rc = $funktionen->influx_local( $aktuelleDaten );
  }
  if (is_file( $Pfad."/1.user.config.php" )) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (9 - (time( ) - $Start));
    $funktionen->log_schreiben( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    $funktionen->log_schreiben( "Schleife: ".($i)." Zeitspanne: ".(floor( ((9 * $i) - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( ((9 * $i) - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));

if (isset($aktuelleDaten["Firmware"])) {

  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $aktuelleDaten["Solarspannung"] = $aktuelleDaten["Solarspannung1"];
    $funktionen->log_schreiben( "Daten werden zur HomeMatic übertragen...", "   ", 8 );
    require ($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben( "Nachrichten versenden...", "   ", 8 );
    require ($Pfad."/meldungen_senden.php");
  }
  $funktionen->log_schreiben( "OK. Datenübertragung erfolgreich.", "   ", 7 );
}
else {
  $funktionen->log_schreiben( "Keine gültigen Daten empfangen.", "!! ", 6 );
}

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Ladeleistung der Wallbox
//  pro Ladung zu speichern.
*****************************************************************************/
if (file_exists( $StatusFile ) and $aktuelleDaten["Kabelstatus"] == 3) {

  /***************************************************************************
  //  Daten einlesen ...   ( Watt * Stunden ) pro Ladung = Wh
  ***************************************************************************/
  $whProLadung = file_get_contents( $StatusFile );
  $whProLadung = ($whProLadung + ($aktuelleDaten["Leistung"] / 60));
  $rc = file_put_contents( $StatusFile, $whProLadung );
  $funktionen->log_schreiben( "WattstundenProLadung: ".round( $whProLadung ), "   ", 5 );
}


fclose($COM1);
//
Ausgang:
//
$funktionen->log_schreiben( "-------------   Stop   innogy_wallbox.php   --------------------- ", "|--", 6 );
return;


?>


