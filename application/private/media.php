<?php
// application/private/media.php

require_once __DIR__ . '/../include/dbms.inc.php';
require_once __DIR__ . '/../include/template2.inc.php';

/**
 * Ritorna tutte le righe di “media” per il fisioterapista $fisioId.
 * In caso di errore SQL, restituisce array vuoto e salva l’errore in sessione.
 */
function listAllMedia(int $fisioId): array {
    $conn = Db::getConnection();
    $rows = [];

    // Seleziono media_id, tipo, descrizione, url e caricato_il formattato
    $res = $conn->query("
        SELECT 
            media_id, 
            tipo, 
            descrizione, 
            url AS file_url, 
            DATE_FORMAT(caricato_il, '%Y-%m-%d %H:%i') AS data_caricamento
        FROM media
        WHERE fisioterapista_id = $fisioId
        ORDER BY media_id DESC
    ");

    if ($res === false) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['media_sql_error'] = "Errore SQL: " . $conn->error;
        return [];
    }

    while ($r = $res->fetch_assoc()) {
        $rows[] = $r;
    }
    return $rows;
}

/**
 * handleMedia(&$showMedia, &$bodyHtmlMedia)
 *   - Se $_GET['page']=='media', imposta $showMedia=true.
 *   - Gestisce upload (inserimento) e delete.
 *   - Usa Post-Redirect-Get per evitare duplicati e flash message per alert.
 *   - Alla fine popola il template “dtml/webarch/media.html”.
 */
function handleMedia(bool &$showMedia, string &$bodyHtmlMedia): void {
    $showMedia     = false;
    $bodyHtmlMedia = '';

    if (!isset($_GET['page']) || $_GET['page'] !== 'media') {
        return;
    }
    $showMedia = true;

        // Avvia la sessione se non è già partita
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Se non siamo loggati, redirigo al login
    if (empty($_SESSION['fisio'])) {
        header('Location: index.php?page=login');
        exit;
    }

    // Prendo l’ID del fisioterapista dalla sessione
    $fisioId = (int) $_SESSION['fisio'];


    $flash = $_SESSION['media_flash'] ?? '';
    unset($_SESSION['media_flash']);

    // 3) Verifico se c'è un errore SQL da mostrare
    $sqlError = $_SESSION['media_sql_error'] ?? '';
    unset($_SESSION['media_sql_error']);

    // 4) Variabili per il template
    $messaggio            = $flash;
    if ($sqlError) {
        $messaggio .= "<div class=\"alert alert-danger\">{$sqlError}</div>";
    }
    $old_tipo             = '';
    $sel_immagine         = '';
    $sel_video            = '';
    $sel_documento        = '';
    $old_descrizione      = '';

    // 5) Decodifico azione
    $action = $_GET['action'] ?? '';

    // 6) Connessione DB
    $conn = Db::getConnection();

    // ──────────────────────────────────────────────────────────────
    // 7) DELETE
    // ──────────────────────────────────────────────────────────────
    if ($action === 'delete' && isset($_GET['id'])) {
        $mid = (int) $_GET['id'];
        // Verifico che il record appartenga al fisioterapista
        $check = $conn->query("
            SELECT media_id
            FROM media
            WHERE media_id = $mid
              AND fisioterapista_id = $fisioId
        ");
        if ($check && $check->num_rows === 1) {
            // Prima elimino fisicamente il file (se esiste)
            $resFile = $conn->query("
                SELECT url
                FROM media
                WHERE media_id = $mid
                  AND fisioterapista_id = $fisioId
                LIMIT 1
            ");
            if ($resFile && $resFile->num_rows === 1) {
                $rFile = $resFile->fetch_assoc();
                $path = __DIR__ . '/../' . $rFile['url'];
                if (file_exists($path)) {
                    @unlink($path);
                }
            }
            // Quindi elimino la riga da DB
            $conn->query("DELETE FROM media WHERE media_id = $mid");

            $_SESSION['media_flash'] = '<div class="alert alert-success">File rimosso correttamente.</div>';
            header('Location: index.php?page=media');
            exit;
        } else {
            $messaggio .= '<div class="alert alert-danger">Impossibile eliminare: file non trovato.</div>';
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 8) UPLOAD (action=upload, POST con enctype multipart)
    // ──────────────────────────────────────────────────────────────
    if ($action === 'upload' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // 8a) Validazione lato server
        $tipo         = trim($_POST['tipo'] ?? '');
        $descrizione  = $conn->real_escape_string(trim($_POST['descrizione'] ?? ''));
        $fileInfo     = $_FILES['file_media'] ?? null;

        if ($tipo === '' || !$fileInfo || $fileInfo['error'] !== UPLOAD_ERR_OK) {
            $messaggio .= '<div class="alert alert-danger">Tipo e file sono obbligatori.</div>';
            $old_tipo = $tipo;
            $old_descrizione = htmlspecialchars($_POST['descrizione'] ?? '', ENT_QUOTES);
        } else {
            // 8b) Controllo dimensione max 5MB
            if ($fileInfo['size'] > 5 * 1024 * 1024) {
                $messaggio .= '<div class="alert alert-danger">File troppo grande (max 5MB).</div>';
                $old_tipo = $tipo;
                $old_descrizione = htmlspecialchars($_POST['descrizione'] ?? '', ENT_QUOTES);
            } else {
                // 8c) Determino cartella di upload in base al tipo
                $baseDir = __DIR__ . '/../upload/media/';
                if ($tipo === 'Immagine') {
                    $subDir = 'images/';
                } elseif ($tipo === 'Video') {
                    $subDir = 'videos/';
                } else {
                    $subDir = 'docs/';
                }
                $targetDir = $baseDir . $subDir;
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0755, true);
                }
                // 8d) Genero un nome univoco
                $originalName = basename($fileInfo['name']);
                $ext = pathinfo($originalName, PATHINFO_EXTENSION);
                $unique = uniqid('media_') . '.' . $ext;
                $fullPathServer = $targetDir . $unique;
                $relativePath   = "upload/media/" . $subDir . $unique;

                // 8e) Muovo il file
                if (move_uploaded_file($fileInfo['tmp_name'], $fullPathServer)) {
                    // 8f) Inserisco record nel DB, usando la colonna “url”
                    $sql = "
                        INSERT INTO media
                        (fisioterapista_id, tipo, descrizione, url, caricato_il)
                        VALUES (
                          $fisioId,
                          '$tipo',
                          '" . ($descrizione ? $descrizione : '') . "',
                          '$relativePath',
                          NOW()
                        )
                    ";
                    if ($conn->query($sql)) {
                        $_SESSION['media_flash'] = '<div class="alert alert-success">File caricato correttamente.</div>';
                        header('Location: index.php?page=media');
                        exit;
                    } else {
                        // In caso di errore DB, elimino il file fisico
                        @unlink($fullPathServer);
                        $messaggio .= '<div class="alert alert-danger">Errore DB: impossibile salvare record.</div>';
                        $old_tipo        = $tipo;
                        $old_descrizione = htmlspecialchars($_POST['descrizione'] ?? '', ENT_QUOTES);
                    }
                } else {
                    $messaggio .= '<div class="alert alert-danger">Errore durante lo spostamento del file.</div>';
                    $old_tipo = $tipo;
                    $old_descrizione = htmlspecialchars($_POST['descrizione'] ?? '', ENT_QUOTES);
                }
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 9) Recupero sempre le righe aggiornate
    // ──────────────────────────────────────────────────────────────
    $rows = listAllMedia($fisioId);

    // 10) Costruisco stringa delle righe e punto di debug
     $lista_media = '';
$numRows     = count($rows);
$debugComment = "<!-- listAllMedia ha trovato {$numRows} righe per fisioId={$fisioId} -->";

foreach ($rows as $row) {
    $mid       = (int) $row['media_id'];
    $tipoVoce  = htmlspecialchars($row['tipo'], ENT_QUOTES);
    $descrVoce = htmlspecialchars($row['descrizione'], ENT_QUOTES);
    $fileUrl   = htmlspecialchars($row['file_url'], ENT_QUOTES);
    $dataCaric = htmlspecialchars($row['data_caricamento'], ENT_QUOTES);

    // Ora l’URL dovrà includere “application/” come prefisso
    $fullUrl = "/tec-web/application/{$fileUrl}";

    if ($row['tipo'] === 'Immagine') {
        $anteprima = "
          <a href=\"{$fullUrl}\" target=\"_blank\">
            <img src=\"{$fullUrl}\" 
                 class=\"img-responsive\" 
                 style=\"max-height:60px;\" 
                 alt=\"Anteprima\">
          </a>
        ";
    } elseif ($row['tipo'] === 'Video') {
        $anteprima = "
          <a href=\"{$fullUrl}\" target=\"_blank\">
            <span class=\"glyphicon glyphicon-film\"></span>
          </a>
        ";
    } else {
        $anteprima = "
          <a href=\"{$fullUrl}\" target=\"_blank\">
            <span class=\"glyphicon glyphicon-file\"></span>
          </a>
        ";
    }

    $lista_media .= "
      <tr>
        <td>{$anteprima}</td>
        <td>{$tipoVoce}</td>
        <td>{$descrVoce}</td>
        <td>{$dataCaric}</td>
        <td>
          <a href=\"index.php?page=media&action=delete&id={$mid}\" 
             class=\"btn btn-danger btn-xs\" 
             onclick=\"return confirm('Eliminare questo file?');\">
            Elimina
          </a>
        </td>
      </tr>
    ";
}

    // ──────────────────────────────────────────────────────────────
    // 11) Selezioni nel dropdown tipo
    // ──────────────────────────────────────────────────────────────
    if ($old_tipo === 'Immagine') {
        $sel_immagine  = 'selected';
    } elseif ($old_tipo === 'Video') {
        $sel_video     = 'selected';
    } elseif ($old_tipo === 'Documento') {
        $sel_documento = 'selected';
    }

    // ──────────────────────────────────────────────────────────────
    // 12) Carico il template e popolo i placeholder
    // ──────────────────────────────────────────────────────────────
    $tpl = new Template('dtml/webarch/media');
    $tpl->setContent('messaggio_form',   $messaggio);
    $tpl->setContent('sel_immagine',      $sel_immagine);
    $tpl->setContent('sel_video',         $sel_video);
    $tpl->setContent('sel_documento',     $sel_documento);
    $tpl->setContent('old_descrizione',   $old_descrizione);
    $tpl->setContent('lista_media',       $lista_media);
    $tpl->setContent('debug_comment',     $debugComment);

    $bodyHtmlMedia = $tpl->get();
}
?>