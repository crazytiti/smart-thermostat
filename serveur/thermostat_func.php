<?php

  $sqlite = '/home/pi/thermostat/thermostat.sqlite';

  //
  //  enregistre une mesure de température
  //
  function putTemp ($temp, $consigne, $timestamp) {
    global $sqlite;
    $db = new SQLite3($sqlite);
    $db->exec('CREATE TABLE IF NOT EXISTS temp (timestamp INTEGER, temp FLOAT, consigne FLOAT);'); // cree la table temp si elle n'existe pas
    $data = array();
    $data['timestamp'] = $timestamp;
    $data['temp']  = $temp;
	$data['consigne']  = $consigne;	
    if($db->busyTimeout(5000)){ // stock les donnees
      $db->exec("INSERT INTO temp (timestamp, temp, consigne) VALUES ('".$data['timestamp']."', '".$data['temp']."', '".$data['consigne']."');");
	  echo "OK";
	}
    return 1;
  }

  //
  //	genere le tableau d'historique de température
  //
  function getTempGraph ($date_debut, $date_fin) {
    global $sqlite;
	$nb_point = 1500;	//nombre de point du graphe
    $now  = time();
    $past = strtotime($date_debut);
	$present = strtotime($date_fin);

    $db = new SQLite3($sqlite);
	$results = $db->querySingle("SELECT count(timestamp) FROM temp WHERE timestamp > $past AND timestamp < $present ORDER BY timestamp ASC;");
	$numRows = $results;
	$modulo_nb_point = $nb_point / $numRows;
	$nb_point_effectif = 1;
	$num_point = 0;
	$results = $db->query("SELECT (timestamp ) as timestamp, temp, consigne FROM temp WHERE timestamp > $past AND timestamp < $present ORDER BY timestamp ASC;");
	$data = array();
	while($row = $results->fetchArray(SQLITE3_ASSOC)){
		//modulo pour faire "sauter" des points
		$num_point += $modulo_nb_point;
		if ($num_point < $nb_point_effectif){
			continue;
		}
		$nb_point_effectif++;
		$year   = date("Y", $row['timestamp']);
		$month = date("n", $row['timestamp'])-1;
		$day    = date("j", $row['timestamp']);
		$hour   = date("G", $row['timestamp']);
		$minute = date("i", $row['timestamp']);
		$second = date("s", $row['timestamp']);
		$temp_indicator ='color: #e0440e';
		$consigne_indicator = 'color: #375D81';
	  
		$data[] = "[{v:new Date($year, $month, $day, $hour, $minute, $second), f:'".date("j", $row['timestamp'])." ".date("M", $row['timestamp'])." ".date("H\hi", $row['timestamp'])."'}, 
                  {v:".$row['temp'].", f:'".$row['temp']." °c'}, '".$temp_indicator."', {v:".$row['consigne'].", f:'".$row['consigne']." °c'}]";
    }

    return implode(', ', $data);
  }
  
    //
  //	calcul le nombre d'heure de chauffage sur la période indiquée
  //
  function getHeatTime ($date_debut, $date_fin) {
    global $sqlite;
    $past = strtotime($date_debut);
	$present = strtotime($date_fin);
	
    $db = new SQLite3($sqlite);
	$results = $db->query("SELECT round(count(timestamp)/60.0,2) as heattime FROM temp WHERE timestamp > $past AND timestamp < $present AND consigne > temp;");
	$data = array();
    $row = $results->fetchArray(SQLITE3_ASSOC);
    $data[] = $row['heattime'];	
    return implode(', ', $data); 
  }
  
  //
  // recupere la dernierre température pour faire du "temps réel"
  //
  function getActualTemp() {
	global $sqlite;
    $db = new SQLite3($sqlite);
    $results = $db->query("select max(timestamp) as timestamp,temp from temp;");
    $data = array();
    $row = $results->fetchArray(SQLITE3_ASSOC);
    $data[] = $row['temp'];	
    return implode(', ', $data); 
  }

  //
  //	récupere un champ de la table config
  //
  function getconfig($champ) {
	global $sqlite;
    $db = new SQLite3($sqlite); 
	$db->exec('CREATE TABLE IF NOT EXISTS config (config_key INTEGER, mode TEXT, T_manuelle FLOAT, N_planning integer, calibration FLOAT, hysteresis FLOAT, fuseau INTEGER, rechargement INTEGER, UNIQUE(config_key));'); // cree la table config si elle n'existe pas
	$results = $db->query("select " . $champ . " from config;");
    $data = array();
    $row = $results->fetchArray(SQLITE3_ASSOC);
    $data[] = $row[$champ];	
    return implode(', ', $data); 
  }
  
  //
  //	enregistre un champ de la table config
  //	+ met le flag rechargement à 1
  //
  function setconfig($champ, $value) {
	global $sqlite;
    $db = new SQLite3($sqlite); 
	$db->exec('INSERT OR IGNORE INTO config (config_key, ' . $champ . ') Values (1, "' . $value . '"); UPDATE config SET rechargement = 1 WHERE config_key = 1; UPDATE config SET ' . $champ . ' = "' . $value . '" WHERE config_key = 1;'); 
	return 1;
	}
  
  //
  //	page de configuration du planning : enregistrement d'un jour
  //	+ met le flag rechargement à 1
  //
  function configPlanning ($N_planning, $Jour, $H0, $T0, $H1, $T1, $H2, $T2, $H3, $T3, $H4, $T4, $H5, $T5
	, $H6, $T6, $H7, $T7, $H8, $T8, $H9, $T9) {
    global $sqlite;
    $db = new SQLite3($sqlite);
    $db->exec('CREATE TABLE IF NOT EXISTS planning (N_planning INTEGER, nom TEXT, Jour INTEGER, H0 DATE, T0 FLOAT, H1 DATE, T1 FLOAT, H2 DATE, T2 FLOAT, H3 DATE, T3 FLOAT, H4 DATE, T4 FLOAT, H5 DATE, T5 FLOAT, H6 DATE, T6 FLOAT, H7 DATE, T7 FLOAT, H8 DATE, T8 FLOAT, H9 DATE, T9 FLOAT, UNIQUE(N_planning, Jour) );'); // cree la table planning si elle n'existe pas
	$db->exec('INSERT OR IGNORE INTO planning (N_planning, Jour, H0, T0, H1, T1, H2, T2, H3, T3, H4, T4, H5, T5, H6, T6, H7, T7, H8, T8, H9, T9) 
	Values (' .$N_planning. ', "' . $Jour . '", "' .$H0. '", "' . $T0 . '", "' .$H1. '", "' . $T1 . '", "' .$H2. '", "' . $T2 . '", "' .$H3. '", "' . $T3 . '", "' .$H4. '", "' . $T4 . '", "' .$H5. '", "' . $T5 . '", "' .$H6. '", "' . $T6 . '", "' .$H7. '", "' . $T7 . '", "' .$H8. '", "' . $T8 . '", "' .$H9. '", "' . $T9 . '"); 
	UPDATE config SET rechargement = 1 WHERE config_key = 1;
	UPDATE planning SET H0 = "' . $H0 . '", T0 = "' . $T0 . '", H1 = "' . $H1 . '", T1 = "' . $T1 . '", H2 = "' . $H2 . '", T2 = "' . $T2 . '", H3 = "' . $H3 . '", T3 = "' . $T3 . '", H4 = "' . $H4 . '", T4 = "' . $T4 . '", H5 = "' . $H5 . '", T5 = "' . $T5 . '", H6 = "' . $H6 . '", T6 = "' . $T6 . '", H7 = "' . $H7 . '", T7 = "' . $T7 . '", H8 = "' . $H8 . '", T8 = "' . $T8 . '", H9 = "' . $H9 . '", T9 = "' . $T9 . '"  
	WHERE N_planning = ' .$N_planning. ' AND Jour = "' . $Jour . '";'); 	
	return 1;	
  }
  
  //
  //	renvoie le planning d'un jour en fonction du planning selectionné
  //
  function getPlanningDay ($Jour, $N_planning) {
    global $sqlite;
    $db = new SQLite3($sqlite);
	$db->exec('CREATE TABLE IF NOT EXISTS planning (N_planning INTEGER, nom TEXT, Jour INTEGER, H0 DATE, T0 FLOAT, H1 DATE, T1 FLOAT, H2 DATE, T2 FLOAT, H3 DATE, T3 FLOAT, H4 DATE, T4 FLOAT, H5 DATE, T5 FLOAT, H6 DATE, T6 FLOAT, H7 DATE, T7 FLOAT, H8 DATE, T8 FLOAT, H9 DATE, T9 FLOAT, UNIQUE(N_planning, Jour) );'); // cree la table planning si elle n'existe pas
	$results = $db->query("select * from planning WHERE N_planning = " . $N_planning . " AND Jour = " . $Jour . " ;");
	$row = $results->fetchArray(SQLITE3_ASSOC);
    return $row;
  }
  
  //
  //	renvoie le planning d'un jour en fonction du planning selectionné
  //
  function getPlanningDayImplode ($Jour, $N_planning) {
    global $sqlite;
    $db = new SQLite3($sqlite);
	$db->exec('CREATE TABLE IF NOT EXISTS planning (N_planning INTEGER, nom TEXT, Jour INTEGER, H0 DATE, T0 FLOAT, H1 DATE, T1 FLOAT, H2 DATE, T2 FLOAT, H3 DATE, T3 FLOAT, H4 DATE, T4 FLOAT, H5 DATE, T5 FLOAT, H6 DATE, T6 FLOAT, H7 DATE, T7 FLOAT, H8 DATE, T8 FLOAT, H9 DATE, T9 FLOAT, UNIQUE(N_planning, Jour) );'); // cree la table planning si elle n'existe pas
	$results = $db->query("select * from planning WHERE N_planning = " . $N_planning . " AND Jour = " . $Jour . " ;");
	$row = $results->fetchArray(SQLITE3_ASSOC);
    return implode(', ', $row);
  }
?>
