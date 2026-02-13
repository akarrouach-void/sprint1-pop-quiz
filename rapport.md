# Rapport de Résolution des Problèmes - Sprint 1 Pop Quiz - Karrouach Ansar

## Chapitre 1: Backend

### ⚠️ Gestion des Erreurs

#### Problème Identifié

La page `http://localhost:8888/broken` retourne une erreur HTTP 500 sans message d'indication visible.

#### Analyse de l'Erreur

- **Cause racine** : Faute de frappe dans `backend/index.php` au niveau du gestionnaire de route `/broken` : `$response->getBody()->wr​ite("Hello world!");` contient un caractère invisible (espace de largeur nulle, U+200B) entre `wr` et `ite`, rendant la méthode invalide.
- **Comportement** : Erreur fatale PHP ("Call to undefined method"), mais `display_errors` est désactivé, et les erreurs sont loggées dans `/tmp/sprint1-php-error.log`.
- **Vérification** : Consultez le log pour l'erreur exacte.

#### Solution Proposée

Corrigez la méthode en supprimant le caractère invisible.

```php
// filepath: /Users/void/dev/sprint1-pop-quiz/backend/index.php
$app->get('/broken', function (Request $request, Response $response, $args) {
    /** @disregard P1013 because we're just testing */
    $response->getBody()->write("Hello world!");
    return $response;
});
```

### 🔄 Gestion des Connexions Simultanées

#### Problème Identifié

Le serveur crash lors du test Apache Benchmark avec des connexions simultanées sur la route `/crash`.

**Test effectué:**

```bash
ab -n 200 -c 10 http://localhost:8888/crash
```

#### Analyse de l'Erreur

**1. Problème de gestion de fichier manquant:**

```php
$logEntries = file($logFile);
```

Si le fichier `request_log.txt` n'existe pas, `file()` retourne `false`, ce qui provoque une erreur lors de `count($logEntries)`.

**2. Code problématique d'allocation mémoire:**

```php
if (count($logEntries) > 0xA) {  // Si plus de 10 lignes
    $contentClear = str_repeat('A', 0x9FFFF0);  // Tente d'allouer ~10 MB
    file_put_contents($logFile, $contentClear);
}
```

**Problèmes identifiés:**

- `0x9FFFF0` = 10,485,744 octets (~10 MB)
- Limite mémoire initiale: `ini_set('memory_limit', '8M')`
- **Dépassement de mémoire**: 10 MB > 8 MB → Crash fatal
- Lors de connexions simultanées, plusieurs processus tentent cette allocation en même temps

#### Solution Implémentée

**1. Vérification de l'existence du fichier:**

```php
$logEntries = file($logFile);

if ($logEntries === false) {
    $logEntries = [];
}
```

Cette vérification évite les erreurs lorsque le fichier n'existe pas encore.

**2. Augmentation de la limite mémoire:**

```php
ini_set('memory_limit', '128M');  // Augmenté de 8M à 128M
```

**Justification de la solution:**

- Résout le problème de dépassement mémoire
- Permet au code existant de fonctionner correctement
- Adapte les ressources système au code plutôt que de modifier la logique métier
- Le serveur peut maintenant gérer l'allocation de 10 MB sans crash

**Alternative possible (non retenue):**
Il serait également possible d'optimiser le code en remplaçant l'allocation massive:

```php
file_put_contents($logFile, '', LOCK_EX);  // Vide le fichier (0 octets)
```

Cependant, cette approche modifie le comportement initial du code. L'augmentation de mémoire a été privilégiée pour respecter la logique existante du système de logging.

#### Test de Validation

```bash
ab -n 200 -c 10 http://localhost:8888/crash
```

**Résultat:** ✅ Le serveur gère maintenant toutes les requêtes sans crash. Les 200 requêtes avec 10 connexions simultanées sont traitées avec succès.

---

## Chapitre 2: Frontend

### 🔍 Appel Fetch / XHR sur la Route /fetch

#### Problème Identifié

Requêtes XHR bloquées par CORS:

```
Access to fetch at 'http://localhost:8888/fetch' from origin 'http://localhost:5173'
has been blocked by CORS policy: No 'Access-Control-Allow-Origin' header is present
```

Erreur 401 Unauthorized - authentification manquante.

#### Solution Implémentée

**1. Middleware CORS dans backend/index.php:**

```php
$app->add(function ($request, $handler) {
    // Gérer les requêtes OPTIONS pour le pré-vol CORS
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
        return $response
            ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withStatus(200);
    }

    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});
```

**Point clé:** La gestion du preflight OPTIONS est essentielle. Le navigateur envoie automatiquement une requête OPTIONS avant la vraie requête GET quand un header Authorization est présent.

**2. Configuration frontend (.env + fetch.lazy.jsx):**

```javascript
// .env
VITE_API_TOKEN=dXNlcm5hbWU6cGFzc3dvcmQ=

// fetch.lazy.jsx
headers: {
    Authorization: `Basic ${import.meta.env.VITE_API_TOKEN}`,
}
```

**Résultat:** ✅ Requêtes XHR réussies avec authentification Basic Auth.
