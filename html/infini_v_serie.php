#!/usr/bin/php
<?php

/*****************************************************************************
//  Solaranzeige Projekt             Copyright (C) [2016-2020]  [Ulrich Kunz]
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
//  Es dient dem Auslesen des Victron-energy Reglers über die USB Schnittstelle
//  Das Auslesen wird hier mit einer Schleife durchgeführt. Wie oft die Daten
//  ausgelesen und gespeichert werden steht in der user.config.php
//  Protokoll Pl18
//
//  Achtung! Der Regler sendet zwischendurch immer wieder asynchrone Daten!
//
*****************************************************************************/
$path_parts = pathinfo($argv[0]);
$Pfad = $path_parts['dirname'];
if (!is_file($Pfad."/1.user.config.php")) {
  // Handelt es sich um ein Multi Regler System?
  require($Pfad."/user.config.php");
}

require_once($Pfad."/phpinc/funktionen.inc.php");
if (!isset($funktionen)) {
  $funktionen = new funktionen();
}
// Im Fall, dass man die Device manuell eingeben muss
if (isset($USBDevice) and !empty($USBDevice)) {
  $USBRegler = $USBDevice;
}

$Tracelevel = 7;  //  1 bis 10  10 = Debug
$RemoteDaten = true;
$Device = "WR"; // WR = Wechselrichter
$Version = "";
$Start = time();  // Timestamp festhalten
$funktionen->log_schreiben("-------------   Start  infini_v_serie.php  ------------------- ","|--",6);

$funktionen->log_schreiben("Zentraler Timestamp: ".$zentralerTimestamp,"   ",8);
$aktuelleDaten = array();
$aktuelleDaten["zentralerTimestamp"] = $zentralerTimestamp;


setlocale(LC_TIME,"de_DE.utf8");


//  Hardware Version ermitteln.
$Teile =  explode(" ",$Platine);
if ($Teile[1] == "Pi") {
  $Version = trim($Teile[2]);
  if ($Teile[3] == "Model") {
    $Version .= trim($Teile[4]);
    if ($Teile[5] == "Plus") {
      $Version .= trim($Teile[5]);
    }
  }
}
$funktionen->log_schreiben("Hardware Version: ".$Version,"o  ",8);

switch($Version) {
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

/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//
*****************************************************************************/
$StatusFile = $Pfad."/database/".$GeraeteNummer.".WhProTag.txt";
if (!file_exists($StatusFile)) {
  /***************************************************************************
  //  Inhalt der Status Datei anlegen, wenn nicht existiert.
  ***************************************************************************/
  $rc = file_put_contents($StatusFile,"0");
  if ($rc === false) {
    $funktionen->log_schreiben("Konnte die Datei whProTag_inf.txt nicht anlegen.",5);
  }
  $aktuelleDaten["WattstundenGesamtHeute"] = 0;
}
else {
  $aktuelleDaten["WattstundenGesamtHeute"] = file_get_contents($StatusFile);
  $funktionen->log_schreiben("WattstundenGesamtHeute: ".$aktuelleDaten["WattstundenGesamtHeute"],"   ",8);
}


$USB1 = $funktionen->openUSB($USBRegler);
if (!is_resource($USB1)) {
  $funktionen->log_schreiben("USB Port kann nicht geöffnet werden. [1]","XX ",7);
  $funktionen->log_schreiben("Exit.... ","XX ",7);
  goto Ausgang;
}

$i = 1;
do {
  $funktionen->log_schreiben("Die Daten werden ausgelesen...","+  ",9);

  /****************************************************************************
  //  Ab hier wird der Regler ausgelesen.
  //
  //  Ergebniswerte:
  //  $aktuelleDaten["Firmware"]                Nummer
  //  $aktuelleDaten["Produkt"]                 Text
  //  $aktuelleDaten["Objekt"]                  Text
  //  $aktuelleDaten["Batteriespannung"]
  //  $aktuelleDaten["Batterieentladestrom"]
  //  $aktuelleDaten["Batterieladestrom"]
  //  $aktuelleDaten["Ladestatus"]
  //  $aktuelleDaten["Solarstrom"]
  //  $aktuelleDaten["Solarspannung"]
  //  $aktuelleDaten["Solarleistung"]
  //  $aktuelleDaten["WattstundenGesamt"]
  //  $aktuelleDaten["WattstundenGesamtHeute"]
  //  $aktuelleDaten["WattstundenGesamtGestern"]
  //  $aktuelleDaten["maxWattHeute"]
  //  $aktuelleDaten["maxAmpHeute"]
  //  $aktuelleDaten["Temperatur"]
  //  $aktuelleDaten["Optionen"]
  //  $aktuelleDaten["ErrorCodes"]
  //
  ****************************************************************************/

  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P005GS";  //  Auslesen der aktuellen Wechselrichter Daten
    $RAW_daten = $funktionen->infini_lesen($USB1, $Befehl);
    if ($RAW_daten === false) {
      $funktionen->log_schreiben("Continue: ".$Befehl,"o  ",8);
      continue;
    }
    $aktuelleDaten = array_merge($aktuelleDaten, $funktionen->infini_entschluesseln(substr($Befehl,5),$RAW_daten));  
    break;
  }
  $funktionen->log_schreiben($RAW_daten,"   ",7);


  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P005PI";  //  Auslesen der Firmware angeben
    $RAW_daten = $funktionen->infini_lesen($USB1, $Befehl);
    if ($RAW_daten === false) {
      $funktionen->log_schreiben("Continue: ".$Befehl,"o  ",8);
      continue;
    }
    $aktuelleDaten = array_merge($aktuelleDaten, $funktionen->infini_entschluesseln(substr($Befehl,5),$RAW_daten));  
    break;
  }

  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P004T";  //  Auslesen der aktuellen Zeit
    $RAW_daten = $funktionen->infini_lesen($USB1, $Befehl);
    if ($RAW_daten === false) {
      $funktionen->log_schreiben("Continue: ".$Befehl,"o  ",8);
      continue;
    }
    $aktuelleDaten = array_merge($aktuelleDaten, $funktionen->infini_entschluesseln(substr($Befehl,5),$RAW_daten));  
    break;
  }

  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P006MOD";  //  Auslesen des aktuellen Modus
    $RAW_daten = $funktionen->infini_lesen($USB1, $Befehl);
    if ($RAW_daten === false) {
      $funktionen->log_schreiben("Continue: ".$Befehl,"o  ",8);
      continue;
    }
    $aktuelleDaten = array_merge($aktuelleDaten, $funktionen->infini_entschluesseln(substr($Befehl,5),$RAW_daten));  
    break;
  }

  for ($k = 1; $k < 3; $k++) {
    $Befehl = "^P006FWS";  //  Auslesen der Fehlercodes und Warnungen
    $RAW_daten = $funktionen->infini_lesen($USB1, $Befehl);
    if ($RAW_daten === false) {
      $funktionen->log_schreiben("Continue: ".$Befehl,"o  ",8);
      continue;
    }
    $aktuelleDaten = array_merge($aktuelleDaten, $funktionen->infini_entschluesseln(substr($Befehl,5),$RAW_daten));  
    $aktuelleDaten["Optionen"] = substr($RAW_daten,3);
    break;
  }



  /****************************************************************************
  //  ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN      ENDE REGLER AUSLESEN
  ****************************************************************************/




  /****************************************************************************
  //  Die Daten werden für die Speicherung vorbereitet.
  ****************************************************************************/
  $aktuelleDaten["Regler"] = $Regler;
  $aktuelleDaten["Objekt"] = $Objekt;

  //  Nur einmal pro Minute berechnen!
  if ($i == 1 and $aktuelleDaten["Solarleistung1"] > 0) {
    $funktionen->log_schreiben("Leistung1: ".$aktuelleDaten["Solarleistung1"],"o  ",9);
    $aktuelleDaten["WattstundenGesamtHeute"] = ($aktuelleDaten["WattstundenGesamtHeute"] + ($aktuelleDaten["Solarleistung1"]/60) );
  }
  if ($i == 1 and $aktuelleDaten["Solarleistung2"] > 0) {
    $funktionen->log_schreiben("Solarleistung2: ".$aktuelleDaten["Solarleistung2"],"o  ",9);
    $aktuelleDaten["WattstundenGesamtHeute"] = ($aktuelleDaten["WattstundenGesamtHeute"] + ($aktuelleDaten["Solarleistung2"]/60) );
  }


  /**************************************************************************
  //  Falls ein ErrorCode vorliegt, wird er hier in einen lesbaren
  //  Text umgewandelt, sodass er als Fehlermeldung gesendet werden kann.
  //  Die Funktion ist noch nicht überall implementiert.
  **************************************************************************/
  $FehlermeldungText = "";

  if ($aktuelleDaten["Fehlercode"] > 0 )   {

    switch ($aktuelleDaten["Fehlercode"]) {
      case 1:
        $Text = "Der Lüfter ist blockiert.";
      break;
      case 2:
        $Text = "Temperatur des Gerätes ist zu hoch.";
      break;
      case 3:
        $Text = "Batteriespannung zu hoch.";
      break;
      case 4:
        $Text = "Batteriespannung zu niedrig.";
      break;
      case 5:
        $Text = "Kurzschluss Ausgangsspannung.";
      break;
      case 6:
        $Text = "Ausgangsspannung zu hoch.";
      break;
      case 7:
        $Text = "Over load time too high.";
      break;
      case 8:
        $Text = "BUS voltage is too high.";
      break;
      case 9:
        $Text = "BUS soft start failed.";
      break;
      case 11:
        $Text = "Main relay failed.";
      break;
      case 51:
        $Text = "Over current inverter.";
      break;
      case 52:
        $Text = "BUS soft start failed.";
      break;
      case 53:
        $Text = "Inverter soft start failed.";
      break;
      case 54:
        $Text = "Self test failed.";
      break;
      case 55:
        $Text = "Over DC voltage on outpt of inverter.";
      break;
      case 56:
        $Text = "Verbindung zur Batterie besteht nicht.";
      break;
      case 57:
        $Text = "Stromsensor Fehler.";
      break;
      case 58:
        $Text = "Ausgangsspannung zu niedrig.";
      break;
      case 60:
        $Text = "Inverter negaitv power.";
      break;
      case 71:
        $Text = "Palallel Version unterschiedlich.";
      break;
      case 72:
        $Text = "Ausgangssicherung fehlerhaft.";
      break;
      case 80:
        $Text = "CAN Kommunikationsfehler.";
      break;
      case 81:
        $Text = "Parallel host line lost.";
      break;
      default:
        $Text = "unbekannte Fehlernummer: ".$ErrorCode;
      break;
    }

    $FehlermeldungText = "Fehlermeldung: ".$Text;
    $funktionen->log_schreiben($FehlermeldungText,"** ",1);
  }

  $aktuelleDaten["zentralerTimestamp"] = ($aktuelleDaten["zentralerTimestamp"]+10);


  $funktionen->log_schreiben(var_export($aktuelleDaten,1),"   ",8);


  /****************************************************************************
  //  User PHP Script, falls gewünscht oder nötig
  ****************************************************************************/
  if ( file_exists ("/var/www/html/infini_v_serie_math.php")) {
    include 'infini_v_serie_math.php';  // Falls etwas neu berechnet werden muss.
  }



  /**************************************************************************
  //  Alle ausgelesenen Daten werden hier bei Bedarf als mqtt Messages
  //  an den mqtt-Broker Mosquitto gesendet.
  //  Achtung! Die Übertragung dauert ca. 30 Sekunden!
  **************************************************************************/
  if ($MQTT and strtoupper($MQTTAuswahl) != "OPENWB") {
    $funktionen->log_schreiben("MQTT Daten zum [ $MQTTBroker ] senden.","   ",1);
    require($Pfad."/mqtt_senden.php");
  }



  /****************************************************************************
  //  Zeit und Datum
  ****************************************************************************/
  //  Der Regler hat keine interne Uhr! Deshalb werden die Daten vom Raspberry benutzt.
  $aktuelleDaten["Timestamp"] = time();
  $aktuelleDaten["Monat"]     = date("n");
  $aktuelleDaten["Woche"]     = date("W");
  $aktuelleDaten["Wochentag"] = strftime("%A",time());
  $aktuelleDaten["Datum"]     = date("d.m.Y");
  $aktuelleDaten["Uhrzeit"]      = date("H:i:s");


  /****************************************************************************
  //  InfluxDB  Zugangsdaten ...stehen in der user.config.php
  //  falls nicht, sind das hier die default Werte.
  ****************************************************************************/
  $aktuelleDaten["InfluxAdresse"] = $InfluxAdresse;
  $aktuelleDaten["InfluxPort"] = $InfluxPort;
  $aktuelleDaten["InfluxUser"] =  $InfluxUser;
  $aktuelleDaten["InfluxPassword"] = $InfluxPassword;
  $aktuelleDaten["InfluxDBName"] = $InfluxDBName;
  $aktuelleDaten["InfluxDaylight"] = $InfluxDaylight;
  $aktuelleDaten["InfluxDBLokal"] = $InfluxDBLokal;
  $aktuelleDaten["InfluxSSL"] = $InfluxSSL;
  $aktuelleDaten["Demodaten"] = false;


  $aktuelleDaten["Solarleistung"] = ($aktuelleDaten["Solarleistung1"] + $aktuelleDaten["Solarleistung2"]);


  if ($aktuelleDaten["Warnungen"] > 0 or $aktuelleDaten["Fehlercode"] > 0) {
    $funktionen->log_schreiben("Fehlercode. ".$aktuelleDaten["Fehlercode"]." Warnung: ".$aktuelleDaten["Warnungen"],"*--",7);
  }



  /*********************************************************************
  //  Daten werden in die Influx Datenbank gespeichert.
  //  Lokal und Remote bei Bedarf.
  *********************************************************************/
  if ($InfluxDB_remote) {
    // Test ob die Remote Verbindung zur Verfügung steht.
    if ($RemoteDaten) {
      $rc = $funktionen->influx_remote_test();
      if ($rc) {
        $rc = $funktionen->influx_remote($aktuelleDaten);
        if ($rc) {
          $RemoteDaten = false;
        }
      }
      else {
        $RemoteDaten = false;
      }
    }
    if ($InfluxDB_local) {
      $rc = $funktionen->influx_local($aktuelleDaten);
    }
  }
  else {
    $rc = $funktionen->influx_local($aktuelleDaten);
  }



  if (is_file($Pfad."/1.user.config.php")) {
    // Ausgang Multi-Regler-Version
    $Zeitspanne = (7 - (time() - $Start));
    $funktionen->log_schreiben("Multi-Regler-Ausgang. ".$Zeitspanne,"   ",2);
    if ($Zeitspanne > 0) {
      sleep($Zeitspanne);
    }
    break;
  }
  else {
    $funktionen->log_schreiben("Schleife: ".($i)." Zeitspanne: ".(floor((56 - (time() - $Start))/($Wiederholungen-$i+1))),"   ",9);
    sleep(floor((56 - (time() - $Start))/($Wiederholungen-$i+1)));
  }
  if ($Wiederholungen <= $i or $i >= 6) {
    $funktionen->log_schreiben("Schleife ".$i." Ausgang...","   ",8);
    break;
  }

  $i++;
} while (($Start + 54) > time());


if (isset($aktuelleDaten["Firmware"]) and isset($aktuelleDaten["Regler"])) {


  /*********************************************************************
  //  Jede Minute werden bei Bedarf einige Werte zur Homematic Zentrale
  //  übertragen.
  *********************************************************************/
  if (isset($Homematic) and $Homematic == true) {
    $funktionen->log_schreiben("Daten werden zur HomeMatic übertragen...","   ",8);
    require($Pfad."/homematic.php");
  }

  /*********************************************************************
  //  Sollen Nachrichten an einen Messenger gesendet werden?
  //  Bei einer Multi-Regler-Version sollte diese Funktion nur bei einem
  //  Gerät aktiviert sein.
  *********************************************************************/
  if (isset($Messenger) and $Messenger == true) {
    $funktionen->log_schreiben("Nachrichten versenden...","   ",8);
    require($Pfad."/meldungen_senden.php");
  }

  $funktionen->log_schreiben("OK. Datenübertragung erfolgreich.","   ",7);
}
else {
  $funktionen->log_schreiben("Keine gültigen Daten empfangen.","!! ",6);
}


/*****************************************************************************
//  Die Status Datei wird dazu benutzt, um die Leistung des Reglers
//  pro Tag zu speichern.
//  Jede Nacht 0 Uhr Tageszähler setzen
*****************************************************************************/
$aktuelleDaten["WattstundenGesamtHeute"] = round($aktuelleDaten["WattstundenGesamtHeute"],2);
if (date("H:i") == "00:00" or date("H:i") == "00:01") {
  $rc = file_put_contents($StatusFile, "0");
  $funktionen->log_schreiben("WattstundenGesamtHeute  gesetzt.","o--",5);
}
else {
  $rc = file_put_contents($StatusFile, $aktuelleDaten["WattstundenGesamtHeute"]);
}


Ausgang:

$funktionen->log_schreiben("-------------   Stop   infini_v_serie.php    ----------------- ","|--",6);

return;





?>

