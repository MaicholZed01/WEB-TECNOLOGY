<?php
// application/public/fisioterapisti.php

// Includi connessione DB e motore template
require "application/include/dbms.inc.php";        // definisce $conn come mysqli
require "application/include/template2.inc.php";   // definisce la classe Template

// Funzione per formattare la data in dd/mm/YYYY
function formattaData($datetime) {
    $dt = new DateTime($datetime);
    return $dt->format('d/m/Y');
}

// Controlla se è stato passato un ID per la pagina di dettaglio
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $fisio_id = intval($_GET['id']);

    // PREPARA ESECUZIONE QUERY PER I DATI DEL FISIOTERAPISTA
    $sql_fisio = "
        SELECT
            fisioterapisti.fisioterapista_id,
            fisioterapisti.nome,
            fisioterapisti.cognome,
            fisioterapisti.telefono,
            fisioterapisti.email,
            fisioterapisti.bio,
            fisioterapisti.anni_esperienza,
            fisioterapisti.tariffa_oraria,
            fisioterapisti.url_foto_profilo
        FROM fisioterapisti
        WHERE fisioterapisti.fisioterapista_id = $fisio_id
        LIMIT 1
    ";
    $res_fisio = mysqli_query($conn, $sql_fisio);
    if (!$res_fisio || mysqli_num_rows($res_fisio) === 0) {
        // Se ID non trovato, reindirizza alla lista fisioterapisti
        header("Location: index.php?page=fisioterapisti");
        exit;
    }
    $row_fisio = mysqli_fetch_assoc($res_fisio);

    // PREPARA TEMPLATE DETTAGLIO FISIOTERAPISTA
    $main = new Template("application/dtml/2098_health/frame");              // template principale public
    $body = new Template("application/dtml/2098_health/fisioterapista-detail"); // sottotemplate dettaglio

    // Popola i placeholder con i dati del fisioterapista
    $body->setContent("url_foto_profilo", htmlspecialchars($row_fisio['url_foto_profilo']));
    $body->setContent("nome", htmlspecialchars($row_fisio['nome']));
    $body->setContent("cognome", htmlspecialchars($row_fisio['cognome']));
    $body->setContent("anni_esperienza", intval($row_fisio['anni_esperienza']));
    $body->setContent("tariffa_oraria", number_format($row_fisio['tariffa_oraria'], 2));
    $body->setContent("telefono", htmlspecialchars($row_fisio['telefono']));
    $body->setContent("email", htmlspecialchars($row_fisio['email']));
    $body->setContent("bio", nl2br(htmlspecialchars($row_fisio['bio'])));

    // RECUPERA RECENSIONI COLLEGATE A QUESTO FISIOTERAPISTA
    $sql_rec = "
        SELECT
            rec.recensione_id,
            rec.valutazione,
            rec.commento,
            rec.creato_il AS data_recensione,
            req.nome AS cliente_nome,
            req.cognome AS cliente_cognome
        FROM recensioni AS rec
        JOIN appuntamenti AS app ON rec.appuntamento_id = app.appuntamento_id
        JOIN richieste AS req ON app.richiesta_id = req.richiesta_id
        WHERE app.fisioterapista_id = $fisio_id
        ORDER BY rec.creato_il DESC
    ";
    $res_rec = mysqli_query($conn, $sql_rec);

    if ($res_rec && mysqli_num_rows($res_rec) > 0) {
        $html_recensioni = "";
        while ($r = mysqli_fetch_assoc($res_rec)) {
            $data_fmt = formattaData($r['data_recensione']);
            $val = intval($r['valutazione']);
            // Costruisci stelle
            $stelle = "";
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $val) {
                    $stelle .= '<i class="fa fa-star text-warning"></i>';
                } else {
                    $stelle .= '<i class="fa fa-star-o text-warning"></i>';
                }
            }
            $html_recensioni .= '
            <div class="review-item mb-4">
              <div class="d-flex justify-content-between">
                <strong>' . htmlspecialchars($r['cliente_nome']) . ' ' . htmlspecialchars($r['cliente_cognome']) . '</strong>
                <small class="text-muted"><i class="fa fa-calendar"></i> ' . $data_fmt . '</small>
              </div>
              <div class="rating mb-2">
                ' . $stelle . ' <span class="ml-2">(' . $val . ')</span>
              </div>
              <p>' . nl2br(htmlspecialchars($r['commento'])) . '</p>
            </div>';
        }
        $body->setContent("lista_recensioni", $html_recensioni);
        $body->setContent("messaggio_no_recensioni", "");
    } else {
        // Nessuna recensione
        $body->setContent("lista_recensioni", "");
        $body->setContent("messaggio_no_recensioni", "Non ci sono ancora recensioni per questo fisioterapista.");
    }

    // Render finale
    $main->setContent("body", $body->get());
    $main->close();

} else {
    // LISTA COMPLETA DEI FISIOTERAPISTI
    $sql_all = "
        SELECT
            fisioterapisti.fisioterapista_id,
            fisioterapisti.nome,
            fisioterapisti.cognome,
            fisioterapisti.url_foto_profilo
        FROM fisioterapisti
        ORDER BY fisioterapisti.cognome, fisioterapisti.nome
    ";
    $res_all = mysqli_query($conn, $sql_all);

    // Prepara template lista
    $main = new Template("application/dtml/2098_health/frame");
    $body = new Template("application/dtml/2098_health/fisioterapisti");

    if ($res_all && mysqli_num_rows($res_all) > 0) {
        $html_lista = "";
        while ($row = mysqli_fetch_assoc($res_all)) {
            $id = intval($row['fisioterapista_id']);
            $nome = htmlspecialchars($row['nome']);
            $cognome = htmlspecialchars($row['cognome']);
            $foto = htmlspecialchars($row['url_foto_profilo']);
            // Costruisci un blocco per ciascun fisioterapista
            $html_lista .= '
            <div class="col-md-4 col-sm-6 mb-4 text-center">
              <div class="card">
                <img src="' . $foto . '" class="card-img-top img-responsive rounded-circle mx-auto mt-3" alt="Foto di ' . $nome . ' ' . $cognome . '" style="width:100px; height:100px; object-fit:cover;">
                <div class="card-body">
                  <h5 class="card-title">' . $nome . ' ' . $cognome . '</h5>
                  <a href="index.php?page=fisioterapisti&id=' . $id . '" class="btn btn-primary btn-sm">Dettagli</a>
                </div>
              </div>
            </div>';
        }
        $body->setContent("lista_fisioterapisti", $html_lista);
    } else {
        $body->setContent("lista_fisioterapisti", "<p>Nessun fisioterapista presente.</p>");
    }

    // Render finale
    $main->setContent("body", $body->get());
    $main->close();
}

?>
