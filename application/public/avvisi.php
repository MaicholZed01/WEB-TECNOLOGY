<?php
// application/public/avvisi.php

require __DIR__ . '/../include/dbms.inc.php';
require __DIR__ . '/../include/template2.inc.php';
require __DIR__ . '/../logic/Annunci.php';

$main = new Template("application/dtml/2098_health/frame");
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    // Visualizza dettaglio avviso
    $idAvviso = intval($_GET['id']);
    $annuncio = Annunci::getById($idAvviso);
    if (!$annuncio) {
        header("Location: index.php?page=avvisi");
        exit;
    }
    $body = new Template("application/dtml/2098_health/news-detail");

    // Popola i dati principali
    $dataFmt = date("d/m/Y", strtotime($annuncio['pubblicato_il']));
    $body->setContent("titolo_avviso", htmlspecialchars($annuncio['titolo']));
    $body->setContent("data_avviso", $dataFmt);
    $body->setContent("contenuto_avviso", nl2br(htmlspecialchars($annuncio['contenuto'])));
    // Se in futuro implementi immagini, usa 'url_immagine'; altrimenti puoi lasciare un'immagine di default:
    $body->setContent("img_avviso", htmlspecialchars($annuncio['url_immagine'] ?? "/tec-web/application/dtml/2098_health/images/default-news.jpg"));

    // Autore: prendi fisioterapista collegato
    $autore = Annunci::getAutore($idAvviso);
    if ($autore) {
        $body->setContent("autore_nome", htmlspecialchars($autore['nome']));
        $body->setContent("autore_cognome", htmlspecialchars($autore['cognome']));
        $body->setContent("autore_bio", nl2br(htmlspecialchars($autore['bio'])));
    } else {
        $body->setContent("autore_nome", "Sconosciuto");
        $body->setContent("autore_cognome", "");
        $body->setContent("autore_bio", "Nessuna descrizione disponibile.");
    }

    // Recenti avvisi (escludi questo)
    $recenti = Annunci::listRecentExcluding($idAvviso, 5);
    $htmlRecenti = "";
    foreach ($recenti as $r) {
        $dataR = date("d/m/Y", strtotime($r['pubblicato_il']));
        $htmlRecenti .= "
          <div class=\"recent-post-item mb-2\">
            <a href=\"index.php?page=avvisi&id={$r['annuncio_id']}\">
              <h5>".htmlspecialchars($r['titolo'])."</h5>
              <small class=\"text-muted\"><i class=\"fa fa-calendar\"></i> $dataR</small>
            </a>
          </div>
        ";
    }
    $body->setContent("recenti_avvisi", $htmlRecenti);

    // Se non gestisci categorie/tag, lasciali vuoti
    $body->setContent("lista_categorie", "");
    $body->setContent("lista_tag", "");

    // Link di condivisione (esempio generico)
    $urlCorrente = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $body->setContent("share_fb", "https://www.facebook.com/sharer/sharer.php?u=".urlencode($urlCorrente));
    $body->setContent("share_tw", "https://twitter.com/intent/tweet?url=".urlencode($urlCorrente));
    $body->setContent("share_li", "https://www.linkedin.com/shareArticle?mini=true&url=".urlencode($urlCorrente));

    $main->setContent("body", $body->get());
    $main->close();
}
else {
    // Elenco avvisi
    $tuttiAvvisi = Annunci::listAll();
    $body = new Template("application/dtml/2098_health/avvisi"); // template avvisi.html

    $htmlLista = "";
    foreach ($tuttiAvvisi as $a) {
        $dataA = date("d/m/Y", strtotime($a['pubblicato_il']));
        $htmlLista .= "
          <div class=\"col-md-4 col-sm-6 mb-4\">
            <div class=\"card h-100\">
              <div class=\"card-body\">
                <h5 class=\"card-title\">".htmlspecialchars($a['titolo'])."</h5>
                <p class=\"card-text text-muted\"><i class=\"fa fa-calendar\"></i> $dataA</p>
                <p class=\"card-text\">".nl2br(htmlspecialchars(substr($a['contenuto'],0,100)))."…</p>
                <a href=\"index.php?page=avvisi&id={$a['annuncio_id']}\" class=\"btn btn-primary btn-sm\">Leggi&nbsp;di&nbsp;più</a>
              </div>
            </div>
          </div>
        ";
    }
    $body->setContent("lista_avvisi", $htmlLista);

    $main->setContent("body", $body->get());
    $main->close();
}
?>