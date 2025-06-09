<?php
// application/private/disponibilita.php
// CRUD fasce_disponibilita (gestione disponibilità settimanali delle sale)
// Regola aggiunta: **non è possibile inserire/modificare una fascia che si sovrappone
//                  (anche solo parzialmente) ad un’altra fascia nella stessa sala e stesso giorno.**

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

function handleDisponibilita(bool &$show, string &$bodyHtml): void
{
    $show     = false;
    $bodyHtml = '';

    /* pagina */
    if (($_GET['page'] ?? '') !== 'disponibilita') {
        return;
    }
    $show = true;

    /* sessione + fisioterapista loggato (0 = admin / tutti) */
    if (session_status() === PHP_SESSION_NONE) session_start();
    $fisioId = (int) ($_SESSION['fisio'] ?? 0);

    /* flash */
    $flash    = $_SESSION['disp_flash'] ?? '';
    unset($_SESSION['disp_flash']);
    $sqlError = $_SESSION['disp_error'] ?? '';
    unset($_SESSION['disp_error']);

    /* connessione */
    $db = Db::getConnection();
    $db->set_charset('utf8');

    /* ───── Mapping giorni enum ⇄ label ───── */
    $giorniEnum  = ['lun','mar','mer','gio','ven','sab'];
    $giorniLabel = ['Lunedì','Martedì','Mercoledì','Giovedì','Venerdì','Sabato'];
    $enum2lbl    = array_combine($giorniEnum,  $giorniLabel);
    $lbl2enum    = array_combine($giorniLabel, $giorniEnum);

    /* variabili form */
    $old_id = $old_sala = 0;
    $old_giorno = $old_inizio = $old_fine = '';
    $label_submit = 'Aggiungi';
    $messaggio    = '';

    /* ───── DELETE ───── */
    if (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $db->query("DELETE FROM fasce_disponibilita WHERE fascia_id = $id");
        $_SESSION['disp_flash'] = '<div class="alert alert-success">Disponibilità eliminata.</div>';
        header('Location: index.php?page=disponibilita');
        exit;
    }

    /* ───── EDIT (popola form) ───── */
    if (($_GET['action'] ?? '') === 'edit' && isset($_GET['id'])) {
        $id = (int)$_GET['id'];
        $r  = $db->query("SELECT sala_id, giorno, inizio, fine FROM fasce_disponibilita WHERE fascia_id=$id LIMIT 1")
                ->fetch_assoc();
        if ($r) {
            $old_id     = $id;
            $old_sala   = (int)$r['sala_id'];
            $old_giorno = $enum2lbl[$r['giorno']] ?? '';
            $old_inizio = substr($r['inizio'],0,5);
            $old_fine   = substr($r['fine'],  0,5);
            $label_submit = 'Modifica';
        }
    }

    /* ───── SAVE (INSERT/UPDATE) ───── */
    if (($_GET['action'] ?? '') === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $fid    = (int)($_POST['fascia_id'] ?? 0);
        $sala   = (int)($_POST['sala_id']   ?? 0);
        $giorno = trim($_POST['giorno']     ?? '');
        $inizio = trim($_POST['inizio']     ?? '');
        $fine   = trim($_POST['fine']       ?? '');
        // aggiunge i secondi se mancano
        if (strlen($inizio) === 5) $inizio .= ':00';
        if (strlen($fine)   === 5) $fine   .= ':00';


        /* Validazione campi base */
        if ($sala <= 0 || $giorno === '' || $inizio === '' || $fine === '') {
            $messaggio = '<div class="alert alert-danger">Compila tutti i campi obbligatori.</div>';
        } else {
            $giornoEnum = $lbl2enum[$giorno] ?? null;
            if (!$giornoEnum) {
                $messaggio = '<div class="alert alert-danger">Giorno non valido.</div>';
            } elseif ($inizio >= $fine) {
                $messaggio = '<div class="alert alert-danger">L\'orario di inizio deve precedere l\'orario di fine.</div>';
            } else {
                /* ➜ VERIFICA SOVRAPPOSIZIONE (stessa sala + giorno) */
                $inSql = $db->real_escape_string($inizio);
                $fiSql = $db->real_escape_string($fine);

                $checkSql = "SELECT 1 FROM fasce_disponibilita
                              WHERE sala_id = $sala
                                AND giorno   = '$giornoEnum'
                                AND fascia_id <> $fid
                                /* overlap: start < existing_end  AND  end > existing_start */
                                AND NOT ('$inSql' >= fine OR '$fiSql' <= inizio)
                              LIMIT 1";
                $hasOverlap = ($db->query($checkSql)->num_rows > 0);

                if ($hasOverlap) {
                    $messaggio = '<div class="alert alert-danger">Esiste già una disponibilità per questa sala che si sovrappone all\'intervallo indicato.</div>';
                } else {
                    /* Query INSERT o UPDATE */
                    $exists = false;
                    if ($fid > 0) {
                        $result = $db->query("SELECT * FROM fasce_disponibilita WHERE fascia_id = $fid");
                        $exists = ($result && $result->num_rows > 0);
                    }
                    if ($exists) {
                        $sql = "UPDATE fasce_disponibilita
                                SET sala_id = $sala, giorno = '$giornoEnum', inizio = '$inSql', fine = '$fiSql'
                                WHERE fascia_id = $fid";
                    } else {
                        $sql = "INSERT INTO fasce_disponibilita (fisioterapista_id, sala_id, giorno, inizio, fine)
                                VALUES ($fisioId, $sala, '$giornoEnum', '$inSql', '$fiSql')";
                    }
                    if ($db->query($sql)) {
                        $_SESSION['disp_flash'] = '<div class="alert alert-success">Disponibilità salvata.</div>';
                        header('Location: index.php?page=disponibilita');
                        exit;
                    }
                    $messaggio = '<div class="alert alert-danger">Errore SQL: '
                               . htmlspecialchars($db->error, ENT_QUOTES) . '</div>';
                }
            }
        }

        /* se siamo qui il salvataggio è fallito, mantieni valori */
        $old_id     = $fid;
        $old_sala   = $sala;
        $old_giorno = $giorno;
        $old_inizio = $inizio;
        $old_fine   = $fine;
        $label_submit = $fid ? 'Modifica' : 'Aggiungi';
    }

    /* ───── Dropdown SALE ───── */
    $lista_sale = '';
    $rsSale = $db->query("SELECT sala_id, nome_sala FROM sale ORDER BY nome_sala");
    while ($s = $rsSale->fetch_assoc()) {
        $sel = ((int)$s['sala_id'] === $old_sala) ? 'selected' : '';
        $lista_sale .= "<option value='{$s['sala_id']}' $sel>".htmlspecialchars($s['nome_sala'], ENT_QUOTES)."</option>";
    }

    /* ───── Lista disponibilità ───── */
    $lista_disp = '';
    $whereFisio = $fisioId ? "WHERE f.fisioterapista_id = $fisioId" : '';
    $sqlList = "SELECT f.fascia_id, s.nome_sala, f.giorno, f.inizio, f.fine
                FROM   fasce_disponibilita f
                JOIN   sale s ON f.sala_id = s.sala_id
                $whereFisio
                ORDER  BY FIELD(f.giorno,'lun','mar','mer','gio','ven','sab'), f.inizio";
    $rl = $db->query($sqlList);
    while ($row = $rl->fetch_assoc()) {
        $fid = (int)$row['fascia_id'];
        $lista_disp .= "<tr>
            <td>".htmlspecialchars($row['nome_sala'], ENT_QUOTES)."</td>
            <td>".($enum2lbl[$row['giorno']] ?? $row['giorno'])."</td>
            <td>".substr($row['inizio'],0,5)."</td>
            <td>".substr($row['fine'],0,5)."</td>
            <td>
              <a href='index.php?page=disponibilita&action=edit&id=$fid' class='btn btn-outline-modern btn-xs me-1'><i class='fas fa-edit'></i></a>
              <a href='index.php?page=disponibilita&action=delete&id=$fid' class='btn btn-outline-modern btn-xs text-danger' onclick='return confirm(\"Eliminare questa fascia?\");'><i class='fas fa-trash-alt'></i></a>
            </td>
          </tr>";
    }
    if ($lista_disp === '') {
        $lista_disp = "<tr><td colspan='5' class='text-center text-muted'>Nessuna disponibilità inserita.</td></tr>";
    }

    /* ───── Selettori giorno (selected) ───── */
    $selDay = array_fill_keys($giorniEnum, '');
    if ($old_giorno !== '' && isset($lbl2enum[$old_giorno])) {
        $selDay[$lbl2enum[$old_giorno]] = 'selected';
    }

    /* ───── Template ───── */
    $tpl = new Template('dtml/webarch/disponibilita');
    $tpl->setContent('messaggio_form', $messaggio ?: $flash . ($sqlError ? '<div class="alert alert-danger">'.$sqlError.'</div>' : ''));
    $tpl->setContent('label_submit',   $label_submit);
    $tpl->setContent('lista_sale',     $lista_sale);
    foreach ($giorniEnum as $g) {
        $tpl->setContent('sel_'.$g, $selDay[$g]);
    }
    $tpl->setContent('old_inizio', htmlspecialchars($old_inizio, ENT_QUOTES));
    $tpl->setContent('old_fine',   htmlspecialchars($old_fine,   ENT_QUOTES));
    $tpl->setContent('old_fascia_id', $old_id);
    $tpl->setContent('lista_disponibilita', $lista_disp);

    $bodyHtml = $tpl->get();
}
?>
