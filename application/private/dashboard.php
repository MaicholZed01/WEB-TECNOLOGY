<?php
// dashboard.php
// Controller per dashboard.html

session_start();

// -----------------------------------------------------------------------------
// 1. Connessione al database
// -----------------------------------------------------------------------------
$host     = 'localhost';
$username = 'TUO_USERNAME';
$password = 'TUA_PASSWORD';
$database = 'my_lazzarini21';

$mysqli = new mysqli($host, $username, $password, $database);
if ($mysqli->connect_errno) {
    die('Errore di connessione al database: ' . $mysqli->connect_error);
}
$mysqli->set_charset('utf8');

// -----------------------------------------------------------------------------
// 2. Variabili / funzioni utili
// -----------------------------------------------------------------------------
$preview_appuntamenti = '';
$preview_richieste    = '';
$preview_messaggi     = '';

/** Escape di sicurezza */
function esc($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/** Badge CSS dal testo dello stato */
function badgeClass($stato) {
    return 'status-' . strtolower(str_replace(' ', '-', $stato));
}

// -----------------------------------------------------------------------------
// 3. Appuntamenti di OGGI  (max 5 righe)
// -----------------------------------------------------------------------------
$sqlApp = "
  SELECT
    a.appuntamento_id,
    TIME(a.prenotato_il)                       AS ora_app,
    r.nome                                     AS nome_cli,
    r.cognome                                  AS cognome_cli,
    s.nome                                     AS servizio_nome,
    a.stato
  FROM appuntamenti AS a
  LEFT JOIN richieste AS r ON a.richiesta_id = r.richiesta_id
  LEFT JOIN servizi     AS s ON a.servizio_id  = s.servizio_id
  WHERE DATE(a.prenotato_il) = CURDATE()
  ORDER BY a.prenotato_il
  LIMIT 5
";
$res = $mysqli->query($sqlApp);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $aid      = (int)$row['appuntamento_id'];
        $ora      = esc(substr($row['ora_app'], 0, 5));
        $cliente  = esc($row['nome_cli'] . ' ' . $row['cognome_cli']);
        $servizio = esc($row['servizio_nome']);
        $stato    = esc($row['stato']);
        $badge    = badgeClass($stato);

        $preview_appuntamenti .= "
          <tr>
            <td><strong>{$ora}</strong></td>
            <td>{$cliente}</td>
            <td>{$servizio}</td>
            <td><span class=\"status-badge {$badge}\">{$stato}</span></td>
            <td>
              <a href=\"index.php?page=appuntamenti&action=view&id={$aid}\" 
                 class=\"btn btn-primary-modern btn-xs\">
                <i class=\"fas fa-eye\"></i>
              </a>
            </td>
          </tr>
        ";
    }
    $res->free();
} else {
    $preview_appuntamenti = '
      <tr><td colspan="5" class="text-center">Nessun appuntamento per oggi.</td></tr>';
}

// -----------------------------------------------------------------------------
// 4. Richieste in arrivo  (ultime 5)
// -----------------------------------------------------------------------------
$sqlRich = "
  SELECT 
    r.richiesta_id,
    DATE_FORMAT(r.data_richiesta,'%d/%m')       AS data_short,
    r.nome,
    r.cognome,
    s.nome                                       AS servizio_nome
  FROM richieste AS r
  LEFT JOIN servizi AS s ON r.servizio_id = s.servizio_id
  ORDER BY r.data_richiesta DESC
  LIMIT 5
";
$res = $mysqli->query($sqlRich);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $rid      = (int)$row['richiesta_id'];
        $data     = esc($row['data_short']);
        $cliente  = esc($row['nome'] . ' ' . mb_substr($row['cognome'], 0, 1) . '.');
        $servizio = esc($row['servizio_nome']);

        $preview_richieste .= "
          <tr>
            <td><small>{$data}</small></td>
            <td>{$cliente}</td>
            <td>{$servizio}</td>
            <td>
              <a href=\"index.php?page=richieste&action=view&id={$rid}\"
                 class=\"btn btn-primary-modern btn-xs\">
                <i class=\"fas fa-eye\"></i>
              </a>
            </td>
          </tr>
        ";
    }
    $res->free();
} else {
    $preview_richieste = '
      <tr><td colspan="4" class="text-center">Nessuna richiesta recente.</td></tr>';
}

// -----------------------------------------------------------------------------
// 5. Messaggi NON letti  (ultimi 5)
// -----------------------------------------------------------------------------
$sqlMsg = "
  SELECT 
    m.messaggio_id,
    m.oggetto,
    LEFT(m.contenuto, 60)   AS snippet,
    CONCAT(mittente_nome,' ',mittente_cognome) AS mittente,
    DATE_FORMAT(m.inviato_il,'%d/%m/%Y %H:%i') AS inviato_il
  FROM messaggi AS m
  WHERE m.letto = 0
  ORDER BY m.inviato_il DESC
  LIMIT 5
";
$res = $mysqli->query($sqlMsg);
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        $mid     = (int)$row['messaggio_id'];
        $mitt    = esc($row['mittente']);
        $oggetto = esc($row['oggetto']);
        $snip    = esc($row['snippet']);
        $time    = esc($row['inviato_il']);

        $preview_messaggi .= "
          <div class=\"notification-item unread mb-3\">
            <div class=\"d-flex justify-content-between align-items-start\">
              <div>
                <strong>{$oggetto}</strong>
                <p class=\"mb-1\">{$mitt}: {$snip}…</p>
                <small class=\"notification-time\">{$time}</small>
              </div>
              <a href=\"index.php?page=messaggi&action=view&id={$mid}\" 
                 class=\"btn btn-primary-modern btn-xs\">Apri</a>
            </div>
          </div>
        ";
    }
    $res->free();
} else {
    $preview_messaggi = '<p class="text-muted mb-0">Nessun messaggio non letto.</p>';
}

// -----------------------------------------------------------------------------
// 6. Carico il template dashboard.html
// -----------------------------------------------------------------------------
$template_path = __DIR__ . '/dashboard.html';
$template = file_get_contents($template_path);
if ($template === false) {
    die('Impossibile caricare il template dashboard.html');
}

// -----------------------------------------------------------------------------
// 7. Sostituisco i placeholder
// -----------------------------------------------------------------------------
$output = str_replace(
    [
      '<[preview_appuntamenti]>',
      '<[preview_richieste]>',
      '<[preview_messaggi]>'
    ],
    [
      $preview_appuntamenti,
      $preview_richieste,
      $preview_messaggi
    ],
    $template
);

// -----------------------------------------------------------------------------
// 8. Output finale
// -----------------------------------------------------------------------------
echo $output;

// -----------------------------------------------------------------------------
// 9. Chiudo connessione
// -----------------------------------------------------------------------------
$mysqli->close();
?>
