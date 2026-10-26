<?php
header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../config/db.php';

use Cloudinary\Cloudinary;

function reponse(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    reponse(405, ['success' => false, 'message' => 'Méthode non autorisée.']);
}

// 1. Recevoir et vérifier les champs
$nom    = trim($_POST['nom'] ?? '');
$prenom = trim($_POST['prenom'] ?? '');
$ville  = trim($_POST['ville'] ?? '');

if ($nom === '' || $prenom === '' || $ville === '') {
    reponse(400, ['success' => false, 'message' => 'Nom, prénom et ville sont obligatoires.']);
}
if (mb_strlen($nom) > 100 || mb_strlen($prenom) > 100 || mb_strlen($ville) > 100) {
    reponse(400, ['success' => false, 'message' => 'Champs trop longs (100 caractères max).']);
}

// 2. Vérifier le fichier image
if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    reponse(400, ['success' => false, 'message' => 'Veuillez choisir une photo valide.']);
}

$fichier = $_FILES['photo'];
if ($fichier['size'] > 4 * 1024 * 1024) {
    reponse(400, ['success' => false, 'message' => 'La photo dépasse 4 Mo.']);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime  = $finfo->file($fichier['tmp_name']);
if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
    reponse(400, ['success' => false, 'message' => 'Format accepté : JPG, PNG ou WEBP.']);
}

try {
    // 3 & 4. Envoyer la photo à Cloudinary et récupérer l'URL sécurisée
    $cloudinary = new Cloudinary(getenv('CLOUDINARY_URL'));
    $upload = $cloudinary->uploadApi()->upload($fichier['tmp_name'], [
        'folder' => 'stagiaires',
    ]);
    $photoUrl = $upload['secure_url'];

    // 5. Insérer dans MySQL
    $pdo  = getDatabase();
    $stmt = $pdo->prepare(
        'INSERT INTO stagiaires (nom, prenom, ville, photo) VALUES (:nom, :prenom, :ville, :photo)'
    );
    $stmt->execute([
        ':nom'    => $nom,
        ':prenom' => $prenom,
        ':ville'  => $ville,
        ':photo'  => $photoUrl,
    ]);

    // 6. Réponse JSON
    reponse(201, ['success' => true, 'message' => 'Stagiaire inscrit avec succès.', 'photo' => $photoUrl]);
} catch (Throwable $e) {
    error_log($e->getMessage());
    reponse(500, ['success' => false, 'message' => 'Erreur serveur. Consultez les logs Vercel.']);
}
