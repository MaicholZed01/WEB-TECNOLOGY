<?php
// application/public/dettagli_fisioterapista.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * handleDettagliFisio(&$showDetail, &$bodyHtmlDetail)
 *   - Se $_GET['page']=='dettagli_fisioterapista', imposta $showDetail=true.
 *   - Legge il fisioterapista con id=$_GET['id']; in caso di errore redirect.
 *   - Recupera le recensioni collegate e costruisce il markup.
 */
function handleDettagliFisio(bool &$showDetail, string &$bodyHtmlDetail): void {
    $showDetail     = false;
    $bodyHtmlDetail = '';

    if (!isset($_GET['page']) || $_GET['page'] !== 'dettagli_fisioterapista') {
        return;
    }
    $showDetail = true;

    // 1) Leggo e valido 'id'
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        // Redirect alla lista se id non valido
        header('Location: index.php?page=fisioterapisti');
        exit;
    }

    $conn = Db::getConnection();

    // 2) Recupero i dati del fisioterapista
    $resF = $conn->query("
        SELECT
          nome,
          cognome,
          telefono,
          email,
          bio,
          anni_esperienza,
          tariffa_oraria,
          url_foto_profilo
        FROM fisioterapisti
        WHERE fisioterapista_id = $id
        LIMIT 1
    ");
    if ($resF === false || $resF->num_rows === 0) {
        // Se l’ID non esiste, torno alla lista
        header('Location: index.php?page=fisioterapisti');
        exit;
    }
    $rowF = $resF->fetch_assoc();
    $nome             = htmlspecialchars($rowF['nome'], ENT_QUOTES);
    $cognome          = htmlspecialchars($rowF['cognome'], ENT_QUOTES);
    $telefono         = htmlspecialchars($rowF['telefono'], ENT_QUOTES);
    $email            = htmlspecialchars($rowF['email'], ENT_QUOTES);
    $bio              = nl2br(htmlspecialchars($rowF['bio'], ENT_QUOTES));
    $anni             = (int) $rowF['anni_esperienza'];
    $tariffa          = htmlspecialchars($rowF['tariffa_oraria'], ENT_QUOTES);
    $urlFoto          = htmlspecialchars($rowF['url_foto_profilo'], ENT_QUOTES);

    // Se non esiste foto, puoi mettere un placeholder
    $srcFoto = $urlFoto !== ''
        ? "/tec-web/application/{$urlFoto}"
        : "/tec-web/application/upload/fisioterapisti/placeholder.jpg";

    // 3) Recupero le recensioni associate a questo fisioterapista
    $recRes = $conn->query("
        SELECT 
          r.valutazione,
          r.commento,
          DATE_FORMAT(r.creato_il, '%d/%m/%Y') AS data_recensione
        FROM recensioni AS r
        JOIN appuntamenti AS a ON r.appuntamento_id = a.appuntamento_id
        WHERE a.fisioterapista_id = $id
        ORDER BY r.creato_il DESC
    ");

    $htmlRecensioni     = '';
    $messaggioNoRec     = '';
    if ($recRes === false) {
        // In caso di errore SQL, mostro un messaggio generico
        $messaggioNoRec = '<div class="alert alert-danger">Errore nel caricamento delle recensioni.</div>';
    } elseif ($recRes->num_rows === 0) {
        $messaggioNoRec = 'Ancora nessuna recensione per questo fisioterapista.';
    } else {
        while ($rowR = $recRes->fetch_assoc()) {
            $valut = (int) $rowR['valutazione'];
            $comm  = nl2br(htmlspecialchars($rowR['commento'], ENT_QUOTES));
            $data  = htmlspecialchars($rowR['data_recensione'], ENT_QUOTES);

            // Costruisco le stelline
            $stars = '';
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $valut) {
                    $stars .= '<i class="fa fa-star text-warning"></i>';
                } else {
                    $stars .= '<i class="fa fa-star-o text-warning"></i>';
                }
            }

            $htmlRecensioni .= "
              <div class=\"review-item mb-4\">
                <div class=\"d-flex justify-content-between\">
                  <strong>Cliente</strong>  <!-- Non abbiamo nome/cognome cliente nel DB -->
                  <small class=\"text-muted\"><i class=\"fa fa-calendar\"></i> {$data}</small>
                </div>
                <div class=\"rating mb-2\">
                  {$stars}
                  <span class=\"ml-2\">({$valut})</span>
                </div>
                <p>{$comm}</p>
              </div>
            ";
        }
    }

    // 4) Carico il template 'dettagli_fisioterapista.html' e sostituisco placeholder
    $tpl = new Template('dtml/2098_health/dettagli_fisioterapista');
    $tpl->setContent('url_foto_profilo',   $srcFoto);
    $tpl->setContent('nome',               $nome);
    $tpl->setContent('cognome',            $cognome);
    $tpl->setContent('anni_esperienza',    $anni);
    $tpl->setContent('tariffa_oraria',     $tariffa);
    $tpl->setContent('telefono',           $telefono);
    $tpl->setContent('email',              $email);
    $tpl->setContent('bio',                $bio);
    $tpl->setContent('lista_recensioni',   $htmlRecensioni);
    $tpl->setContent('messaggio_no_recensioni', $messaggioNoRec);

    $bodyHtmlDetail = $tpl->get();
}
?>