#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016 - 2021] [Ulrich Kunz]
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
//  Es dient dem Auslesen der Hoymiles Microwechselrichter.
//  mit LAN Schnittstelle. MODBUS TCP
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//
//
//
*****************************************************************************/
$path_parts = pathinfo( $argv[0] );
$Pfad = $path_parts['dirname'];
if (!is_file( $Pfad."/1.user.config.php" )) {
  // Handelt es sich um ein Multi Regler System?
  require ($Pfad."/user.config.php");
}
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
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time( ); // Timestamp festhalten
$funktionen->log_schreiben( "-------------   Start  hoymiles.php    -------------------------- ", "|--", 6 );
$funktionen->log_schreiben( "Zentraler Timestamp: ".$zentralerTimestamp, "   ", 8 );
$aktuelleDaten = array();

$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;
setlocale( LC_TIME, "de_DE.utf8" );
//  Hardware Version ermitteln.
$Teile = explode( " ", $Platine );
if ($Teile[1] == "Pi") {
  $funktionen->log_schreiben( "Hardware Version: ".$Platine, "o  ", 8 );
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
  $WR_ID = "FF";
}
elseif(strlen($WR_Adresse) == 1)  {
  $WR_ID = str_pad(dechex($WR_Adresse),2,"0",STR_PAD_LEFT);
}
else {
  $WR_ID = str_pad(dechex($WR_Adresse),2,"0",STR_PAD_LEFT);
}

$funktionen->log_schreiben("WR_ID: ".$WR_ID,"+  ",8);


$COM1 = fsockopen( $WR_IP, $WR_Port, $errno, $errstr, 10 ); // 10 = Timeout in Sekunden
if (!is_resource( $COM1 )) {
  $funktionen->log_schreiben( "Kein Kontakt zum Wechselrichter ".$WR_IP."  Port: ".$WR_Port, "XX ", 3 );
  $funktionen->log_schreiben( "Exit.... ", "XX ", 9 );
  goto Ausgang;
}

$i = 1;
do {
  $funktionen->log_schreiben( "Die Daten werden ausgelesen...", "+  ", 7 );

  /****************************************************************************
  //  Ab hier wird der Wechselrichter ausgelesen.
  //
  //  function modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp )
  //
  //
  ****************************************************************************/

  // Bei der DTU-pro ist die Geräte ID egal.
  $GeraeteAdresse = "01";

  /********************/
  $FunktionsCode = "02";
  //  Alle angeschlossenen Geräte haben einen eigenen Speicherbereich. Siehe Beschreibung
  $Shift = (($WR_Adresse * 6) - 6);

  $RegisterAdresse = 49158 + $Shift;    // Hex C006   49158
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "Hex";
  $Timebase = 100000; //  Wie lange soll auf eine Antwort gewartet werden? normal = 100000 = 0,1 Sekunden
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["OnOff"] = hexdec(substr($Ergebnis["Wert"],0,2));
    $aktuelleDaten["LimitPower"] = hexdec(substr($Ergebnis["Wert"],2));
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Gerät ist aus.", "   ", 5 );
    goto Ausgang;
  }

  /********************/
  $FunktionsCode = "03";
  //  Alle angeschlossenen Geräte haben einen eigenen Speicherbereich. Siehe Beschreibung
  $Shift = (($WR_Adresse * 40) - 40);

  $RegisterAdresse = 4097 + $Shift;    // Hex 1002
  $RegisterAnzahl = "0003";   // Hex
  $DatenTyp = "Hex";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Seriennummer"] = substr($Ergebnis["Wert"],0,12);
    $funktionen->log_schreiben( "Seriennummer: ".$aktuelleDaten["Seriennummer"], "   ", 5 );

  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  $RegisterAdresse = 4103 + $Shift;    // Hex 1007
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "U8";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Portnummer"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  $RegisterAdresse = (4104 + $Shift);  // Hex 1008 
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV_Spannung"] = ($Ergebnis["Wert"]/10);
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  $RegisterAdresse = (4106 + $Shift);  // Hex 100A
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV_Strom"] = $Ergebnis["Wert"]/100;
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (4108 + $Shift);  // Hex 100C
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["AC_Spannung"] = $Ergebnis["Wert"]/10;
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (4110 + $Shift);  // Hex 100E
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Frequenz"] = $Ergebnis["Wert"]/100;
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (4112 + $Shift);  // Hex 1010
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "U32";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["PV_Leistung"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (4114 + $Shift);  // Hex 1012
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["WattstundenGesamtHeute"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  $RegisterAdresse = (4116 + $Shift);  // Hex 1014
  $RegisterAnzahl = "0002";   // Hex
  $DatenTyp = "U32";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Energie_Total"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (4120 + $Shift);  // Hex 1018
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Temperatur"] = $Ergebnis["Wert"]/10;
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (4122 + $Shift);  // Hex 1042
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Status"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }

  $RegisterAdresse = (4124 + $Shift);  // Hex 1044
  $RegisterAnzahl = "0001";   // Hex
  $DatenTyp = "U16";
  $Ergebnis = $funktionen->modbus_tcp_lesen( $COM1, $GeraeteAdresse, $FunktionsCode, $RegisterAdresse, $RegisterAnzahl, $DatenTyp, $Timebase );
  if (is_array( $Ergebnis )) {
    $aktuelleDaten["Fehler"] = $Ergebnis["Wert"];
  }
  else {
    $funktionen->log_schreiben( "Lesefehler => Ausgang.", "   ", 5 );
    goto Ausgang;
  }


  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/



  /***************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  ***************************************************************************/
  $FehlermeldungText = "";

  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;
  $aktuelleDaten["Produkt"] = "Hoymiles";
  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"] + 10);
  $funktionen->log_schreiben( var_export( $aktuelleDaten, 1 ), "   ", 8 );


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if (file_exists( "/var/www/html/hoymiles_math.php" )) {
    include 'hoymiles_math.php'; // Falls etwas neu berechnet werden muss.
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
    $Zeitspanne = (7 - (time( ) - $Start));
    $funktionen->log_schreiben( "Multi-Regler-Ausgang. ".$Zeitspanne, "   ", 2 );
    if ($Zeitspanne > 0) {
      sleep( $Zeitspanne );
    }
    break;
  }
  else {
    $funktionen->log_schreiben( "Schleife: ".($i)." Zeitspanne: ".(floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1))), "   ", 9 );
    sleep( floor( (56 - (time( ) - $Start)) / ($Wiederholungen - $i + 1)));
  }
  if ($Wiederholungen <= $i or $i >= 1) {
    //  Die RCT Wechselrichter dürfen nur einmal pro Minute ausgelesen werden!
    $funktionen->log_schreiben( "Schleife ".$i." Ausgang...", "   ", 5 );
    break;
  }
  $i++;
} while (($Start + 54) > time( ));

/*********************************************************************
//  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
//  übertragen.
*********************************************************************/
if (isset($Homematic) and $Homematic == true) {
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
/***/
Ausgang:
/***/
fclose( $COM1 );
$funktionen->log_schreiben( "-------------   Stop   hoymiles.php    -------------------------- ", "|--", 6 );
return;
?>
