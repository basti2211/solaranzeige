<?php
$HM_Var = array();
$HM_Var['GesamtBezug']     = ($aktuelleDaten['Wh_VerbrauchGesamt_R'] + $aktuelleDaten['Wh_VerbrauchGesamt_S'] + $aktuelleDaten['Wh_VerbrauchGesamt_T'])/1000.0;
$HM_Var['GesamtEinspeisung']   = ($aktuelleDaten['Wh_EinspeisungGesamt_R'] + $aktuelleDaten['Wh_EinspeisungGesamt_S'] + $aktuelleDaten['Wh_EinspeisungGesamt_T']) / 1000.0;
$HM_Var['GesamtWirkleistung'] = ($aktuelleDaten['Wirkleistung_R'] + $aktuelleDaten['Wirkleistung_S'] + $aktuelleDaten['Wirkleistung_T']) /1000.0;
?>
