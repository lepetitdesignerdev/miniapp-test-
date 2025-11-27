<?php
session_start();

/**
 * CONFIG SIMPLE
 * -------------
 * Mot de passe admin à changer ici si tu veux.
 */
$ADMIN_PASSWORD = 'admin123'; // 🔐 CHANGE-LE ! 

$configFile   = __DIR__ . '/config.json';
$productsFile = __DIR__ . '/produits.json';
$chatFile     = __DIR__ . '/messages.json'; // 🔹 fichier du chat

$saveMessage = '';
$saveError   = '';
$skipSave    = false; // 🔹 pour ne pas lancer l'enregistrement produits quand on vide le chat

// ----------------------------------------------------
// LOGIN SIMPLE
// ----------------------------------------------------
if (!isset($_SESSION['is_admin'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($_POST['password'] === $ADMIN_PASSWORD) {
            $_SESSION['is_admin'] = true;
            header('Location: admin.php');
            exit;
        } else {
            $loginError = "Mot de passe incorrect.";
        }
    }

    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <meta charset="UTF-8">
        <title>Admin - Connexion</title>
        <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
        <style>
            *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
            html,body{height:100%}
            body{
                min-height:100vh;
                background:#111827;
                display:flex;
                align-items:center;
                justify-content:center;
                font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
                color:#e5e7eb;
            }
            .card{
                background:rgba(15,23,42,0.95);
                border:1px solid rgba(148,163,184,0.3);
                border-radius:12px;
                padding:20px;
                width:100%;
                max-width:360px;
                box-shadow:0 20px 40px rgba(0,0,0,0.6);
            }
            h1{
                font-size:1.3rem;
                margin-bottom:10px;
                text-align:center;
            }
            label{display:block;font-size:0.9rem;margin-bottom:4px;}
            input[type="password"]{
                width:100%;padding:8px 10px;border-radius:999px;
                border:1px solid #4b5563;background:#020617;color:#e5e7eb;
                margin-bottom:10px;
            }
            button{
                width:100%;padding:9px 12px;border-radius:999px;
                border:none;background:#0ea5e9;color:#f9fafb;
                font-weight:600;cursor:pointer;font-size:0.95rem;
            }
            button:hover{background:#0284c7;}
            .error{color:#f97373;font-size:0.85rem;margin-bottom:8px;text-align:center;}
            .hint{font-size:0.75rem;color:#9ca3af;text-align:center;margin-top:8px;}
        </style>
    </head>
    <body>
        <div class="card">
            <h1>Admin catalogue</h1>
            <?php if (!empty($loginError)): ?>
                <div class="error"><?= htmlspecialchars($loginError) ?></div>
            <?php endif; ?>
            <form method="post">
                <label for="password">Mot de passe</label>
                <input type="password" id="password" name="password" required>
                <button type="submit">Se connecter</button>
            </form>
            <div class="hint">Tu peux modifier le mot de passe dans admin.php (variable <code>$ADMIN_PASSWORD</code>).</div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ----------------------------------------------------
// FONCTIONS UTILES
// ----------------------------------------------------
function readJsonFile($path, $default = []) {
    if (!file_exists($path)) return $default;
    $content = file_get_contents($path);
    if ($content === false) return $default;
    $data = json_decode($content, true);
    if (!is_array($data)) return $default;
    return $data;
}

function writeJsonFile($path, $data) {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    return (bool)file_put_contents($path, $json, LOCK_EX);
}

// ----------------------------------------------------
// 🔹 VIDER L’HISTORIQUE DU CHAT
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clear_chat'])) {
    if (writeJsonFile($chatFile, [])) {
        $saveMessage = "✅ Historique du chat vidé.";
    } else {
        $saveError = "❌ Erreur lors de la suppression de l’historique du chat. Vérifie les permissions sur messages.json.";
    }
    $skipSave = true; // on ne fait pas la sauvegarde des produits dans cette requête
}

// ----------------------------------------------------
// CHARGEMENT CONFIG + PRODUITS
// ----------------------------------------------------
$config   = readJsonFile($configFile, [
    "siteName"             => "Ma boutique CBD",
    "tagline"              => "Catalogue officiel",
    "logoUrl"              => "logo.png",
    "catalogBackgroundUrl" => "background.jpg",
    "primaryColor"         => "#0088cc",
    "telegramContactUrl"   => "",
    "orderRedirectUrl"     => "",
    "orderRedirectLabel"   => "Envoyer ma commande",
    "infoText"             => "Clique sur un produit pour voir les grammages, les prix et l’ajouter au panier."
]);

$products = readJsonFile($productsFile, []);

// ----------------------------------------------------
// SAUVEGARDE (CONFIG + PRODUITS)
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_all']) && !$skipSave) {
    // 1) CONFIG
    $config['siteName']             = $_POST['siteName'] ?? $config['siteName'];
    $config['tagline']              = $_POST['tagline'] ?? '';
    $config['logoUrl']              = $_POST['logoUrl'] ?? '';
    $config['catalogBackgroundUrl'] = $_POST['catalogBackgroundUrl'] ?? '';
    $config['primaryColor']         = $_POST['primaryColor'] ?? '#0088cc';
    $config['telegramContactUrl']   = $_POST['telegramContactUrl'] ?? '';
    $config['orderRedirectUrl']     = $_POST['orderRedirectUrl'] ?? '';
    $config['orderRedirectLabel']   = $_POST['orderRedirectLabel'] ?? 'Envoyer ma commande';
    $config['infoText']             = $_POST['infoText'] ?? '';

    // 2) PRODUITS
    $postedProducts = $_POST['products'] ?? [];
    $newProducts = [];
    $maxId = 0;
    foreach ($products as $p) {
        if (isset($p['id']) && is_numeric($p['id']) && $p['id'] > $maxId) {
            $maxId = (int)$p['id'];
        }
    }

    foreach ($postedProducts as $p) {
        $nom = trim($p['nom'] ?? '');
        if ($nom === '') {
            // ligne vide = ignorée (sert aussi à SUPPRIMER un produit si on vide le nom)
            continue;
        }

        $id = isset($p['id']) && $p['id'] !== '' ? (int)$p['id'] : null;
        if ($id === null) {
            $id = ++$maxId;
        }

        $categorie = trim($p['categorie'] ?? '');
        $farm      = trim($p['farm'] ?? ''); // 🔹 producteur
        $image     = trim($p['image'] ?? '');
        $video_url = trim($p['video_url'] ?? '');
        $description = trim($p['description'] ?? '');
        $badgesStr  = trim($p['badges'] ?? '');
        $badges = [];
        if ($badgesStr !== '') {
            $parts = explode(',', $badgesStr);
            foreach ($parts as $b) {
                $bb = trim($b);
                if ($bb !== '') $badges[] = $bb;
            }
        }

        $featured = isset($p['featured']) && $p['featured'] === '1';
        $in_stock = isset($p['in_stock']) && $p['in_stock'] === '1';

        // prix_options
        $prixOptions = [];
        if (isset($p['prix_options']) && is_array($p['prix_options'])) {
            foreach ($p['prix_options'] as $opt) {
                $label = trim($opt['label'] ?? '');
                $prix  = isset($opt['prix']) ? floatval(str_replace(',', '.', $opt['prix'])) : 0;
                $promo = isset($opt['prix_promo']) && $opt['prix_promo'] !== ''
                    ? floatval(str_replace(',', '.', $opt['prix_promo']))
                    : null;
                if ($label === '' || $prix <= 0) continue;

                $entry = [
                    'label' => $label,
                    'prix'  => $prix
                ];
                if ($promo !== null && $promo > 0) {
                    $entry['prix_promo'] = $promo;
                }
                $prixOptions[] = $entry;
            }
        }

        $newProducts[] = [
            'id'          => $id,
            'nom'         => $nom,
            'categorie'   => $categorie,
            'farm'        => $farm,
            'image'       => $image,
            'video_url'   => $video_url,
            'description' => $description,
            'prix_options'=> $prixOptions,
            'badges'      => $badges,
            'featured'    => $featured,
            'in_stock'    => $in_stock
        ];
    }

    // TRI par id DESC comme sur ton index
    usort($newProducts, function($a,$b){
        return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
    });

    // Écrit les fichiers
    $ok1 = writeJsonFile($configFile, $config);
    $ok2 = writeJsonFile($productsFile, $newProducts);

    if ($ok1 && $ok2) {
        $saveMessage = "✅ Modifications enregistrées.";
        $products = $newProducts;
    } else {
        $saveError = "❌ Erreur lors de l’enregistrement. Vérifie les permissions des fichiers config.json et produits.json.";
    }
}

// Pour affichage : on reconstruit une version "form-friendly"
function productToForm($p) {
    return [
        'id'          => $p['id'] ?? '',
        'nom'         => $p['nom'] ?? '',
        'categorie'   => $p['categorie'] ?? '',
        'farm'        => $p['farm'] ?? '',
        'image'       => $p['image'] ?? '',
        'video_url'   => $p['video_url'] ?? '',
        'description' => $p['description'] ?? '',
        'badges'      => isset($p['badges']) && is_array($p['badges']) ? implode(', ', $p['badges']) : '',
        'featured'    => !empty($p['featured']),
        'in_stock'    => !empty($p['in_stock']),
        'prix_options'=> isset($p['prix_options']) && is_array($p['prix_options']) ? $p['prix_options'] : []
    ];
}

// Ajout d’un produit vierge en bas du formulaire (ligne vide pour créer un produit)
$productsForForm = array_map('productToForm', $products);
$productsForForm[] = productToForm([
    'id'          => '',
    'nom'         => '',
    'categorie'   => '',
    'farm'        => '',
    'image'       => '',
    'video_url'   => '',
    'description' => '',
    'badges'      => '',
    'featured'    => false,
    'in_stock'    => true,
    'prix_options'=> []
]);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Admin catalogue</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%}
    body{
      min-height:100vh;
      background:#020617;
      color:#e5e7eb;
      font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
      padding:16px;
    }
    a{color:#38bdf8;text-decoration:none;}
    a:hover{text-decoration:underline;}
    .layout{
      max-width:1100px;
      margin:0 auto;
    }
    h1{
      font-size:1.5rem;
      margin-bottom:8px;
    }
    .subtitle{
      font-size:0.9rem;
      color:#9ca3af;
      margin-bottom:14px;
    }
    .topbar{
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom:16px;
    }
    .btn-logout{
      padding:6px 12px;
      border-radius:999px;
      border:1px solid rgba(148,163,184,0.6);
      background:transparent;
      color:#e5e7eb;
      font-size:0.8rem;
      cursor:pointer;
    }
    .btn-logout:hover{background:rgba(148,163,184,0.15);}
    .grid{
      display:grid;
      grid-template-columns:1.2fr 2fr;
      gap:16px;
    }
    @media(max-width:900px){
      .grid{grid-template-columns:1fr;}
    }
    .card{
      background:rgba(15,23,42,0.95);
      border:1px solid rgba(148,163,184,0.3);
      border-radius:12px;
      padding:14px 14px 16px;
      box-shadow:0 15px 30px rgba(0,0,0,0.5);
      margin-bottom:12px;
    }
    .card h2{
      font-size:1rem;
      margin-bottom:8px;
    }
    .field{
      margin-bottom:8px;
    }
    .field label{
      display:block;
      font-size:0.8rem;
      margin-bottom:3px;
      color:#9ca3af;
    }
    .field input[type="text"],
    .field input[type="number"],
    .field textarea{
      width:100%;
      padding:6px 8px;
      border-radius:8px;
      border:1px solid #4b5563;
      background:#020617;
      color:#e5e7eb;
      font-size:0.85rem;
    }
    .field textarea{min-height:70px;resize:vertical;}
    .row-inline{
      display:flex;
      gap:8px;
    }
    .row-inline .field{flex:1;}
    .small-hint{
      font-size:0.75rem;
      color:#6b7280;
      margin-top:2px;
    }
    .products-wrapper{
      max-height:75vh;
      overflow-y:auto;
      padding-right:4px;
    }
    .product-block{
      border-radius:10px;
      border:1px solid rgba(55,65,81,0.9);
      padding:10px;
      margin-bottom:10px;
      background:rgba(15,23,42,0.9);
    }
    .product-header-line{
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:8px;
      margin-bottom:6px;
    }
    .product-header-line strong{
      font-size:0.95rem;
    }
    .pill{
      font-size:0.7rem;
      border-radius:999px;
      padding:3px 7px;
      border:1px solid rgba(148,163,184,0.6);
      color:#cbd5f5;
    }
    .price-table{
      width:100%;
      border-collapse:collapse;
      margin-top:4px;
      margin-bottom:4px;
      font-size:0.8rem;
    }
    .price-table th,
    .price-table td{
      border-bottom:1px solid rgba(55,65,81,0.9);
      padding:3px 4px;
      text-align:left;
    }
    .price-table th{
      color:#9ca3af;
      font-weight:500;
    }
    .price-table input{
      width:100%;
      padding:3px 4px;
      border-radius:6px;
      border:1px solid #4b5563;
      background:#020617;
      color:#e5e7eb;
      font-size:0.8rem;
    }
    .btn-add-line{
      margin-top:4px;
      font-size:0.75rem;
      padding:3px 8px;
      border-radius:999px;
      border:1px dashed rgba(148,163,184,0.7);
      background:transparent;
      color:#9ca3af;
      cursor:pointer;
    }
    .btn-add-line:hover{
      background:rgba(148,163,184,0.1);
    }
    .checkbox-row{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:4px;
      font-size:0.8rem;
    }
    .checkbox-row label{
      display:flex;
      align-items:center;
      gap:4px;
      cursor:pointer;
    }
    .alert-success{
      margin-bottom:8px;
      padding:6px 10px;
      border-radius:8px;
      background:rgba(22,163,74,0.15);
      border:1px solid rgba(22,163,74,0.6);
      font-size:0.8rem;
      color:#bbf7d0;
    }
    .alert-error{
      margin-bottom:8px;
      padding:6px 10px;
      border-radius:8px;
      background:rgba(185,28,28,0.15);
      border:1px solid rgba(220,38,38,0.6);
      font-size:0.8rem;
      color:#fecaca;
    }
    .btn-save-main{
      margin-top:10px;
      width:100%;
      padding:10px 14px;
      border-radius:999px;
      border:none;
      background:#0ea5e9;
      color:#f9fafb;
      font-weight:600;
      font-size:0.95rem;
      cursor:pointer;
    }
    .btn-save-main:hover{background:#0284c7;}
    .footer-note{
      margin-top:8px;
      font-size:0.75rem;
      color:#6b7280;
      text-align:center;
    }
    .btn-delete{
      background:rgba(239,68,68,0.15);
      border:1px solid rgba(239,68,68,0.4);
      border-radius:50%;
      width:28px;
      height:28px;
      cursor:pointer;
      color:#fecaca;
      font-size:0.9rem;
      line-height:1;
      display:flex;
      align-items:center;
      justify-content:center;
    }
    .btn-delete:hover{
      background:rgba(239,68,68,0.35);
    }
    .btn-clear-chat{
      margin-top:6px;
      width:100%;
      padding:8px 12px;
      border-radius:999px;
      border:none;
      background:#b91c1c;
      color:#fee2e2;
      font-size:0.85rem;
      font-weight:600;
      cursor:pointer;
    }
    .btn-clear-chat:hover{
      background:#dc2626;
    }
  </style>
</head>
<body>
  <div class="layout">
    <div class="topbar">
      <div>
        <h1>Admin catalogue</h1>
        <div class="subtitle">Modifie les produits et la config. Tout est enregistré dans <code>config.json</code> &amp; <code>produits.json</code>.</div>
      </div>
      <form method="post" action="admin_logout.php" onsubmit="return confirm('Se déconnecter ?');">
        <!-- Optionnel : crée un admin_logout.php qui fait session_destroy() -->
        <button type="submit" class="btn-logout">Déconnexion</button>
      </form>
    </div>

    <?php if ($saveMessage): ?>
      <div class="alert-success"><?= htmlspecialchars($saveMessage) ?></div>
    <?php endif; ?>
    <?php if ($saveError): ?>
      <div class="alert-error"><?= htmlspecialchars($saveError) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="save_all" value="1">

      <div class="grid">
        <!-- CONFIG -->
        <div>
          <div class="card">
            <h2>Configuration générale</h2>
            <div class="field">
              <label>Nom du site</label>
              <input type="text" name="siteName" value="<?= htmlspecialchars($config['siteName'] ?? '') ?>">
            </div>
            <div class="field">
              <label>Sous-titre / tagline</label>
              <input type="text" name="tagline" value="<?= htmlspecialchars($config['tagline'] ?? '') ?>">
            </div>
            <div class="field">
              <label>Logo (URL ou chemin)</label>
              <input type="text" name="logoUrl" value="<?= htmlspecialchars($config['logoUrl'] ?? '') ?>">
              <div class="small-hint">Ex : <code>logo.png</code></div>
            </div>
            <div class="field">
              <label>Fond du catalogue (URL ou chemin)</label>
              <input type="text" name="catalogBackgroundUrl" value="<?= htmlspecialchars($config['catalogBackgroundUrl'] ?? '') ?>">
            </div>
            <div class="field">
              <label>Couleur principale (hex)</label>
              <input type="text" name="primaryColor" value="<?= htmlspecialchars($config['primaryColor'] ?? '#0088cc') ?>">
            </div>
            <div class="field">
              <label>Contact Telegram (URL)</label>
              <input type="text" name="telegramContactUrl" value="<?= htmlspecialchars($config['telegramContactUrl'] ?? '') ?>">
            </div>
            <div class="field">
              <label>Redirection commande (URL)</label>
              <input type="text" name="orderRedirectUrl" value="<?= htmlspecialchars($config['orderRedirectUrl'] ?? '') ?>">
            </div>
            <div class="field">
              <label>Texte du bouton commande</label>
              <input type="text" name="orderRedirectLabel" value="<?= htmlspecialchars($config['orderRedirectLabel'] ?? '') ?>">
            </div>
            <div class="field">
              <label>Texte page Infos</label>
              <textarea name="infoText"><?= htmlspecialchars($config['infoText'] ?? '') ?></textarea>
              <div class="small-hint">Ce texte s’affiche dans la page Infos (index &gt; onglet ℹ️).</div>
            </div>
          </div>

          <!-- 🔹 Carte pour vider le chat -->
          <div class="card">
            <h2>Chat public</h2>
            <p class="small-hint">Vider tous les messages envoyés dans le chat de l’app (action définitive).</p>
            <button type="submit" name="clear_chat" value="1" class="btn-clear-chat" onclick="return confirm('Vider tout l\\'historique du chat ?');">
              🗑️ Vider l’historique du chat
            </button>
          </div>
        </div>

        <!-- PRODUITS -->
        <div>
          <div class="card">
            <h2>Produits</h2>
            <div class="small-hint">Modifie les produits ci-dessous. La dernière carte vide permet d’ajouter un nouveau produit.</div>
            <div class="products-wrapper" id="products-wrapper">
              <?php foreach ($productsForForm as $index => $p): 
                $isNew = ($p['id'] === '' && trim($p['nom']) === '');
              ?>
                <div class="product-block" data-index="<?= $index ?>">
                  <div class="product-header-line">
                    <strong><?= $isNew ? 'Nouveau produit' : htmlspecialchars($p['nom'] ?: 'Produit #'.$p['id']) ?></strong>
                    <div style="display:flex;gap:6px;align-items:center;">
                      <span class="pill"><?= $isNew ? 'Nouveau' : 'ID : '.htmlspecialchars($p['id']) ?></span>
                      <?php if(!$isNew): ?>
                        <button type="button" class="btn-delete" onclick="deleteProduct(<?= $index ?>)">🗑️</button>
                      <?php endif; ?>
                    </div>
                  </div>

                  <input type="hidden" name="products[<?= $index ?>][id]" value="<?= htmlspecialchars($p['id']) ?>">

                  <div class="field">
                    <label>Nom du produit</label>
                    <input type="text" name="products[<?= $index ?>][nom]" value="<?= htmlspecialchars($p['nom']) ?>">
                  </div>

                  <div class="row-inline">
                    <div class="field">
                      <label>Catégorie</label>
                      <input type="text" name="products[<?= $index ?>][categorie]" value="<?= htmlspecialchars($p['categorie']) ?>">
                    </div>
                    <div class="field">
                      <label>Producteur / Farm</label>
                      <input type="text" name="products[<?= $index ?>][farm]" value="<?= htmlspecialchars($p['farm']) ?>">
                    </div>
                  </div>

                  <div class="field">
                    <label>Badges (séparés par des virgules)</label>
                    <input type="text" name="products[<?= $index ?>][badges]" value="<?= htmlspecialchars($p['badges']) ?>">
                  </div>

                  <div class="field">
                    <label>Image (URL)</label>
                    <input type="text" name="products[<?= $index ?>][image]" value="<?= htmlspecialchars($p['image']) ?>">
                  </div>

                  <div class="field">
                    <label>Vidéo (URL .mp4) – facultatif</label>
                    <input type="text" name="products[<?= $index ?>][video_url]" value="<?= htmlspecialchars($p['video_url']) ?>">
                    <div class="small-hint">Si rempli, la fiche produit affichera la vidéo à la place de l’image.</div>
                  </div>

                  <div class="field">
                    <label>Description</label>
                    <textarea name="products[<?= $index ?>][description]"><?= htmlspecialchars($p['description']) ?></textarea>
                  </div>

                  <div class="checkbox-row">
                    <label>
                      <input type="checkbox" name="products[<?= $index ?>][in_stock]" value="1" <?= $p['in_stock'] ? 'checked' : '' ?>>
                      En stock
                    </label>
                    <label>
                      <input type="checkbox" name="products[<?= $index ?>][featured]" value="1" <?= $p['featured'] ? 'checked' : '' ?>>
                      Produit vedette
                    </label>
                  </div>

                  <div class="field" style="margin-top:8px;">
                    <label>Grammages &amp; prix</label>
                    <table class="price-table" data-price-table="<?= $index ?>">
                      <thead>
                        <tr>
                          <th>Label (ex: 1g, 5g)</th>
                          <th>Prix (€)</th>
                          <th>Prix promo (€)</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php
                          $options = $p['prix_options'];
                          if (empty($options)) {
                              $options = [
                                  ['label' => '', 'prix' => '', 'prix_promo' => '']
                              ];
                          }
                          foreach ($options as $optIndex => $opt):
                        ?>
                          <tr>
                            <td>
                              <input type="text" name="products[<?= $index ?>][prix_options][<?= $optIndex ?>][label]" value="<?= htmlspecialchars($opt['label'] ?? '') ?>">
                            </td>
                            <td>
                              <input type="text" name="products[<?= $index ?>][prix_options][<?= $optIndex ?>][prix]" value="<?= htmlspecialchars($opt['prix'] ?? '') ?>">
                            </td>
                            <td>
                              <input type="text" name="products[<?= $index ?>][prix_options][<?= $optIndex ?>][prix_promo]" value="<?= htmlspecialchars($opt['prix_promo'] ?? '') ?>">
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                    <button type="button" class="btn-add-line" onclick="addPriceLine(<?= $index ?>)">+ Ajouter une ligne</button>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <button type="submit" class="btn-save-main">💾 Enregistrer toutes les modifications</button>
      <div class="footer-note">
        Les fichiers <code>config.json</code> et <code>produits.json</code> sont réécrits à chaque enregistrement.
      </div>
    </form>
  </div>

  <script>
    // Ajoute une ligne de prix dans le tableau
    function addPriceLine(productIndex){
      const table = document.querySelector('table[data-price-table="'+productIndex+'"] tbody');
      if(!table) return;
      const rows = table.querySelectorAll('tr');
      const nextIndex = rows.length;

      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>
          <input type="text" name="products[${productIndex}][prix_options][${nextIndex}][label]" value="">
        </td>
        <td>
          <input type="text" name="products[${productIndex}][prix_options][${nextIndex}][prix]" value="">
        </td>
        <td>
          <input type="text" name="products[${productIndex}][prix_options][${nextIndex}][prix_promo]" value="">
        </td>
      `;
      table.appendChild(tr);
    }

    // Supprime visuellement un produit + vide tous ses champs
    function deleteProduct(productIndex){
      if(!confirm("Supprimer ce produit ?")) return;
      const block = document.querySelector('.product-block[data-index="'+productIndex+'"]');
      if(!block) return;

      block.querySelectorAll('input, textarea').forEach(el=>{
        if(el.type === 'checkbox'){
          el.checked = false;
        }else{
          el.value = '';
        }
      });

      block.style.opacity = '0.4';
      block.style.display = 'none';
    }
  </script>
</body>
</html>
