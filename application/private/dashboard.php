<?php
// application/private/dashboard.php
// Dashboard controller – mostra riepilogo quotidiano (appuntamenti, richieste, messaggi)

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

function handleDashboard(bool &$show, string &$bodyHtml): void {
    $show = false;
    $bodyHtml = '';

    if (($_GET['page'] ?? '') !== 'dashboard') {
        return; // non siamo nella pagina dashboard
    }
    $show = true;

    if (session_status() === PHP_SESSION_NONE) session_start();
    // (eventuale) controllo login: se vuoi proteggere la dashboard, decommenta
    // if (empty($_SESSION['fisio'])) { header('Location: index.php?page=login'); exit; }

    // Connessione DB
    $db = Db::getConnection();
    $db->set_charset('utf8');

    /*******************************************************************
     * 1) Appuntamenti di OGGI (max 5)                                 *
     ******************************************************************/
    $today = date('Y-m-d');
    $rowsApp = '';
    $sqlApp = "SELECT a.appuntamento_id,
                      a.orario,
                      a.stato,
                      r.nome,
                      r.cognome,
                      s.nome AS servizio
               FROM   appuntamenti a
               JOIN   richieste r ON a.richiesta_id = r.richiesta_id
               JOIN   servizi   s ON a.servizio_id   = s.servizio_id
               WHERE  a.data = '$today'
               ORDER  BY a.orario ASC
               LIMIT  5";
    if ($res = $db->query($sqlApp)) {
        if ($res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $ora = substr($row['orario'], 0, 5);
                $cliente = htmlspecialchars($row['nome'] . ' ' . $row['cognome'], ENT_QUOTES);
                $servizio = htmlspecialchars($row['servizio'], ENT_QUOTES);
                $stato = htmlspecialchars($row['stato'], ENT_QUOTES);
                $badge = ($stato === 'Prenotato') ? 'status-confermato' : 'status-completato';

                $rowsApp .= "<tr>
                                <td><strong>{$ora}</strong></td>
                                <td>{$cliente}</td>
                                <td>{$servizio}</td>
                                <td><span class='status-badge {$badge}'>{$stato}</span></td>
                                <td>
                                  <a href='index.php?page=appuntamenti&action=view&id={$row['appuntamento_id']}'
                                     class='btn btn-primary-modern btn-xs'>
                                     <i class='fas fa-eye'></i>
                                  </a>
                                </td>
                              </tr>";
            }
        } else {
            $rowsApp = "<tr><td colspan='5' class='text-center text-muted'>Nessun appuntamento per oggi.</td></tr>";
        }
        $res->free();
    } else {
        $rowsApp = "<tr><td colspan='5' class='text-danger'>Errore DB: ".$db->error."</td></tr>";
    }

    /*******************************************************************
     * 2) Ultime richieste NON soddisfatte (max 5)                     *
     ******************************************************************/
    $rowsReq = '';
    $sqlReq = "SELECT r.richiesta_id,
                      DATE(r.creato_il) AS data_req,
                      r.nome,
                      r.cognome,
                      s.nome AS servizio
               FROM   richieste r
               LEFT   JOIN appuntamenti a ON r.richiesta_id = a.richiesta_id
               JOIN   servizi       s ON r.servizio_id  = s.servizio_id
               WHERE  a.richiesta_id IS NULL
               ORDER  BY r.creato_il DESC
               LIMIT  5";
    if ($res = $db->query($sqlReq)) {
        if ($res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $dataBreve = date('d/m', strtotime($row['data_req']));
                $cliente   = htmlspecialchars($row['nome'].' '.substr($row['cognome'],0,1).'.', ENT_QUOTES);
                $servizio  = htmlspecialchars($row['servizio'], ENT_QUOTES);
                $rowsReq  .= "<tr>
                                <td><small>{$dataBreve}</small></td>
                                <td>{$cliente}</td>
                                <td>{$servizio}</td>
                                <td>
                                   <a href='index.php?page=richieste&action=view&id={$row['richiesta_id']}'
                                      class='btn btn-primary-modern btn-xs'><i class='fas fa-eye'></i></a>
                                </td>
                              </tr>";
            }
        } else {
            $rowsReq = "<tr><td colspan='4' class='text-center text-muted'>Nessuna nuova richiesta.</td></tr>";
        }
        $res->free();
    } else {
        $rowsReq = "<tr><td colspan='4' class='text-danger'>Errore DB: ".$db->error."</td></tr>";
    }

    /*******************************************************************
     * 3) Ultimi messaggi (tabella messaggi semplificata)              *
     ******************************************************************/
    $rowsMsg = '';
    // Verifico che la tabella esista (nel dump è presente) – se no mostro placeholder
    $testMsgTbl = $db->query("SHOW TABLES LIKE 'messaggi'");
    if ($testMsgTbl && $testMsgTbl->num_rows === 1) {
        $sqlMsg = "SELECT messaggio_id, mittente, contenuto, inviato_il
                    FROM   messaggi
                    ORDER  BY messaggio_id DESC
                    LIMIT  5";
        if ($resM = $db->query($sqlMsg)) {
            if ($resM->num_rows > 0) {
                while ($row = $resM->fetch_assoc()) {
                    $time = date('d/m/Y H:i', strtotime($row['inviato_il']));
                    $mitt = htmlspecialchars($row['mittente'], ENT_QUOTES);
                    $snippet = htmlspecialchars(substr($row['contenuto'], 0, 60).'...', ENT_QUOTES);
                    $rowsMsg .= "<div class='notification-item'>
                                     <div class='d-flex justify-content-between align-items-start'>
                                       <div>
                                         <strong>{$mitt}</strong>
                                         <p class='mb-1'>{$snippet}</p>
                                         <small class='notification-time'>{$time}</small>
                                       </div>
                                       <a href='index.php?page=messaggi&action=view&id={$row['messaggio_id']}' class='btn btn-primary-modern btn-xs'>Apri</a>
                                     </div>
                                   </div>";
                }
            } else {
                $rowsMsg = "<p class='text-muted text-center mb-0'>Nessun messaggio presente.</p>";
            }
            $resM->free();
        } else {
            $rowsMsg = "<p class='text-danger text-center mb-0'>Errore DB messaggi: ".$db->error."</p>";
        }
    } else {
        $rowsMsg = "<p class='text-muted text-center mb-0'>Modulo messaggi non disponibile.</p>";
    }

    /*******************************************************************
     * 4) Render Template                                              *
     ******************************************************************/
    $tpl = new Template('dtml/webarch/dashboard');
    $tpl->setContent('preview_appuntamenti', $rowsApp);
    $tpl->setContent('preview_richieste',    $rowsReq);
    $tpl->setContent('preview_messaggi',     $rowsMsg);

    $bodyHtml = $tpl->get();
}
?>
