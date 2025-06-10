<?php
// application/public/news-detail.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * handleNewsDetail(&$showDetail, &$bodyHtmlDetail)
 *   - Se $_GET['page']=='news-detail', imposta $showDetail = true.
 *   - Legge $_GET['id'], recupera i dati dell’annuncio e dell’autore.
 *   - Costruisce i pulsanti di share e la lista “Altri avvisi”.
 */
function handleNewsDetail(bool &$showDetail, string &$bodyHtmlDetail): void {
    $showDetail      = false;
    $bodyHtmlDetail  = '';

    if (!isset($_GET['page']) || $_GET['page'] !== 'news-detail') {
        return;
    }
    $showDetail = true;

    // 1) Leggi e valida l’ID dall’URL
    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        header('Location: index.php?page=avvisi');
        exit;
    }

    $conn = Db::getConnection();

    // 2) Recupera i dati dell’annuncio e dell’autore (JOIN con fisioterapisti)
    $sql = "
        SELECT
          a.titolo            AS titolo_avviso,
          a.contenuto         AS contenuto_avviso,
          a.pubblicato_il     AS data_raw,
          f.nome              AS autore_nome,
          f.cognome           AS autore_cognome,
          f.bio               AS autore_bio
        FROM annunci AS a
        LEFT JOIN fisioterapisti AS f
          ON a.fisioteraprista_id = f.fisioterapista_id
        WHERE a.annuncio_id = $id
        LIMIT 1
    ";
    $res = $conn->query($sql);
    if ($res === false || $res->num_rows === 0) {
        // Annuncio non trovato: torno ad avvisi
        header('Location: index.php?page=avvisi');
        exit;
    }
    $row = $res->fetch_assoc();

    // Sanitizza i campi
    $titolo_avviso    = htmlspecialchars($row['titolo_avviso'], ENT_QUOTES);
    // Converte nuovo paragrafo
    $contenuto_avviso = nl2br(htmlspecialchars($row['contenuto_avviso'], ENT_QUOTES));
    // Formatto la data
    $data_obj         = date_create($row['data_raw']);
    $data_avviso      = $data_obj ? date_format($data_obj, 'j F Y') : '';
    $autore_nome      = htmlspecialchars($row['autore_nome']    ?? 'Staff', ENT_QUOTES);
    $autore_cognome   = htmlspecialchars($row['autore_cognome'] ?? '', ENT_QUOTES);
    $autore_bio       = nl2br(htmlspecialchars($row['autore_bio'] ?? '')); // se vuoto, rimane stringa vuota

    // 3) Costruisco i link di “share”
    //    Usiamo l’URL completo corrente:
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $currentUrl = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $encodedUrl = urlencode($currentUrl);

    // Facebook sharer
    $share_fb = "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}";
    // Twitter tweet
    $share_tw = "https://twitter.com/intent/tweet?url={$encodedUrl}&text=" . urlencode($titolo_avviso);
    // LinkedIn share
    $share_li = "https://www.linkedin.com/sharing/share-offsite/?url={$encodedUrl}";

    // 4) Recupero “Altri avvisi”: gli ultimi 5 annunci diversi da questo
    $rec_sql = "
        SELECT
          annuncio_id,
          titolo,
          DATE_FORMAT(pubblicato_il, '%d/%m/%Y') AS data_breve
        FROM annunci
        WHERE annuncio_id <> $id
        ORDER BY pubblicato_il DESC
        LIMIT 5
    ";
    $rec_res = $conn->query($rec_sql);
    $htmlRecenti = '';
    if ($rec_res === false) {
        $htmlRecenti = '<div class="text-danger small">Errore nel caricamento degli altri avvisi.</div>';
    } elseif ($rec_res->num_rows === 0) {
        $htmlRecenti = '<div class="text-muted small">Nessun altro avviso disponibile.</div>';
    } else {
        while ($r = $rec_res->fetch_assoc()) {
            $aid       = (int) $r['annuncio_id'];
            $tit_breve = htmlspecialchars($r['titolo'], ENT_QUOTES);
            $data_brev = htmlspecialchars($r['data_breve'], ENT_QUOTES);
            $link      = "index.php?page=news-detail&id={$aid}";

            $htmlRecenti .= "
              <div class=\"recent-post-item mb-2\">
                <a href=\"{$link}\">
                  <h5 class=\"mb-1\">{$tit_breve}</h5>
                  <small class=\"text-muted\"><i class=\"fa fa-calendar\"></i> {$data_brev}</small>
                </a>
              </div>
            ";
        }
    }

    // costruzione url immagine news
    $img_avviso = '/tec-web/application/dtml/2098_health/images/news-image' . $id % 4 . '.jpg';

    // 5) Carico il template ‘news-detail.html’ e sostituisco i placeholder
    $tpl = new Template('dtml/2098_health/news-detail');
    $tpl->setContent('img_avviso',            $img_avviso);
    $tpl->setContent('titolo_avviso',         $titolo_avviso);
    $tpl->setContent('data_avviso',           $data_avviso);
    $tpl->setContent('autore_nome',           $autore_nome);
    $tpl->setContent('autore_cognome',        $autore_cognome);
    $tpl->setContent('contenuto_avviso',      $contenuto_avviso);
    $tpl->setContent('share_fb',              $share_fb);
    $tpl->setContent('share_tw',              $share_tw);
    $tpl->setContent('share_li',              $share_li);
    $tpl->setContent('autore_bio',            $autore_bio);
    $tpl->setContent('recenti_avvisi',        $htmlRecenti);

    $bodyHtmlDetail = $tpl->get();
}
?>