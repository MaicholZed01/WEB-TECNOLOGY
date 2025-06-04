<?php
// application/public/index.php

require __DIR__ . '/../include/dbms.inc.php';
require __DIR__ . '/../include/template2.inc.php';
require __DIR__ . '/../logic/Annunci.php';
require __DIR__ . '/../logic/Fisioterapisti.php';
require __DIR__ . '/../logic/Servizi.php';

// Template principale (pubblico)
$main = new Template("application/dtml/2098_health/frame");
$body = new Template("application/dtml/2098_health/index");

// 1) Recupera ultimi 5 avvisi
$ultimiAvvisi = Annunci::listRecent(5);  // restituirà array con campi id, titolo, contenuto breve, data
$htmlAvvisi = "";
foreach ($ultimiAvvisi as $avviso) {
    $dataFmt = date("d/m/Y", strtotime($avviso['pubblicato_il']));
    $htmlAvvisi .= "
      <div class=\"col-md-4 col-sm-6 mb-4\">
        <div class=\"card h-100\">
          <div class=\"card-body\">
            <h5 class=\"card-title\">".htmlspecialchars($avviso['titolo'])."</h5>
            <p class=\"card-text text-muted\"><i class=\"fa fa-calendar\"></i> $dataFmt</p>
            <p class=\"card-text\">".nl2br(htmlspecialchars(substr($avviso['contenuto'],0,100)))."…</p>
            <a href=\"index.php?page=avvisi&id={$avviso['annuncio_id']}\" class=\"btn btn-primary btn-sm\">Leggi&nbsp;di&nbsp;più</a>
          </div>
        </div>
      </div>
    ";
}
$body->setContent("ultimi_avvisi", $htmlAvvisi);

// 2) Recupera primi 3 fisioterapisti (ordinati alfabeticamente)
$allFisio = Fisioterapisti::listAll();
$htmlFisio = "";
for ($i = 0; $i < min(3, count($allFisio)); $i++) {
    $f = $allFisio[$i];
    $htmlFisio .= "
      <div class=\"col-md-4 col-sm-6 mb-4 text-center\">
        <div class=\"card h-100\">
          <img src=\"".htmlspecialchars($f['url_foto_profilo'])."\" class=\"card-img-top img-circle mx-auto mt-3\" alt=\"{$f['nome']} {$f['cognome']}\" style=\"width:100px; height:100px; object-fit:cover;\">
          <div class=\"card-body\">
            <h5 class=\"card-title\">{$f['nome']} {$f['cognome']}</h5>
            <a href=\"index.php?page=fisioterapisti&id={$f['fisioterapista_id']}\" class=\"btn btn-primary btn-sm\">Dettagli</a>
          </div>
        </div>
      </div>
    ";
}
$body->setContent("ultimi_fisioterapisti", $htmlFisio);

// 3) Recupera primi 3 servizi
$allServizi = Servizi::listAll();
$htmlServizi = "";
for ($i = 0; $i < min(3, count($allServizi)); $i++) {
    $s = $allServizi[$i];
    $htmlServizi .= "
      <div class=\"col-md-4 col-sm-6 mb-4\">
        <div class=\"card h-100\">
          <div class=\"card-body text-center\">
            <h5 class=\"card-title\">".htmlspecialchars($s['nome'])."</h5>
            <p class=\"card-text\">".nl2br(htmlspecialchars(substr($s['descrizione'],0,80)))."…</p>
            <p class=\"card-text\"><strong>Durata:</strong> {$s['durata_minuti']} min</p>
            <p class=\"card-text\"><strong>Prezzo:</strong> € ".number_format($s['prezzo_base'],2)."</p>
          </div>
        </div>
      </div>
    ";
}
$body->setContent("ultimi_servizi", $htmlServizi);

// Render finale
$main->setContent("body", $body->get());
$main->close();
?>